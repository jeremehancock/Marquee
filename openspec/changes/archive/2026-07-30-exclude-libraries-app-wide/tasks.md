## 1. Shared exclusion config

- [x] 1.1 Add `src/Config/LibraryExclusions.php`: immutable value object holding
      `list<string> $names`, with `fromEnv()` reading `EXCLUDED_LIBRARIES` via
      `Env::list()` and `isExcluded(string $libraryTitle): bool` matching on the
      library name, case-insensitively and trimmed (behavior moved verbatim from
      `AutoImportConfig::isExcluded()`), plus a way to tell whether any
      exclusion is configured.
- [x] 1.2 Remove `excludedLibraries` and `isExcluded()` from
      `src/Config/AutoImportConfig.php`, including the constructor parameter and
      the `EXCLUDED_LIBRARIES` read in `fromEnv()`.
- [x] 1.3 Register `LibraryExclusions::class => LibraryExclusions::fromEnv()` in
      the container in `src/bootstrap.php`.

## 2. Drop excluded libraries at the boundary

- [x] 2.1 Inject `LibraryExclusions` into `src/Plex/HttpPlexClient.php` (wired in
      `src/bootstrap.php`) and omit excluded libraries from `libraries()`.
- [x] 2.2 Document the contract on `PlexClient::libraries()` in
      `src/Plex/PlexClient.php`: excluded libraries are never returned, so
      callers need no exclusion check of their own.
- [x] 2.3 Remove the now-redundant `isExcluded()` filter from
      `src/Plex/Import/AutoImportService.php` and switch it to the slimmed
      `AutoImportConfig`, keeping its "no libraries to import" log path.
- [x] 2.4 Confirm `src/Plex/Import/ImportService.php` and
      `src/Plex/Orphan/OrphanService.php` need no edit — both inherit the filter
      from the client — and note it rather than changing them.

## 3. Import from Plex screen

- [x] 3.1 Inject `LibraryExclusions` into `PlexImportController::show()` and pass
      the template a flag for "exclusions are configured".
- [x] 3.2 Update `templates/plex.html.twig` so the empty state says libraries
      listed in `EXCLUDED_LIBRARIES` are hidden when exclusions are configured,
      keeping today's "no movie or TV libraries were found" wording otherwise.

## 4. Orphans screen copy

- [x] 4.1 Update the orphan definition in `templates/orphans.html.twig` to name
      both causes: the media no longer exists in Plex, or the poster's library is
      now excluded.

## 5. Tests

- [x] 5.1 Add `tests/Unit/Config/LibraryExclusionsTest.php`: exact match, case
      difference, surrounding whitespace on both sides, empty/unset env, and an
      entry matching no library name (e.g. a section key) excluding nothing.
- [x] 5.2 Extend `tests/Unit/Plex/HttpPlexClientTest.php`: `libraries()` omits an
      excluded library and keeps the rest.
- [x] 5.3 Update `tests/Unit/Config/AutoImportConfigTest.php` and
      `tests/Unit/Plex/AutoImportServiceTest.php` for the new constructor
      signature, keeping auto-import's excluded-library coverage now that the
      filtering happens in the client.
- [x] 5.4 Extend `tests/Unit/Plex/ImportServiceTest.php`: posting an excluded
      library's section key imports nothing, and a mixed selection imports only
      the non-excluded library.
- [x] 5.5 Extend `tests/Unit/Plex/OrphanServiceTest.php`: a poster imported from
      a since-excluded library is listed as an orphan and is not deleted until
      the user deletes it.
- [x] 5.6 Extend `tests/Functional/PlexImportTest.php`: the Import from Plex
      screen omits an excluded library, and shows the exclusions message when
      every library is excluded.

## 6. Docs and gates

- [x] 6.1 Update `README.md`: describe `EXCLUDED_LIBRARIES` in the configuration
      table as app-wide (hidden everywhere in the UI and skipped by every
      import, not just auto-import) and state that entries are library names as
      shown in Plex, never section keys; check the compose example comment reads
      the same way.
- [x] 6.2 Add a README FAQ entry for excluding a library that was already
      imported: its posters become orphans, appear on the Orphans screen, are
      never deleted automatically, and come back by removing the exclusion and
      re-importing.
- [x] 6.3 Check `docs/` and `CLAUDE.md` for statements tying
      `EXCLUDED_LIBRARIES` to auto-import; fix any found, or state explicitly
      that none needed changing.
- [x] 6.4 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass before committing.
