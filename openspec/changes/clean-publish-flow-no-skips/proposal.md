## Why

Every ship currently produces an alarming pair of runs — one **cancelled** and
one **skipped** Docker publish — because each commit is built by CI twice: once
as a `push` to `dev`, and again as a redundant `pull_request` run when the PR to
`main` opens. The `pull_request`-triggered publish shares a branch-only
concurrency group with the real `push`-triggered publish and, with
`cancel-in-progress: true`, kills the real build before skipping itself. The
prior fix converted the duplicate from a *failure* into a *skip* but left the
cancellation in place, so the noise — and the risk of cancelling real work —
remains. The maintainer wants a flow where no run is ever cancelled or skipped
unless a person triggers it deliberately.

## What Changes

- Remove the `pull_request` trigger from the CI workflow so each commit is built
  by CI exactly once, via the `push` trigger. This eliminates the redundant
  second CI run that was the sole source of the skipped publish and the
  cancellation.
- Change the publish workflow's concurrency from `cancel-in-progress: true` to
  `false`, so a superseding publish **queues** behind an in-flight one instead
  of cancelling it. No automated publish is ever cancelled.
- **Tradeoff (accepted):** external fork PRs will no longer receive CI in this
  repository, because fork pushes do not run workflows in the base repo and the
  `pull_request` event is what previously covered them. This is acceptable for a
  solo `dev` → `main` flow with no branch protection and owner-gated publishing.
- `workflow_dispatch` remains as the manual override; a person may still cancel
  or re-run a build by hand.

## Capabilities

### New Capabilities

<!-- none -->

### Modified Capabilities

- `release-publishing`: The publish/CI relationship changes from "a
  `pull_request` CI run is ignored" to "a `pull_request` does not trigger CI at
  all," so each commit is CI'd once. Adds the guarantee that the automated flow
  produces no cancelled or skipped runs — a superseded publish queues rather
  than cancels, and cancellation/skipping happen only through manual action.

## Impact

- `.github/workflows/ci.yml` — remove the `pull_request:` trigger.
- `.github/workflows/docker-publish.yml` — set concurrency
  `cancel-in-progress: false`. The job-level `workflow_run.event == 'push'`
  guard becomes redundant (no `pull_request` CI runs remain) but is harmless to
  keep.
- No application code, runtime behavior, or published image contents change;
  this is purely CI/CD orchestration.
