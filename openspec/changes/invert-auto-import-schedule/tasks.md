## 1. The interval stops being inert

- [x] 1.1 Add an `AutoImportInterval` enum (or equivalent) holding the five
      choices, the hours each spans, and its label; make it the one place the set
      is defined, so the settings select and the slot arithmetic cannot disagree
- [x] 1.2 Resolve the interval in `AutoImportConfig` from
      `SettingKey::AutoImportSchedule`, falling back to daily for an unrecognised
      value, and delete the docblock paragraph saying nothing reads it

## 2. Schedule state

- [x] 2.1 Add an `auto_import_runs` table (single row, `CHECK (id = 1)`, as
      `plex_server` does) holding the last completed slot, created by the same
      idempotent migration path
- [x] 2.2 Add the repository: read the last completed slot, record a slot as
      completed, take and release the run guard, and expose when the guard was
      taken so a stale one can be judged
- [x] 2.3 Store the slot itself, not the finish time — the scheduler asks which
      slot is done

## 3. Deciding whether a run is due

- [x] 3.1 Add an `AutoImportSchedule` service: given the interval, the clock, and
      the last completed slot, answer which slot the current moment falls in and
      whether it is due
- [x] 3.2 Anchor slots at midnight local time so the five choices keep the exact
      firing times of the cron expressions being deleted
- [x] 3.3 Treat a slot later than the last completed one as due, which is what
      makes a missed slot catch up — one import however many were missed
- [x] 3.4 Record the slot only on completion, so an interrupted run is retried

## 4. The run guard

- [x] 4.1 Take the guard before importing; release it on completion and on
      failure alike
- [x] 4.2 Treat a guard older than a generous bound as abandoned and proceed, so
      a killed process cannot disable auto-import permanently
- [x] 4.3 Log when a tick is skipped because a run is in progress

## 5. The CLI decides

- [x] 5.1 Have `AutoImportService::run()` consult the schedule before its existing
      enabled / configured / media-type checks, and exit quietly when not due
- [x] 5.2 Keep those existing checks exactly as they are — the schedule sits in
      front of them rather than replacing them
- [x] 5.3 Make a not-due tick exit 0 with no error output, since most ticks now do
      nothing

## 6. Container: the tick

- [x] 6.1 Strip the scheduling block from
      `docker/root/etc/s6-overlay/s6-rc.d/init-marquee-config/run`, keeping the
      directory creation, ownership, and permissions it also does
- [x] 6.2 Write `0 * * * * /app/auto-import.sh` to `/etc/crontabs/abc`
      unconditionally
- [x] 6.3 Reduce `svc-cron/run` to a single unconditional `exec busybox crond`,
      removing the empty-file check and the `sleep infinity` branch
- [x] 6.4 **Build the image locally and smoke-test `/health` before pushing** —
      CI only exercises the image after a push, and an s6 service that fails does
      so at boot. See `docs/docker.md`
- [x] 6.5 Verify in the running container that `crond` is up with auto-import
      disabled — the bug this change fixes

## 7. Settings screen

- [x] 7.1 Add the auto-import fields to `SettingsForm`: enabled, four media-type
      toggles, and the interval validated against the enum
- [x] 7.2 Add the Auto-import section to `settings.html.twig` using the existing
      form macros, placed after the Plex section
- [x] 7.3 Word the section so it says these apply on the next scheduled run, not
      on the next page load like the rest of the screen
- [x] 7.4 Remove the test asserting the screen offers no auto-import control, and
      replace it with one asserting it does

## 8. Tests

- [x] 8.1 Slot arithmetic per interval: which slots exist, and that daily lands at
      midnight
- [x] 8.2 Slot arithmetic in a non-UTC timezone, since `crond` and PHP have to
      agree about local midnight
- [x] 8.3 A tick outside a slot runs nothing and exits successfully
- [x] 8.4 A completed slot does not run twice within the same slot
- [x] 8.5 A missed slot runs at the next tick, and several missed slots still run
      exactly one import
- [x] 8.6 An interrupted run leaves its slot unrecorded and is retried
- [x] 8.7 The guard blocks a concurrent tick, is released on failure, and a stale
      guard does not block forever
- [x] 8.8 Losing the schedule state costs one import and raises no error
- [x] 8.9 Enabled / not-configured / no-media-types checks still no-op, unchanged
- [x] 8.10 Saving auto-import settings stores them and the next tick observes them
- [x] 8.11 The settings screen offers the auto-import controls and still offers no
      Plex server address field

## 9. Docs and gates

- [x] 9.1 README: the auto-import rows seed a new install only; add auto-import to
      the Settings section; drop "the auto-import schedule is not on this screen
      yet"; update the numbered step that tells users to set
      `AUTO_IMPORT_ENABLED`
- [x] 9.2 `docs/docker.md` if the s6 changes alter the smoke test, and note that
      `crond` now always runs
- [x] 9.3 Note in the docs that deleting the database now costs one extra import
- [x] 9.4 `composer test`, `composer stan`, `composer cs` all pass
- [x] 9.5 Tick phase 3 in `openspec/settings-in-app-plan.md` and record anything
      phase 4 would otherwise rediscover
