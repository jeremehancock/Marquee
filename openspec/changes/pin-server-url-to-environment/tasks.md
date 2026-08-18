## 1. Remove the claim

- [x] 1.1 Run `git revert --no-commit b2f0b12` and inspect the staged diff before
      going further — confirm it reached the incidental edits in
      `src/bootstrap.php`, `src/Auth/AuthMiddleware.php`,
      `src/Auth/PlexConnectionMiddleware.php`, `src/Routes.php`, and
      `tests/AppTestCase.php`.
- [x] 1.2 Confirm the four `src/Auth/Claim/` classes, `src/Auth/ClaimMiddleware.php`,
      `src/Controller/ClaimController.php`, `templates/claim.html.twig`, and
      `tests/Functional/ClaimTest.php` are gone.
- [x] 1.3 Confirm `src/Plex/Connection/PlexConnectionStore.php` has no
      `KEY_CLAIMED_AT`, `claimedAt()`, `isClaimed()`, or `markClaimed()`, and
      that the `clearToken()` docblock no longer discusses the claim.
- [x] 1.4 Confirm the `location = /claim` block is gone from
      `docker/root/defaults/nginx/site-confs/default.conf.sample`.
- [x] 1.5 Delete `openspec/changes/add-first-run-claim/` outright.

## 2. Re-apply what the revert should not have taken

- [x] 2.1 Restore the `docs/docker.md` s6 verification block if the revert
      removed it — it came from phase 3 and is unrelated to the claim.
- [x] 2.2 Drop only the "first-run claim needs an empty volume" section that
      phase 4 appended to `docs/docker.md`.

## 3. Move the server address to the environment

- [x] 3.1 Delete the `PlexServerUrl` case from `src/Settings/SettingKey.php`,
      along with its `variable()` and `default()` arms.
- [x] 3.2 Update the `SettingKey` class docblock so `PLEX_SERVER_URL` is listed
      among the variables that stay on `Env`, with its reason: it is a
      host-access assertion, not a preference.
- [x] 3.3 Change `PlexConfig::resolve()` to read the address via
      `Env::str('PLEX_SERVER_URL', '')` instead of
      `$settings->string(SettingKey::PlexServerUrl)`, keeping the existing
      trim-and-parse handling that turns an unusable address into an empty one.
- [x] 3.4 Rewrite the `PlexConfig` class docblock — it currently says the address
      "is seeded from `PLEX_SERVER_URL` like every other setting", which becomes
      the opposite of the truth.
- [x] 3.5 Remove `SettingsForm::FIELD_SERVER_URL`, its validation branch, and its
      entries in the form-state and submission arrays.
- [x] 3.6 Remove the server address field from `templates/settings.html.twig`.
- [x] 3.7 Verify no remaining caller resolves the address from the settings
      store: `grep -rn "PlexServerUrl\|plex_server_url" src/ tests/ templates/`.

## 4. Tests

- [x] 4.1 Remove `testTheScreenOffersTheServerAddress`,
      `testTheServerAddressCanBeChanged`, and `testAnUnusableServerAddressIsRefused`
      from `SettingsScreenTest`.
- [x] 4.2 Drop the server address field from the `form()` and `submission()`
      helpers in **both** `SettingsScreenTest` and `SettingsFormTest`, or every
      save test fails at once.
- [x] 4.3 Replace `AppTestCase::makeUnclaimedApp()` with a helper for the
      no-address state — an app built with `PLEX_SERVER_URL` unset — and remove
      the claim-file unlinks from `makeApp()`.
- [x] 4.4 Add a test that the address comes from the environment and that the
      settings store holds no `plex_server_url` key after seeding.
- [x] 4.5 Add a test that changing `PLEX_SERVER_URL` takes effect on an
      already-seeded store. Build a second container directly via
      `createApp(buildContainer([...]))` — calling `makeApp()` again deletes
      `settings.json` and would not prove anything. See
      `SettingsScreenTest::nextRequest()`.
- [x] 4.6 Add a test that `PLEX_SERVER_URL` is reported neither as retired nor as
      relocated by `SupersededEnvironment` while it is set.
- [x] 4.7 Add a test that a settings submission carrying a server address field
      writes no address to the store.
- [x] 4.8 Add a test that an unparseable `PLEX_SERVER_URL` resolves to no address
      rather than raising. *(Already covered by
      `PlexConfigResolutionTest::testAnUnparseableServerUrlIsTreatedAsNoAddress`,
      over several malformed addresses; no duplicate added.)*

## 5. Docs

- [x] 5.1 `README.md`: restore `PLEX_SERVER_URL` to the compose example and say
      it is the one thing you must set; everything else is optional and
      seed-only.
- [x] 5.2 `README.md`: remove the "Get your claim code" block and the "Why a
      code?" callout from Quick start.
- [x] 5.3 `README.md`: remove the claim feature bullet ("Nothing to configure
      before you start").
- [x] 5.4 `README.md`: restore the `### Connecting to Plex` numbered steps so
      they start from setting `PLEX_SERVER_URL`.
- [x] 5.5 `README.md`: remove the "Can I hand this install to someone else, or
      start its setup over?" FAQ entry **including its network warning** — that
      warning only made sense because the claim existed.
- [x] 5.6 `README.md`: confirm the Settings section lists no Plex server address
      row, since there is no field.
- [x] 5.7 `docs/development-workflow.md`: put `PLEX_SERVER_URL` back in the dev
      compose example, without claim commentary.
- [x] 5.8 `docs/testing.md`: restore the orphans troubleshooting line to mention
      setting `PLEX_SERVER_URL`.
- [x] 5.9 `CLAUDE.md`: remove the `claimed_at` bullet and add `PLEX_SERVER_URL`
      to the list of structural environment exceptions.
- [x] 5.10 `openspec/config.yaml`: add `PLEX_SERVER_URL` to the same exception
      list in the project context block.

## 6. Plan file

- [x] 6.1 Rewrite phase 4 in `openspec/settings-in-app-plan.md` as abandoned:
      what was built, that it was validated on `:dev`, and why it was rolled
      back.
- [x] 6.2 Record in that entry that the *initial* address coming from the
      environment is what keeps ownership verification meaningful, so nobody
      later finishes the job by removing it.

## 7. Gates

- [x] 7.1 `composer test` passes.
- [x] 7.2 `composer stan` passes at level 10.
- [x] 7.3 `composer cs` passes.
- [x] 7.4 `openspec validate pin-server-url-to-environment --strict` passes, and
      `openspec validate --specs --strict` is unaffected.

## 8. Validate on `:dev` (user-run, before archiving)

- [ ] 8.1 An upgrade from the existing install still works, despite the stale
      `plex_server_url` key left in `settings.json`.
- [ ] 8.2 A fresh install with `PLEX_SERVER_URL` set reaches sign-in with no
      wizard and no claim prompt.
- [ ] 8.3 Changing `PLEX_SERVER_URL` in compose and recreating the container
      changes the address in effect.
