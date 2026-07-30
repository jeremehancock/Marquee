## Why

When a user's poster library looks wrong, they have no idea which of Marquee's
existing tools fixes it — so they reach for the biggest hammer they can imagine,
which is usually deleting the `/config` volume by hand. Almost every case is
already covered by a force re-import or by Orphans, and the few that aren't need
a clean slate that is safe to take only if you know which files to remove.
Nothing in the documentation says any of this.

## What Changes

- Add a **FAQ entry** to `README.md`: "Something's wrong with my posters. Can I
  start over?"
  - Leads with the two existing in-app remedies — re-import with **Re-download
    unchanged posters** checked, and **Orphans** — because those solve the
    common cases without destroying anything.
  - Ends with full-reset instructions as a footnote: stop the container, remove
    `posters/` and `data/marquee.sqlite*` from the `/config` volume, start it
    again, import. The `*` glob covers SQLite's `-wal` and `-shm` sidecar files,
    which are the detail a hand-written reset misses and which leave a
    half-restored database behind.
  - States the recovery boundary plainly: posters already sent to Plex come back
    on the next import, because Plex holds and locks them; art that only ever
    existed in Marquee does not.
- Add one requirement to `application-shell` recording the invariant the new FAQ
  depends on: everything Marquee persists is recreatable, so removing it returns
  the app to a first-run state rather than a broken one. This is already true of
  the code; specifying it stops a future change from quietly making the
  documented reset unsafe.

Explicitly **not** in scope: an in-app Reset button. The old Posteria app needed
one because it had no item→file mapping, so a wrong poster on disk could not be
corrected in place. Marquee already ships the targeted fix (`Force a full
re-import`) and a bulk cleanup path (`orphan-detection`), which leaves a
permanent, irreversible, one-click destroy button covering only rare cases like
replacing the Plex server.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: adds a requirement that Marquee's persisted state
  (poster files and the SQLite database) is recreatable, and that removing it
  returns the app to a first-run state.

## Impact

- `README.md` — one new FAQ entry, placed after the existing orphan questions so
  the "what is orphan detection" answer sets it up.
- No code, routes, templates, or migrations change. The specification added
  describes behavior the current implementation already has: `Database::migrate`
  is idempotent and runs on every boot, and `FilesystemPosterStorage` treats a
  missing category directory as empty and recreates it on write.
- Tests: a regression test that a first boot against an absent database and an
  absent posters directory succeeds, so the documented reset stays true.
