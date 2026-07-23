## Why

The Docker publish workflow and the CI workflow both trigger on the same push
and run in parallel, so an image can be built and pushed to Docker Hub — and a
release cut — even when linting, static analysis, tests, or the image smoke
test are failing. Users can end up pulling a `:dev`, `:latest`, or pinned
version image built from a commit CI has already rejected.

## What Changes

- The Docker publish workflow no longer triggers directly on `push`. Instead it
  runs only after the CI workflow completes successfully for that commit.
- A failing (or cancelled) CI run publishes nothing: no moving tag, no pinned
  version image, no git tag, and no GitHub Release.
- The branch/VERSION-driven publish behavior (which tag, when a release is cut)
  is unchanged — only the trigger and its success precondition change.
- Manual `workflow_dispatch` remains available for deliberate re-runs.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `release-publishing`: Publishing gains a precondition — it SHALL run only
  after CI has passed for the commit being published. This adds a
  green-CI gate to the existing "which tag / when a release" rules without
  changing them.

## Impact

- `.github/workflows/docker-publish.yml` — trigger changes from `push` to
  `workflow_run` gated on CI success; per-branch tag logic and release steps
  otherwise preserved.
- `.github/workflows/ci.yml` — CI remains the source of truth for green; its
  name is referenced by the publish trigger, so the two must stay in sync.
- No application code, runtime image contents, or published tag names change.
