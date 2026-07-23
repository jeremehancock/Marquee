## 1. Triggers and permissions

- [x] 1.1 `.github/workflows/docker-publish.yml`: trigger on pushes to `main`
      and `dev`; remove the `tags: ['v*']` trigger. Keep `workflow_dispatch`.
- [x] 1.2 Raise `permissions` to `contents: write` (needed to create the tag and
      the Release).
- [x] 1.3 `actions/checkout@v4` with `fetch-depth: 0` so tag history is present.
- [x] 1.4 Update the workflow's header comment to describe the VERSION-driven
      flow.

## 2. Tag derivation

- [x] 2.1 Read `VERSION` into a step output, stripping whitespace.
- [x] 2.2 Compute a `new release` flag: on `main` **and** no `v<version>` tag
      exists.
- [x] 2.3 Replace the `type=semver` rules with:
      `latest` on the default branch, `dev` on `dev`, `<version>` when the
      release is new, and `sha-<short>` always.

## 3. Tag and Release creation

- [x] 3.1 After a successful image push, when the release is new: create the
      `v<version>` git tag and a GitHub Release from it, with auto-generated
      notes.
- [x] 3.2 Confirm the step is skipped on `dev` and on `main` pushes whose
      `VERSION` is already released.

## 4. Related fixes

- [x] 4.1 `src/bootstrap.php`: change the `UPDATE_REPO` default from
      `jeremehancock/Posteria-II` to `jeremehancock/Marquee`.
- [x] 4.2 `docs/development-workflow.md`: drop the note about the `v*` trigger
      not re-triggering the workflow — that trigger no longer exists.
- [x] 4.3 `openspec/config.yaml`: add `release-publishing` to the capability map
      (build/release behavior, not an application capability).

## 5. Verify

- [x] 5.1 `actionlint` (or equivalent) reports no errors in the workflow.
      actionlint is not installed on the dev machine. Verified instead that the
      YAML parses and that triggers (`push`, `workflow_dispatch`) and
      `permissions: contents: write` resolve as intended.
- [x] 5.2 Push to `dev`: `:dev` and `:sha-<short>` appear on Docker Hub; no tag
      or Release is created.
      Verified live: pushing `1af8db5` to `dev` published `:dev` and
      `:sha-1af8db5` (2026-07-23T01:29Z). Tags remained `v0.1.0`/`v0.1.1` and
      the release count stayed at 2.
- [ ] 5.3 Merge to `main` without changing `VERSION`: `:latest` refreshes; no
      duplicate tag or Release.
      Not yet exercised live — every merge to `main` so far has carried a new
      `VERSION`. The logic was simulated against the repo's real tags
      (main/0.1.1 and main/0.1.0 both -> no release), but the "already released"
      branch has not run in CI. Low risk; confirm on the next no-op merge.
- [x] 5.4 Merge to `main` with a bumped `VERSION`: `:latest`, `:<version>`, the
      `v<version>` tag, and the GitHub Release all appear from one run.
      Verified live, twice. For v0.1.1: tag created 01:15:00Z, `:latest` pushed
      01:16:02Z, `:0.1.1` pushed 01:16:04Z, Release published 01:16:14Z by
      `github-actions[bot]` with auto-generated notes — confirming both the
      one-run behavior and that the Release follows the image push.
- [x] 5.5 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green (for the
      `bootstrap.php` change).
      PHPStan and PHP-CS-Fixer green. PHPUnit could not run on the dev machine
      (missing ext-gd, ext-intl, ext-iconv, pdo_sqlite); CI covers it. No PHP
      was changed by this apply — `bootstrap.php` was already correct.
- [x] 5.6 `openspec validate release-automation` passes.
