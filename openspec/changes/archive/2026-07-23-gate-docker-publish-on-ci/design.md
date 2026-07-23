## Context

`ci.yml` (lint, PHPStan, PHPUnit, plus a Docker build + `/health` smoke test)
and `docker-publish.yml` both trigger on `push` to `dev`/`main` and run
concurrently. Nothing links them, so the publish can finish and push an image
to Docker Hub while CI is still running or has already failed. The publish
workflow already sequences its own steps correctly (release tag only after a
successful push), but it has no visibility into CI's result.

The two workflows must stay decoupled as files but become ordered at runtime:
publish should start only once CI for the same commit is green.

## Goals / Non-Goals

**Goals:**
- Publish only after CI succeeds for the exact commit being published.
- On CI failure/cancellation, publish nothing (no tag, no release).
- Preserve every existing publish behavior: which tag per branch, VERSION-driven
  release, `sha-<short>` tag, fork skip, concurrency cancellation.
- Keep `workflow_dispatch` as a manual escape hatch.

**Non-Goals:**
- Merging the two workflows into one job/file.
- Changing what CI checks, or adding new checks.
- Changing image names, tags, or the release-cutting logic.

## Decisions

### Trigger publish via `workflow_run` on CI completion
Replace the publish workflow's `push` trigger with:

```yaml
on:
  workflow_run:
    workflows: ["CI"]
    types: [completed]
    branches: [main, dev]
  workflow_dispatch:
```

`workflows: ["CI"]` must match the CI workflow's `name:` exactly (`CI`), so the
two are coupled by that string — noted in the proposal's Impact.

Gate the job on success:
```yaml
if: >-
  github.repository_owner == 'jeremehancock' &&
  (github.event_name == 'workflow_dispatch' ||
   github.event.workflow_run.conclusion == 'success')
```

**Alternatives considered:**
- *A single combined workflow with `needs: [quality, docker]`.* Cleanest
  ordering, but collapses CI and publish into one file and one event, and would
  re-run the full CI matrix semantics inside the publish concern. Rejected to
  keep CI reusable and the publish concern isolated.
- *`gh run watch` / polling from within publish.* Wastes a runner blocking on
  CI and duplicates success logic. Rejected.

### Derive branch and SHA from the `workflow_run` payload, not `github.ref`
Under `workflow_run`, the workflow file is taken from the default branch and
`github.ref`/`github.sha` point at the default branch, **not** the commit that
triggered CI. The current file keys branch logic off `github.ref` and lets
`checkout` default to the current ref — both break silently under
`workflow_run`. So:

- Checkout the tested commit explicitly:
  ```yaml
  - uses: actions/checkout@v4
    with:
      ref: ${{ github.event.workflow_run.head_sha }}
      fetch-depth: 0
      fetch-tags: true
  ```
- Compute a single `branch` value once (from `github.event.workflow_run.head_branch`
  for the automatic path, `github.ref_name` for manual dispatch) and replace
  every `github.ref == 'refs/heads/...'` / `is_default_branch` check with tests
  against that value. This drives the `:dev` vs `:latest` selection and the
  `main`-only release detection.

### Scope concurrency to the resolved branch
The concurrency group currently uses `github.ref`, which is now constant.
Re-key it to the resolved head branch so two publishes for the same branch
still supersede each other:
```yaml
concurrency:
  group: docker-publish-${{ github.event.workflow_run.head_branch || github.ref_name }}
  cancel-in-progress: true
```

## Risks / Trade-offs

- **CI `name:` drift silently disables publishing.** If `ci.yml`'s `name` stops
  being `CI`, `workflow_run` never fires and images quietly stop publishing.
  → Mitigation: the spec scenario ties publish to CI success; keep the name
  reference adjacent in review, and manual dispatch remains as a fallback.
- **`workflow_run` context pitfalls** (default-branch ref, wrong checkout).
  → Mitigation: explicit `head_sha` checkout and branch derived from the payload,
  covered above; this is the main correctness risk.
- **`workflow_run` only fires for workflow files on the default branch.** A
  change to the publish trigger takes effect only once merged to `main`; on a
  feature branch the new trigger won't run. → Expected for GitHub Actions;
  validate via a `dev`→ merge, using manual dispatch if needed.
- **Slightly longer time-to-publish**, since publish now waits for all of CI
  instead of racing it. → Acceptable; correctness over speed for releases.

## Migration Plan

1. Land the workflow change on `dev`; merge to `main` so the new `workflow_run`
   trigger becomes active (workflow_run reads the file from the default branch).
2. Push a trivial commit to `dev`; confirm CI runs, then publish fires only
   after CI is green and produces `bozodev/marquee:dev`.
3. Force a CI failure on `dev` (or observe a real one) and confirm no image is
   published.
4. Rollback: revert the workflow file to the `push` trigger; no state to undo.

## Open Questions

None.
