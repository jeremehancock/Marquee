## Why

Excluding a library today only stops the scheduled import. The library still
appears on the **Import from Plex** screen, so a manual import happily pulls in
the posters the user asked Marquee to leave alone. An excluded library should
not exist as far as Marquee is concerned — not offered anywhere, not imported by
any path, and not treated as a live source for posters already on disk.

## What Changes

- Excluded libraries are dropped at the boundary: the Plex client never reports
  them, so no screen, service, or scheduled run can see one. The Import from
  Plex picker no longer lists them, and an import skips one even if its section
  key is posted directly to the import endpoint.
- Posters imported before a library was excluded become **orphans**, listed on
  the Orphans screen for the user to delete. Excluding a library is how you tell
  Marquee that library is gone; the orphans flow is how you clear what it left
  behind. Marquee never deletes them on its own.
- The Orphans screen copy stops claiming an orphan is only a poster whose media
  no longer exists in Plex — an excluded library produces orphans too.
- When every library the server reports is excluded, the Import from Plex screen
  says so instead of claiming no libraries were found on the server.
- Exclusion matching is pinned down as **by library name** — the name shown in
  Plex, compared case-insensitively and ignoring surrounding whitespace, never by
  section key or id. That is today's behavior; this change makes it a normative,
  tested requirement and says so in the docs, so `EXCLUDED_LIBRARIES` cannot be
  misread as taking ids.
- `EXCLUDED_LIBRARIES` stops being an auto-import setting and becomes an app-wide
  one, with a README FAQ entry covering the exclude-after-import case.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `plex-import`: new requirements — excluded libraries are omitted from the
  library picker on the Import from Plex screen, and an import skips an excluded
  library even when its section key is submitted.
- `auto-import`: the excluded-libraries requirement is restated as the app-wide
  rule every import path honors, and pins matching to the library name (case-
  and whitespace-insensitive).
- `orphan-detection`: posters whose library is excluded are orphans, and the
  page copy must describe that second cause.

## Impact

- `src/Config/AutoImportConfig.php` — the exclusion list and `isExcluded()` move
  out to a shared config object; auto-import keeps its type toggles.
- New `src/Config/LibraryExclusions.php`, registered in the container and read
  once from `EXCLUDED_LIBRARIES` at bootstrap.
- `src/Plex/HttpPlexClient.php` — `libraries()` filters excluded libraries out;
  `src/Plex/PlexClient.php` documents that contract.
- `src/Plex/Import/AutoImportService.php` — its now-redundant exclusion filter is
  removed.
- `src/Controller/PlexImportController.php` and `templates/plex.html.twig` — the
  all-excluded empty state.
- `templates/orphans.html.twig` — orphan definition copy.
- Unchanged by design: `src/Plex/Import/ImportService.php` and
  `src/Plex/Orphan/OrphanService.php` both inherit the filter from the client.
- Docs: `README.md` configuration table, the compose example, and a new FAQ
  entry.
