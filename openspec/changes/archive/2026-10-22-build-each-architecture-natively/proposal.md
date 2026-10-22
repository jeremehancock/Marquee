## Why

Marquee now builds on the official PHP image so that PHP security fixes reach users with each release, and the weekly currency check tells the maintainer when a release is due. But the release that carries a new PHP patch is exactly the one that recompiles the `gd` and `intl` extensions, and the arm64 half of the image is compiled under emulation on an amd64 machine. That first build took about 13 minutes against under a minute for a cached one, so the delay lands on security releases specifically. Building each architecture on its own hardware removes it.

## What Changes

- The publish workflow builds the amd64 image on a standard runner and the arm64 image on GitHub's native arm64 runner (`ubuntu-24.04-arm`) at the same time, instead of building both on one amd64 runner with arm64 emulated through QEMU.
- A final step combines the two into one multi-architecture image and applies the tags (`dev`, `latest`, `<version>`, `sha-<short>`). Users pull exactly what they pull today.
- No tag moves unless both architectures built and pushed. If either fails, every tag keeps pointing at the previous complete image.
- The git tag and GitHub Release are created only after the combined image is published, as now. They now also wait for both architectures.
- Each architecture keeps its own build cache, so a cached publish stays fast on both.
- CI's image smoke test runs on both architectures, on native runners, so a fault that only affects arm64 stops the commit before anything is published. Today CI builds and tests amd64 only, and an arm64-only fault would first be found by a user.
- Each publish run in the Actions list says what it publishes. The run title names the moving tag (`Publish :latest`, `Publish :dev`, marked `(manual)` when dispatched), and the final job's name lists the tags it applied and, on a release, the version it released. Today every run is titled "Publish Docker image", and telling a `dev` publish from a release means opening it.
- Everything else about publishing is unchanged: the branch-to-tag mapping, `VERSION`-driven releases, the CI gate, queueing instead of cancelling, and manual dispatch.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `release-publishing`: a release now waits for every architecture rather than "the image"; adds that a tag only ever names a complete multi-architecture image, that each architecture is built on hardware of that architecture rather than emulated, and that CI smoke-tests every architecture before a commit can publish.

## Impact

- `.github/workflows/docker-publish.yml`: split from one job into three (prepare, a build per architecture, merge and release). Adds `actions/upload-artifact` and `actions/download-artifact` to pass image digests between jobs; drops `docker/setup-qemu-action`.
- `docs/docker.md` and `docs/development-workflow.md`: the note that arm64 compiles under emulation, and how a publish is structured.
- `.github/workflows/ci.yml`: the `docker` job becomes a matrix over `ubuntu-latest` and `ubuntu-24.04-arm`, running the same build and smoke test on each.
- No change to the image, the application, or how users pull or update.
