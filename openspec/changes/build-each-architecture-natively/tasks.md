## 1. Publish workflow

- [x] 1.1 Split `docker-publish.yml` into a `prepare` job holding the existing guard, checkout of the tested commit, version and release detection, and `metadata-action`, exposing branch, version, release, tags and labels as job outputs
- [x] 1.2 Add a `build` matrix job (`linux/amd64` on `ubuntu-latest`, `linux/arm64` on `ubuntu-24.04-arm`, `fail-fast: false`) that builds one platform, pushes it by digest with the labels from `prepare`, uses a per-architecture `gha` cache scope, and uploads the digest as an artifact
- [x] 1.3 Add a `merge` job that needs `prepare` and `build`, downloads the digests, runs `docker buildx imagetools create` with every tag from `prepare`, and inspects the result to confirm both platforms are present
- [x] 1.4 Move the "Create tag and GitHub Release" step into `merge`, after the inspect, with its condition and inputs unchanged
- [x] 1.5 Add a `run-name` that names what the run publishes (`Publish :latest`, `Publish :dev`, `Publish <branch>` otherwise, plus `(manual)` on a dispatch), and name the `merge` job from `prepare`'s outputs so it lists the tags applied and, on a release, `release v<version>`
- [x] 1.6 Remove `docker/setup-qemu-action`, keep the workflow-level triggers, concurrency and permissions as they are, and update the header comment to describe the three jobs

## 2. CI

- [x] 2.1 Make the `docker` job in `ci.yml` a matrix over `ubuntu-latest` and `ubuntu-24.04-arm` (`fail-fast: false`), running the existing build and smoke-test steps unchanged on each, with the job name showing the architecture

## 3. Documentation

- [x] 3.1 Update `docs/docker.md`: replace the note that arm64 compiles under emulation with how each architecture is built natively and merged
- [x] 3.2 Update `docs/docker.md`'s local smoke-test section to say CI runs the same checks on both architectures
- [x] 3.3 Update `docs/development-workflow.md` where it describes the publish workflow or CI, if anything there names the single job, emulation, or an amd64-only smoke test

## 4. Verification

- [x] 4.1 Run `composer test`, `composer stan` and `composer cs`, and lint the workflow file (`actionlint` if available)
- [x] 4.2 After pushing to `dev`, confirm CI ran the smoke test on both architectures and passed on each
- [x] 4.3 Dispatch the publish workflow on `dev` and confirm both build jobs ran on runners of their own architecture with no emulation, and that `bozodev/marquee:dev` lists `linux/amd64` and `linux/arm64` and points at the pushed commit
- [x] 4.4 Confirm the dispatched runs are titled `Publish :dev (manual)` and their merge job is named `Tag :dev`, and after the release, that the automatic run reads `Publish :latest` with a merge job naming the version it released
- [x] 4.5 Dispatch a second time and record cold and warm publish times against the previous 13 minutes (cold) and about a minute (warm)
- [x] 4.6 Pull `:dev` for each platform and confirm it is built from the pushed commit with the expected PHP version; start the amd64 image and check `/health` (the arm64 image is started and smoke-tested by CI on the native arm64 runner, which needs no local emulation)
