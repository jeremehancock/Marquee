## 1. README FAQ entry

- [x] 1.1 Add the FAQ entry "Something's wrong with my posters. Can I start
      over?" to `README.md`, placed immediately after the existing "I excluded a
      library I'd already imported. What happens to its posters?" answer so it
      inherits the orphan context established above it.
- [x] 1.2 Order the answer remedy-first: re-import with **Re-download unchanged
      posters** checked, then **Orphans**, then the full reset last.
- [x] 1.3 Write the reset steps as stop the container → remove `posters/` and
      `data/marquee.sqlite*` from the `/config` volume → start it again → import.
      Use the `*` glob so SQLite's `-wal` and `-shm` sidecars go with the
      database file, and keep "stop the container first" explicit.
- [x] 1.4 State the recovery boundary: posters already sent to Plex come back on
      the next import because Plex holds and locks them; art that only ever
      existed in Marquee does not.
- [x] 1.5 Match the surrounding FAQ voice — bold question line, prose answer, no
      nested headings — and keep the existing line wrapping.

## 2. Documentation sweep

- [x] 2.1 Confirm `docs/docker.md` needs no change: it documents building and
      smoke-testing the image, not the `/config` volume's contents.
- [x] 2.2 Confirm the README's `/config` volume description ("Quick start") and
      the "Back up your `/config` directory" line under Security considerations
      stay accurate alongside the new entry, and fix them if the new wording
      makes either misleading.
- [x] 2.3 Confirm `CLAUDE.md` and `docs/development-workflow.md` are unaffected;
      state so explicitly rather than inventing edits.

## 3. Regression test for the documented reset

- [x] 3.1 Add a test asserting that opening the database at a path that does not
      exist creates it and applies the schema, so a removed database file returns
      Marquee to a working first-run state (`tests/Unit/Database/DatabaseTest.php`).
- [x] 3.2 Add a test asserting that listing a category whose directory is absent
      returns an empty list rather than failing, and that storing a poster
      recreates the directory (`tests/Unit/Poster/` — the filesystem storage).
- [x] 3.3 Name both tests after the invariant they protect, not the method they
      call, so a future change that breaks the documented reset fails with a
      readable reason.

## 4. Gates

- [x] 4.1 `composer test`
- [x] 4.2 `composer stan`
- [x] 4.3 `composer cs` (`composer cs:fix` if it reports anything)
- [x] 4.4 `openspec validate document-starting-over --strict`
