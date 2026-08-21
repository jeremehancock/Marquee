## Why

Every action in the release pipeline targets the deprecated Node 20 runtime, and
GitHub is already force-running them on Node 24 while warning that it will not do
so forever. Nothing fails today, so the deadline is the only thing that decides
when this gets fixed — and the pipeline it runs through is the one that publishes
`:latest`, the pinned version image, the git tag, and the GitHub Release that
powers the in-app update notice. Users find out that pipeline broke by never
being offered an update again.

## What Changes

- Move all seven Node 20 actions to the current major that targets Node 24:
  `actions/checkout` v4 → v7 (both workflows), `docker/setup-qemu-action` v3 → v4,
  `docker/setup-buildx-action` v3 → v4, `docker/login-action` v3 → v4,
  `docker/metadata-action` v5 → v6, `docker/build-push-action` v6 → v7, and
  `softprops/action-gh-release` v2 → v3.
- Leave `shivammathur/setup-php@v2` alone — its floating major already targets
  Node 24 and it is not named in the deprecation warning.
- Record the standing decision that a deprecation warning in the publish flow is
  a defect to fix rather than output to tolerate, together with the trap that
  makes it easy to get wrong: a run that skipped a step says nothing about that
  step, and the release-creating step is skipped by every run except a release.

Not breaking. Every input the two workflows pass survives all seven bumps, and
the tag mapping, the CI gate, and the release conditions are untouched.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `release-publishing`: adds a requirement that the automated publish flow is
  kept on runner tooling the platform supports, and that evidence about a step's
  health is only taken from a run in which that step actually executed. No
  existing requirement changes — the branch-to-tag mapping, the release
  conditions, and the CI gate all behave exactly as specified before and after.

## Impact

- `.github/workflows/ci.yml` — two `actions/checkout` pins, one per job.
- `.github/workflows/docker-publish.yml` — seven action pins across the publish
  job.
- No application code. No `composer.json` change. The toolchain gate still runs,
  but nothing it covers is touched.
- No documentation change. `docs/development-workflow.md` and the README's
  "Image tags" section describe branch-to-tag behavior and the CI gate; neither
  names an action or a version, so neither goes stale.
- `VERSION` is untouched — this change is deliberately mergeable without a
  version bump, which is what lets the `latest` publish path be exercised on
  `main` before any release depends on it.
