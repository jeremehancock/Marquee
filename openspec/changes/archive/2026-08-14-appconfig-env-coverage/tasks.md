## 1. Prepare the test class

- [x] 1.1 Rewrite the class docblock of `tests/Unit/Config/AppConfigTest.php` so
      it explains why every default is pinned — a default nothing asserts can be
      moved by a refactor with no test failing — rather than describing sessions
      alone. Keep the `session.save_path` incident as the worked example of that
      failure.
- [x] 1.2 Widen `tearDown()` to unset all five variables (`SITE_TITLE`,
      `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`, `DISPLAY_ERRORS`), so no test can
      leak a value into a later test in the same process.

## 2. Assert every default

- [x] 2.1 Add a test asserting `SITE_TITLE` defaults to the literal `'Marquee'`
      when unset — the literal, not `AppConfig::APP_NAME`, so a change to the
      constant is visible.
- [x] 2.2 Add a test asserting `DATA_DIR` defaults to `/config/data` when unset.
- [x] 2.3 Add a test asserting `POSTERS_DIR` defaults to `/config/posters` when
      unset.
- [x] 2.4 Add a test asserting `DISPLAY_ERRORS` defaults to `false` when unset,
      with a note that the default is what keeps stack traces out of a reachable
      install.
- [x] 2.5 Keep the existing `SESSION_DIR` default test as-is; confirm it still
      unsets its own variable before reading, so it does not depend on the
      ambient environment.

## 3. Assert trailing-separator trimming for every directory

- [x] 3.1 Add a test asserting a trailing `/` is trimmed from `DATA_DIR`.
- [x] 3.2 Add a test asserting a trailing `/` is trimmed from `POSTERS_DIR`.
- [x] 3.3 Confirm the existing `SESSION_DIR` trimming test still reads as one of
      three siblings rather than a special case, and adjust its docblock if it
      claims uniqueness.
- [x] 3.4 Give each test its own name identifying the setting it guards — no
      data provider over the three directories, so a failure names the setting
      that regressed.

## 4. Documentation

- [x] 4.1 Document `DISPLAY_ERRORS` in `docs/development-workflow.md` near
      *Sanity-check the toolchain*: what it does (renders the exception instead
      of the generic error page), its default (`false`), and that it must stay
      off in any reachable install because stack traces disclose paths and
      configuration.
- [x] 4.2 Confirm `README.md` needs no edit — `DATA_DIR` and `POSTERS_DIR` stay
      out by decision, and the `/config` section already describes the layout as
      fixed. Record the check explicitly rather than inventing an edit; group 5
      then makes the check permanent.
- [x] 4.3 Confirm `CLAUDE.md` and the rest of `docs/` are unaffected; nothing
      user-facing changed.

## 5. Pin the documentation decision

- [x] 5.1 Create `tests/Unit/Config/ConfigurationSurfaceTest.php`, reading
      `README.md` and `docs/development-workflow.md` with
      `dirname(__DIR__, 3)`, matching the convention in `tests/Unit/Asset/`.
      Assert each file is readable before asserting on its contents.
- [x] 5.2 Assert `DISPLAY_ERRORS` appears in `docs/development-workflow.md`.
- [x] 5.3 Assert `DATA_DIR` and `POSTERS_DIR` appear nowhere in `README.md`,
      commented compose examples included — a commented `# DATA_DIR:` advertises
      the setting as effectively as a table row. Give the failure message the
      reason, pointing at the spec decision rather than just reporting a match.
- [x] 5.4 Assert `SITE_TITLE` and `SESSION_DIR` do appear in `README.md` — the
      positive control, without which the absence assertions also pass against an
      empty or gutted README.
- [x] 5.5 Match on the variable-name token only, never on table syntax or
      surrounding prose, so reformatting the table cannot break the test.
- [x] 5.6 Write a class docblock explaining why documentation is under test here:
      the decision to withhold `DATA_DIR` and `POSTERS_DIR` is a spec decision,
      and this is what makes reversing it deliberate.

## 6. Verify

- [x] 6.1 Run `composer test` — all green, including the widened `AppConfigTest`
      and the new `ConfigurationSurfaceTest`.
- [x] 6.2 Sanity-check the new test actually bites: temporarily add `DATA_DIR` to
      the README, confirm the test fails, then revert. An absence assertion that
      has never failed is not yet known to work.
- [x] 6.3 Run `composer stan` (level 10) and `composer cs`; apply
      `composer cs:fix` if the formatter objects.
- [x] 6.4 Confirm `src/Config/AppConfig.php` is untouched in the diff — no
      default changed, no variable renamed, no behaviour altered.
- [x] 6.5 Run `openspec validate appconfig-env-coverage --strict`.
