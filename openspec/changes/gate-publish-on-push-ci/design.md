## Context

`docker-publish.yml` triggers on `workflow_run` for the `CI` workflow completing
on `main`/`dev`. `ci.yml` runs on both `push` and `pull_request`. During a ship,
the dev tip commit is built by CI twice — once for the `push` (the archive
commit) and once for the `pull_request` synchronize on the open `dev → main` PR
— both with `head_branch == dev`. Each CI completion fires an independent
`workflow_run`, so one commit produces two publishes.

The two publishes collide on two shared resources:
- The concurrency group `docker-publish-${{ head_branch }}` with
  `cancel-in-progress: true` → the older run is **cancelled**.
- `cache-to: type=gha,mode=max` on the same cache keys → the survivor **fails**
  with `error writing layer blob: failed to reserve cache`, because the
  cancelled run's `mode=max` reservation is left half-held.

Evidence (ship of change #17, dev tip `be01ff5`): `push` CI at 05:03:23 and
`pull_request` CI at 05:03:47 for the same SHA produced publish `30068407938`
(cancelled) and `30068429684` (failed). Both of that commit's publishes died.

## Goals / Non-Goals

**Goals:**
- One pushed commit produces exactly one publish.
- Remove the cancelled + failed publish noise that appears on every ship.
- Change only which CI completions may trigger a publish — no change to image
  tags, release logic, the CI-success gate, or `workflow_dispatch`.

**Non-Goals:**
- Reworking the GitHub Actions cache strategy.
- Changing when CI itself runs (`push` + `pull_request` both stay).
- Touching the release/tag/version logic.

## Decisions

**Decision: Gate the publish job on `github.event.workflow_run.event == 'push'`.**

Add one condition to the existing `if` on the `publish` job so the automatic
path only proceeds when the triggering CI run was itself a `push`. The
`workflow_dispatch` branch of the `if` stays untouched. Concretely the automatic
clause becomes: `workflow_run.conclusion == 'success' && workflow_run.event ==
'push'`.

Why this over the alternatives:

- *Attacks the root cause.* The duplicate is a redundant `pull_request` CI for
  an identical SHA. Suppressing its publish removes the second run entirely, so
  the concurrency cancel and the cache race simply never happen — rather than
  being made survivable.
- *One line, fully reversible*, contained to the publish `if`. No CI semantics
  change, so PR checks still run exactly as before.

Alternatives considered:
- **Per-branch cache scope / `cancel-in-progress: false`** — only make the
  collision survivable; the redundant build still runs, wasting minutes and
  still capable of failing. Rejected as treating the symptom.
- **Drop `pull_request` from CI (rely on `push: ['**']`)** — would also remove
  the duplicate, but changes CI trigger semantics (PR checks, fork PRs) for a
  publishing problem. Larger blast radius than warranted.

## Risks / Trade-offs

- **A commit that only ever exists via a PR head (never pushed to `dev`/`main`)
  would not publish.** → Not a real path here: the publish `workflow_run` filter
  is already `branches: [main, dev]`, and images are only ever wanted for
  commits on those branches, which always arrive via `push`. Fork/PR commits are
  intentionally not published.
- **Relies on `workflow_run.event` reflecting the CI run's own trigger.** → This
  is the documented GitHub semantics; the field is the event that started the
  referenced run (`push` vs `pull_request`), which is exactly the discriminator
  needed.

## Migration Plan

Single-commit edit to `.github/workflows/docker-publish.yml`. Takes effect on
the next push. Rollback is reverting the one-line `if` change. Verify on the
next ship that the dev tip produces exactly one publish and no cancelled/failed
publish appears.

## Open Questions

None.
