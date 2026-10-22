## Context

`docker-publish.yml` runs one job on `ubuntu-latest` that sets up QEMU and calls `docker/build-push-action` with `platforms: linux/amd64,linux/arm64`. Buildx builds amd64 natively and arm64 under emulation, then pushes one multi-architecture image under every tag at once. The tag and GitHub Release step runs afterwards in the same job.

Since 2.16.0 the image compiles `gd` and `intl`. With a warm cache a publish takes about a minute. On the first build after the official PHP image moves to a new patch, the cache misses and the emulated arm64 compile dominates: about 13 minutes on the first 2.16.0-line publish. That first build is the security release.

GitHub provides native arm64 runners (`ubuntu-24.04-arm`, 4 vCPU, 16 GB). They are free and generally available for public repositories, which Marquee is.

The publish workflow is triggered by `workflow_run`, which always reads the workflow file from the default branch. A change to it therefore only takes effect on the automatic path after it is merged. `workflow_dispatch` on a branch, however, runs that branch's copy of the file, which gives a way to exercise it on `dev` first.

## Goals / Non-Goals

**Goals:**

- No architecture is compiled under emulation.
- Tags only ever name a complete two-architecture image, and the release waits for it.
- CI tests the image on both architectures before a commit can publish.
- Every other publish behavior in the `release-publishing` spec is unchanged.

**Non-Goals:**

- Adding or dropping architectures.
- Changing triggers, concurrency, tag names, or the CI gate.

## Decisions

### Three jobs: prepare, build per architecture, merge

1. **prepare** (`ubuntu-latest`): the existing job guard (`if:`), checkout of the tested commit, the existing version and release detection, and `docker/metadata-action`. It exposes the branch, version, release flag, the computed tags and the labels as job outputs, so the tags are computed once and every later job uses the same ones.
2. **build** (matrix: `linux/amd64` on `ubuntu-latest`, `linux/arm64` on `ubuntu-24.04-arm`): checkout, Buildx, Docker Hub login, then `build-push-action` for that one platform with `outputs: type=image,name=bozodev/marquee,push-by-digest=true,name-canonical=true,push=true`. It pushes the image without any tag and uploads its digest as an artifact.
3. **merge** (`ubuntu-latest`, `needs: [prepare, build]`): downloads both digests, runs `docker buildx imagetools create` with every tag from prepare, pointing at both digests, and inspects the result to confirm both platforms are listed. The existing "Create tag and GitHub Release" step moves here, after the inspect, unchanged.

This is Docker's documented pattern for distributing a multi-platform build across runners.

- *Pushing per-architecture tags (`:latest-amd64`) and combining them*: leaves extra tags on Docker Hub that users might pull by mistake. Pushing by digest leaves nothing pullable by name until the merge.
- *Two separate workflows*: duplicates the guard, the version logic and the tag rules, which would drift.

### A failure in one architecture stops before any tag moves

`merge` needs every `build` job to succeed, so when either fails, `merge` never runs. No tag moves and no release is created. The digest the successful architecture pushed stays on Docker Hub, untagged and unreachable by name. That is harmless, and it is what the spec asks for.

The matrix sets `fail-fast: false`. With the default, a failure in one architecture would cancel the other mid-build. The release-publishing spec allows a run to be cancelled only by deliberate manual action, and letting the other build finish also tells the maintainer whether the failure is specific to one architecture.

### Cache per architecture

`cache-from`/`cache-to` use `type=gha` with `scope=marquee-<arch>`. Two jobs writing the same scope would overwrite each other's entries, and each architecture would keep missing.

### Labels on the images, tags on the index

Labels from `metadata-action` go on each architecture's image at build time, as now, so `docker image inspect` keeps reporting the commit and version. `imagetools create` applies only the tags.

### Validate on `dev` before the merge

After this change is pushed to `dev`, a manual dispatch on `dev` runs the new file and publishes `:dev` through the new path. That shows both architectures building natively and the merge producing a two-platform `:dev`, with a cold cache (the new cache scopes start empty), then warm on a second dispatch. The release step cannot run on `dev`. It is verified on the release publish itself, which the spec's deprecation requirement asks for in any case: the release path is assessed on a run that took it.

### Say what a run publishes, in its title and its last job's name

The workflow sets `run-name` from the branch the publish is for, which is known as soon as the run starts (`github.event.workflow_run.head_branch`, or the dispatched ref): `main` reads `Publish :latest`, `dev` reads `Publish :dev`, any other branch reads `Publish <branch>` (such a run only gets a `sha-<short>` tag), and a manual dispatch adds `(manual)`.

Whether the run is also a release is not known at that point: it depends on `VERSION` and the existing tags, which `prepare` reads. So the `merge` job's name carries it instead, built from `prepare`'s outputs: `Tag :latest` on an ordinary `main` publish, `Tag :latest, :<version> and release v<version>` on a release, `Tag :dev` on `dev`. Job names can read `needs` outputs, so this costs no extra step.

- *Putting the release in the run title*: would need a second workflow or a pre-run to read `VERSION`. The last job's name is visible in the run list's details and on the run page, which is where a release is checked.

### Smoke-test both architectures in CI with a runner matrix

The `docker` job in `ci.yml` becomes a matrix over `ubuntu-latest` and `ubuntu-24.04-arm`, running the existing build and every existing smoke-test step unchanged on each. The steps already avoid anything architecture-specific (they use `docker`, `curl`, `stat`, `netstat`), so one definition serves both. `fail-fast: false`, for the same reason as the publish matrix: no automatic cancellations, and a failure on one architecture should still report the other.

Because the publish waits for the whole CI run, an arm64 failure blocks the publish with no change to `docker-publish.yml`.

- *Smoke-testing inside the publish workflow, after the build*: by then the image has been pushed by digest, and a failure there is a failed publish instead of a red commit. Testing in CI keeps the existing order: test, then publish.
- *Emulating arm64 in CI*: the slowness this change exists to remove, and emulation can hide or invent faults that native hardware would not.

## Risks / Trade-offs

- [The arm64 runner queues during GitHub busy periods] → It only delays that publish, which already queues instead of cancelling. Nothing is published partially.
- [The `ubuntu-24.04-arm` label is retired one day] → GitHub warns ahead of removal, and the spec already treats such a warning as a defect to fix before the deadline.
- [A first run through the new path happens on the release itself] → The `dev` dispatch exercises everything except the final tag and release step, which is moved without change.
- [Untagged per-architecture digests accumulate on Docker Hub] → They are referenced by the tagged index, as the per-architecture images are today. Only a failed publish leaves one unreferenced.
- [Three jobs take longer to start than one] → Seconds of runner start-up against about twelve minutes saved on the build that matters.

## Migration Plan

1. Push the change to `dev`. CI runs as usual. The automatic `:dev` publish still uses `main`'s current file.
2. Dispatch the publish workflow on `dev` twice (cold cache, then warm). Confirm `:dev` lists both platforms, record both timings, and confirm the image still starts and reports PHP on both architectures.
3. The maintainer validates `:dev`. Release through `/ship`. Check the release run created the tag and GitHub Release after the merge step.

**Rollback**: revert the workflow file. Published images are unaffected either way.

## Open Questions

None.
