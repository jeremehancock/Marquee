## Why

During a ship, every commit on `dev` triggers CI twice — once from the `push`
event and once from the open `dev → main` pull request re-running on the same
tip. Each CI completion independently triggers the Docker publish via
`workflow_run`, so one commit spawns two concurrent publishes. They land in the
same concurrency group and write the same GitHub Actions cache, so they kill
each other: one is cancelled by `cancel-in-progress`, and the survivor fails
with `error writing layer blob: failed to reserve cache`. The result is a
cancelled + failed publish on every ship, and — since the redundant publish is
for an identical commit — a real risk that a commit's image never ships when
both of its duplicate publishes die.

## What Changes

- The Docker publish workflow reacts only to CI runs that were themselves
  triggered by a `push`. A CI run triggered by a `pull_request` no longer
  spawns a publish, eliminating the duplicate publish at its source.
- With duplicates gone, each pushed commit produces exactly one publish, so the
  same-branch concurrency collision and the GitHub Actions cache race no longer
  occur during a normal ship.
- Manual `workflow_dispatch` publishing is unaffected.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `release-publishing`: publishing is driven only by the CI run of a `push`, not
  by a `pull_request` CI run, so a single commit produces exactly one publish.

## Impact

- `.github/workflows/docker-publish.yml` — the `if` gate on the `publish` job
  gains a condition on `github.event.workflow_run.event`.
- No application code, no runtime behavior, and no published image tags change.
  Only which CI completions are allowed to trigger a publish changes.
