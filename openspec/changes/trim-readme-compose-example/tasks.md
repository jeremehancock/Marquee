## 1. Establish the facts before editing

- [x] 1.1 List every variable the store owns by reading `SettingKey::variable()`
      in [SettingKey.php](../../../src/Settings/SettingKey.php). This list is the
      grep target for the rest of the change: none of these may appear in the
      README's compose example.
- [x] 1.2 List every variable still read from the environment, with its default,
      from [AppConfig.php](../../../src/Config/AppConfig.php) and
      [bootstrap.php](../../../src/bootstrap.php) — `DATA_DIR`, `POSTERS_DIR`,
      `SESSION_DIR`, `DISPLAY_ERRORS`, `UPDATE_REPO`, `POSTER_SOURCE_URL` — plus
      `PLEX_SERVER_URL`, which is separate and stays for a security reason.
- [x] 1.3 List the retired variables from the superseded-variable code
      (`PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, `AUTH_BYPASS`), so the
      README's upgrade notes stay accurate.
- [x] 1.4 Read the Settings screen's actual field groups from
      [SettingsForm.php](../../../src/Settings/SettingsForm.php) and the settings
      template, so the README's Settings table names what is really there.

### Findings

**Store-owned (17, from `SettingKey`) — none may appear in the README compose
example:** `SITE_TITLE`, `SESSION_DURATION`, `PLEX_CONNECT_TIMEOUT`,
`PLEX_REQUEST_TIMEOUT`, `PLEX_REMOVE_OVERLAY_LABEL`, `AUTO_IMPORT_ENABLED`,
`AUTO_IMPORT_SCHEDULE`, `AUTO_IMPORT_MOVIES`, `AUTO_IMPORT_SHOWS`,
`AUTO_IMPORT_SEASONS`, `AUTO_IMPORT_COLLECTIONS`, `EXCLUDED_LIBRARIES`,
`IMAGES_PER_PAGE`, `MAX_FILE_SIZE`, `IGNORE_ARTICLES_IN_SORT`, `DEFAULT_SORT`,
`UPDATE_CHECK_ENABLED`.

**Environment-only, with defaults:** `DATA_DIR` `/config/data`, `POSTERS_DIR`
`/config/posters`, `SESSION_DIR` `/config/sessions`, `DISPLAY_ERRORS` `false`,
`UPDATE_REPO` `jeremehancock/Marquee`, `POSTER_SOURCE_URL`
`https://posteria.app`, plus `PLEX_SERVER_URL` (unset, required, security
control) and the LinuxServer container settings `PUID` / `PGID` (`911`) and `TZ`
(`Etc/UTC`).

**Retired:** `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, `AUTH_BYPASS`.

**Settings screen groups** — Presentation, Plex, Auto-import, Session, Updates,
Libraries. The README's existing group table is correct.

**Two corrections to design.md's drift table, found here:**

- The screen itself says "Changes take effect on the next page you load", and
  the auto-import section says it applies from the next scheduled run. The
  README already matches the app. Task 4.6 keeps that wording rather than
  changing it to "immediately"; only the auto-import call-out needs checking.
- The screen asks for session duration in **days** (1–365) and upload size in
  **megabytes** (1–100); the store and the seeding variables keep seconds and
  bytes. `docs/configuration.md` uses seconds/bytes, the README's Settings
  section says days/MB.

`DEFAULT_SORT` accepts four slugs, not two: `alphabetical`, `alphabetical_desc`,
`date_added`, `date_added_asc`. The README documents only two.

## 2. Write `docs/configuration.md`

- [x] 2.1 Create `docs/configuration.md`, opening with when this page applies:
      seeding happens once, on the first start that finds no
      `/config/data/settings.json`, and never again.
- [x] 2.2 Move the seed-once variable table from the README into it — every
      `SettingKey` variable with its description and its default from
      `SettingKey::default()`. Verify each default against the enum rather than
      copying the README's numbers. Split by the Settings screen's own six groups
      so the page and the screen can be read side by side; `DEFAULT_SORT` gains
      its two missing slugs.
- [x] 2.3 Document the environment-only variables that do not belong in the
      README: `DATA_DIR`, `POSTERS_DIR`, `DISPLAY_ERRORS`, `UPDATE_REPO`,
      `POSTER_SOURCE_URL`, noting the last three are development overrides.
- [x] 2.4 Explain what happens to a variable after seeding: it is ignored and
      reported on the Settings screen as relocated, and deleting it is safe.
      Note that recreating the container — not `docker compose restart` — is what
      clears a notice.
- [x] 2.5 Explain the clean-slate path: deleting `/config/data/settings.json`
      lets the next start seed from the environment again.

## 3. Trim the README's Quick start

- [x] 3.1 Replace the compose example with the trimmed one: image,
      `container_name`, port, `PUID` / `PGID` / `TZ`, `PLEX_SERVER_URL`, the
      `/config` volume, `restart`. Keep the inline comment saying there is no
      token to set because you sign in to Plex.
- [x] 3.2 Grep the new block against the task 1.1 list — zero matches, including
      inside comments. Verified by script: the only `yaml` block in `README.md`
      is clean.
- [x] 3.3 Check the surrounding prose still reads correctly now that the block is
      short, and that the `/config` sub-directory list below it is unchanged and
      still accurate. Added a "that is the whole file" line under the block so the
      brevity reads as deliberate.

## 4. Rewrite the README's Configuration section

- [x] 4.1 Lead with the Settings screen: this is where configuration lives, it
      needs no restart, and it is reached from the header.
- [x] 4.2 Replace the 23-row table with the short one — `PUID`, `PGID`, `TZ`,
      `PLEX_SERVER_URL`, `SESSION_DIR` — using defaults verified in task 1.2.
      Mention `DATA_DIR` / `POSTERS_DIR` in prose as overrides rather than giving
      them rows.
- [x] 4.3 Rewrite the exemption blockquote, which currently says "four things"
      and lists five. Replaced rather than patched: the short table now *is* the
      list, so the section states the rule once ("only what cannot be a setting")
      and the blockquote is reduced to the one thing an existing install needs to
      know. `UPDATE_REPO`, `DISPLAY_ERRORS`, and `POSTER_SOURCE_URL` moved to
      `docs/configuration.md` — they are not choices an install is offered.
- [x] 4.4 Add the one-sentence link to `docs/configuration.md` for pre-seeding a
      brand-new install, framed as optional.
- [x] 4.5 Keep and sharpen the paragraph for existing installs: variables already
      in your compose file are ignored, not broken, and the Settings screen lists
      which ones to delete.
- [x] 4.6 Correct the Settings sub-section's group table against task 1.4, and
      check the timing sentence. The group table and the timing sentence were both
      already correct — see the findings above; the only edit needed was naming the
      screen's units (whole days, MB), which the table did not mention.

## 5. Fix the stale instructions elsewhere in the README

- [x] 5.1 Excluded-libraries FAQ: replace "remove the library from
      `EXCLUDED_LIBRARIES`, restart" with un-ticking it on the Settings screen,
      no restart.
- [x] 5.2 Updating section: `UPDATE_CHECK_ENABLED` becomes the update-check
      toggle under Settings.
- [x] 5.3 Features list: confirm the "Settings in the app" and "Library
      exclusions" bullets match the trimmed story, and that no bullet still
      implies compose-file configuration. Both already correct; no edit needed.
- [x] 5.4 Sweep the whole README for every remaining `SettingKey` variable name
      and convert each to the setting it now is, keeping only the deliberate
      references in the upgrade notes and the link to `docs/configuration.md`.
      Exactly two were left (5.1 and 5.2); the README now names no store-owned
      variable anywhere.
- [x] 5.5 Leave the `PLEX_SERVER_URL`, `PLEX_TOKEN`, and `AUTH_*` sections alone
      apart from consistency fixes — they are correct and their reasoning is
      load-bearing. One precision fix: "the only setting that does not move into
      the Settings screen" read as an absolute the new Configuration table
      contradicts, since `SESSION_DIR` is in it. Reworded to distinguish
      "not a setting at all" from "a setting held back deliberately"; the
      rationale paragraphs are untouched.

## 6. Check the other docs for the same drift

- [x] 6.1 Check the compose snippet in
      [docs/development-workflow.md](../../../docs/development-workflow.md) for
      store-owned variables and trim it the same way if present. Already clean —
      the `:dev` instance snippet carries only `PUID` / `PGID` / `TZ` /
      `PLEX_SERVER_URL`.
- [x] 6.2 Grep `docs/` and `CLAUDE.md` for the store-owned variable names and
      fix any that present a variable as the way to configure something. Two
      files needed it: `development-workflow.md` told the reader to enable the
      update check with `UPDATE_CHECK_ENABLED=true`, and `testing.md` referred to
      `PLEX_REMOVE_OVERLAY_LABEL` as a variable in five places, including a
      results table keyed on `true`/`false`. Both now name the Settings toggle;
      the tester's own `EXPECT_LABEL_REMOVED` variable is unchanged, because it
      belongs to the script rather than to Marquee.
- [x] 6.3 Add `docs/configuration.md` to the README's Development section links
      and to `CLAUDE.md`'s "Detail lives elsewhere" table if it belongs there.
      Added to `CLAUDE.md`. Deliberately not added to the README's Development
      section — it is a user-facing reference, not a contributor doc, and it is
      already linked from §Configuration where a reader would look for it.

## 7. Verify

- [x] 7.1 Re-read the trimmed README end to end as a new user would, checking
      that the install path is coherent with no dangling references to the
      removed table. One fix: the compose comment called `PLEX_SERVER_URL` "the
      only setting that stays here", which the table below it contradicts.
- [x] 7.2 Verify every scenario in the change's `specs/settings/spec.md` against
      the edited files — particularly that the compose example produces no
      superseded notice. Checked by script: the README names none of the 17
      store-owned variables anywhere, so the example cannot produce one.
- [x] 7.3 Check every relative link in `README.md` and `docs/configuration.md`
      resolves. Extended to `development-workflow.md`, `testing.md`, and
      `CLAUDE.md`, including cross-file anchors. All resolve.
- [x] 7.4 Run `composer test`, `composer stan`, and `composer cs`. All three
      pass: 922 tests / 3006 assertions, PHPStan level 10 clean, CS clean.
- [x] 7.5 Run `openspec validate trim-readme-compose-example --strict`. Valid.
- [x] 7.6 Note any drift found during the audit that is a code bug rather than a
      doc bug, and report it for a separate change rather than fixing it here.
      None found — see below.

## 8. Unplanned: the guard test the audit tripped

Discovered during 7.4, not anticipated when this change was proposed. Both are
recorded as spec deltas under `application-shell` rather than fixed silently.

- [x] 8.1 `ConfigurationSurfaceTest` asserts `SITE_TITLE` is present in the
      README as its positive control — the assertion that stops the absence
      checks passing against a README whose configuration section went missing.
      This change relocates `SITE_TITLE`, so the control had to move to a
      variable that cannot become a setting: `PLEX_SERVER_URL`. Comment on the
      constant records why, so the next person does not put `SITE_TITLE` back.
- [x] 8.2 The same test caught `DATA_DIR` / `POSTERS_DIR` being added to the
      README, which the `application-shell` spec forbids: the `/config` layout is
      presented as fixed so that "back up `/config`" is unconditional. Task 4.2's
      instruction to mention them in prose was wrong and is reverted — the spec
      decision stands and is unaffected by this change.
- [x] 8.3 The same reasoning applies to `docs/configuration.md`, which the test
      does not read. Its "variables that are not settings" table was dropped and
      replaced with a pointer to
      `docs/development-workflow.md#settings-that-stay-in-the-environment`, which
      already documents all six correctly. The `application-shell` delta extends
      the exclusion to every user-facing page so a second page cannot overturn
      the decision while the README's test still passes.
- [x] 8.4 `docs/development-workflow.md` presents its list of environment-only
      variables as exhaustive ("six variables") without mentioning
      `PLEX_SERVER_URL`, which is a seventh for a different reason. Added a
      pointer to the README rather than a seventh row, since it is a security
      control rather than a structural exception.
