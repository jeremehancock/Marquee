## Context

Publishing is a two-workflow arrangement: `ci.yml` runs quality + a docker
smoke test on every push and pull request; `docker-publish.yml` triggers via
`workflow_run` when CI completes and pushes the multi-arch image to Docker Hub.

The observed defect: each shipped commit is CI'd twice — once as a `push` to
`dev`, and once as a `pull_request` when the PR to `main` is opened on the same
SHA. Both CI runs complete, and each fires a `docker-publish` run. The two
publishes land in the **same** concurrency group because the group is keyed on
branch only (`docker-publish-<head_branch>`), and a `pull_request` CI run's
`workflow_run.head_branch` is the source branch — the same `dev` as the push.

The order of evaluation is what makes it destructive:

```
  push CI (defa2fd) done → publish B created
     group=docker-publish-dev, if=push ✓ → starts BUILDING
  pull_request CI (defa2fd) done → publish C created
     group=docker-publish-dev  ← SAME GROUP, cancel-in-progress:true
       → 🔪 cancels B mid-build            (B = CANCELLED)
       → job if: event=='push'? no → C SKIPPED
```

Concurrency is resolved when a run is *created*; the job-level `if` is evaluated
later. So the run that was going to skip anyway cancels the legitimate publish
before skipping itself. The earlier fix (adding `workflow_run.event == 'push'`
to the job `if`) turned the duplicate from a *failure* into a *skip*, but the
cancellation happens at the concurrency layer that fix never touched.

Investigation ground-truth (ship of 2026-07-27): commit `defa2fd` produced a
cancelled publish (25s, mid-build) immediately followed by a skipped publish
(6s), both from the same SHA — one from the push CI, one from the pull_request
CI. Branch protection on `main`: none. `dev` is even with `main`.

## Goals / Non-Goals

**Goals:**
- No CI or publish run is ever cancelled or skipped automatically.
- Each pushed commit is built by CI exactly once and publishes exactly once.
- Preserve every existing publish guarantee: branch→tag mapping, VERSION-driven
  releases, the CI-must-pass gate, the immutable `sha-<short>` tag, and
  `workflow_dispatch` as a manual override.

**Non-Goals:**
- Rearchitecting publishing into a single workflow (folding publish into CI as a
  `needs`-gated job). That is a larger refactor considered and rejected below.
- Retaining CI coverage for external fork pull requests (explicitly traded away).
- Changing anything about the published image, its tags, or runtime behavior.

## Decisions

### Decision 1: Remove the `pull_request` trigger from CI

`ci.yml` triggers on both `push: branches: ['**']` and `pull_request`. The
`pull_request` run is pure redundancy in this flow — it tests the exact same SHA
the push already tested — and it is the sole source of the second `docker-publish`
run (the skipped one) and therefore of the cancellation. Removing it makes each
commit produce exactly one CI run.

PR status still works without it: GitHub check runs attach to the **commit SHA**,
not the triggering event, so the push CI's result is visible on the PR. There is
no branch protection consuming a required check, so nothing breaks.

*Alternative considered — fold publish into CI as a `needs`-gated job:* removes
the `workflow_run` indirection and its head_sha/head_branch quirks entirely, and
keeps `pull_request` CI (so fork PRs stay covered) at the cost of a greyed
*skipped job* inside pull_request runs. Rejected for this change because it is a
larger structural change and still leaves a visible skipped job — the opposite of
the stated goal — whereas removing the `pull_request` trigger yields a flow where
every run is green.

### Decision 2: `cancel-in-progress: false` on the publish concurrency

With the redundant pull_request run gone, the only remaining way an automated
publish could be cancelled is a genuinely rapid second push to the same branch
while the first publish is still building. `cancel-in-progress: true` would kill
the first. Setting it to `false` keeps the concurrency group (so same-branch
publishes still serialize and never race) but makes a superseding run **queue**
and run after, rather than cancel. Result: no automated cancellation, ever.

*Alternative considered — key the concurrency group on the CI event as well
(`...-<workflow_run.event>`):* this would stop the pull_request publish from
cancelling the push publish while keeping `cancel-in-progress: true`. Rejected
because Decision 1 already removes the pull_request run, making the event key
moot, and because `true` can still cancel on rapid same-event pushes — which
violates the "no cancelled runs" goal.

### Decision 3: Leave the `workflow_run.event == 'push'` job guard in place

With no pull_request CI runs, this guard has nothing left to catch. It is
harmless dead weight and acts as belt-and-suspenders if a pull_request trigger is
ever reintroduced. Removing it is optional and out of scope.

## Risks / Trade-offs

- **External fork PRs get no CI in this repo** → Accepted. Fork pushes do not run
  workflows in the base repo; `pull_request` was what covered them. This is a
  solo `dev` → `main` project with owner-gated publishing, so the loss is
  acceptable. If outside contributions become common, revisit Decision 1 (or move
  to the folded-job architecture that keeps `pull_request`).
- **Push CI tests the branch tip, not the merge-into-`main` preview** → Low risk.
  `dev` is kept in sync with `main`, so drift is minimal, and the post-merge
  `main` push CI still runs before `:latest` publishes — a broken merge fails CI
  and publishes nothing.
- **A required status check is added to `main` later, expecting the
  `pull_request` event** → Would block PRs that only have a push run. Mitigation:
  if branch protection is introduced, require the push-triggered check by name (it
  exists on the SHA) or reinstate `pull_request` via the folded-job approach.

## Migration Plan

1. Edit `.github/workflows/ci.yml`: remove the `pull_request:` trigger, leaving
   `push: branches: ['**']`.
2. Edit `.github/workflows/docker-publish.yml`: set
   `concurrency.cancel-in-progress: false`.
3. Verify on the next ship that the flow shows one green CI and one green publish
   per commit, with zero cancelled or skipped runs.

Rollback: revert the two workflow edits; behavior returns to the current
skipped+cancelled pattern. No state, image, or data migration is involved.

## Open Questions

None.
