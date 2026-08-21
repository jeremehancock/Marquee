## 1. CI workflow

- [x] 1.1 In `.github/workflows/ci.yml`, move the `quality` job's
      `actions/checkout@v4` (line 21) to `@v7`
- [x] 1.2 In `.github/workflows/ci.yml`, move the `docker` job's
      `actions/checkout@v4` (line 52) to `@v7`
- [x] 1.3 Confirm `shivammathur/setup-php@v2` (line 24) is left unchanged — its
      floating major already targets Node 24 and it is absent from the
      deprecation annotation

## 2. Publish workflow

- [x] 2.1 In `.github/workflows/docker-publish.yml`, move
      `actions/checkout@v4` (line 57) to `@v7`, leaving the `ref`,
      `fetch-depth`, and `fetch-tags` inputs exactly as they are
- [x] 2.2 Move `docker/setup-qemu-action@v3` (line 93) to `@v4`
- [x] 2.3 Move `docker/setup-buildx-action@v3` (line 96) to `@v4`, keeping the
      step input-less
- [x] 2.4 Move `docker/login-action@v3` (line 99) to `@v4`
- [x] 2.5 Move `docker/metadata-action@v5` (line 106) to `@v6`, leaving
      `images`, `context: git`, and all four `tags` rows unchanged
- [x] 2.6 Move `docker/build-push-action@v6` (line 119) to `@v7`
- [x] 2.7 Move `softprops/action-gh-release@v2` (line 133) to `@v3` — the action
      the original scope wrongly exempted; see `design.md`
- [x] 2.8 Confirm the `on:` block, the `concurrency` group, the job-level `if:`,
      the version-detection shell step, and the release step's `if:` are all
      byte-identical to before

## 3. Specs

- [x] 3.1 Confirm the delta at `specs/release-publishing/spec.md` is a pure
      `## ADDED Requirements` block — no existing requirement is modified,
      because none of the eight change

## 4. Gates

- [x] 4.1 Run `composer test`, `composer stan`, and `composer cs`; all three must
      pass before any commit even though no PHP is touched
- [x] 4.2 Docs gate: re-read `docs/development-workflow.md` (Branches & tags,
      Promoting & releasing, Notes) and the README "Image tags" section, and
      confirm neither names an action or a version — record explicitly that no
      documentation edit is needed rather than inventing one
- [x] 4.3 Confirm `VERSION` is untouched, and that this change is deliberately
      mergeable without a version bump
- [x] 4.4 Run `openspec validate retire-node20-actions --strict`

## 5. Validation (after `/ship` pushes `dev`; not part of `/opsx:apply`)

- [x] 5.1 Confirm the push-triggered CI run on `dev` is green and its annotation
      no longer names `actions/checkout` — run 32505785594 on 34331ba: both jobs
      green, no annotations at all
- [x] 5.2 Dispatch `docker-publish.yml` from the Actions tab against the `dev`
      ref, and confirm the run starts — proving the edited file parses and its
      job-level `if:` evaluates — run 32506329819, green end to end
- [x] 5.3 In that run, read `steps.meta.outputs.tags` and confirm the `dev` and
      `sha-<short>` rows are correct and that no `latest` or version row appears
      — resolved to exactly `:dev` and `:sha-34331ba`; the `latest` and `2.11.1`
      rows both evaluated `enable=false`, and `context: git` still reported the
      checked-out revision
- [x] 5.4 Confirm the dispatch run's annotation names none of the six actions it
      ran, and record the release step's status as unknown rather than clean —
      it does not run on a `dev` dispatch — no annotations at all; the release
      step reported `skipped`, so `softprops/action-gh-release@v3` remains
      **unverified**, not clean
- [x] 5.5 Pull `bozodev/marquee:dev` and confirm its `sha-<short>` tag matches
      `dev` HEAD — pulled `:sha-34331ba`, manifest carries linux/amd64 +
      linux/arm64, `/health` returned `{"status":"ok","app":"marquee"}`, and the
      image revision label is `34331baa…`
- [ ] 5.6 Merge to `main` **without** a `VERSION` bump, then confirm the
      resulting `main` publish is green, refreshes `:latest`, and skips the
      release step
- [ ] 5.7 On the next release, confirm `softprops/action-gh-release@v3` creates
      the `v<version>` tag and the GitHub Release, and that the run's annotation
      names no action at all
