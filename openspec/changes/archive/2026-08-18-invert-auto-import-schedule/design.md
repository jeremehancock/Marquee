## Context

Scheduling is currently split across two s6 scripts and one crontab:

- `init-marquee-config/run` maps `AUTO_IMPORT_SCHEDULE` through a `case`
  statement to a cron expression and writes `/etc/crontabs/abc` — but only when
  `AUTO_IMPORT_ENABLED` is true. Otherwise it deletes the file.
- `svc-cron/run` execs `busybox crond` only when that file is non-empty, and
  `sleep infinity` otherwise, so the longrun service does not restart-loop.
- `/app/auto-import.sh` runs `bin/auto-import.php`, which asks
  `AutoImportService::run()` to do one import.

Both variables were moved into the settings store in phase 1, and
`AutoImportConfig` already resolves the toggles from it. The interval is stored
and seeded but nothing reads it — `AutoImportConfig`'s docblock says so in as
many words.

The CLI already builds the same container the web process does, so it already
reads the settings store and the connection store from disk. Nothing new is
needed to let it read a schedule.

## Goals / Non-Goals

**Goals:**

- The schedule and the toggles are changeable from the browser, taking effect
  without recreating the container.
- `crond` runs unconditionally, so no boot-time state can leave an install unable
  to schedule anything.
- The five existing interval choices keep the exact firing times they have today.
- A run cannot overlap itself.

**Non-Goals:**

- New interval choices. The set stays 1h/3h/6h/12h/24h; changing it is a separate
  question from where the schedule lives.
- Changing what an import does. This is entirely about when.
- Per-library or per-type schedules.
- A visible run history. Last-run state exists to answer "is a run due", not to be
  a report.

## Decisions

### Slots, not elapsed time

A run is due when the current hour begins a slot for the configured interval and
that slot has not been run. Slots are anchored to midnight in the container's
local time: `24h` → 00:00; `12h` → 00:00, 12:00; `6h` → 00:00, 06:00, 12:00,
18:00; and so on. These are precisely the cron expressions being deleted.

*Alternative rejected:* due when `now - lastRun >= interval`. It drifts. A daily
import that finishes at 00:05 is next due at 00:05 tomorrow, the tick at 00:00
misses it, and the run lands at 01:00 — an hour later every day until it wraps.
Users configured "daily", and the current crontab gives them midnight; elapsed
time would quietly stop meaning that.

### The tick is hourly

Every offered interval is a whole number of hours dividing the day evenly, so an
hourly tick can hit every slot exactly. A finer tick would buy nothing and wake
PHP more often for a job that mostly exits.

`0 * * * *` is the whole crontab, written unconditionally at boot.

### A missed slot is caught up, not skipped

If the container is down at midnight and starts at 03:00, the 24h slot for today
has not been run, so the next tick runs it. This is new behavior — the current
crontab simply misses it and waits a day — and it is the behavior a user expects
from "import daily".

It follows from comparing against the last slot run rather than against the clock
alone, so it costs nothing to have. The catch-up runs once: after it, today's
slot is recorded.

### Last-run state in SQLite, and what that costs

`application-shell` says the system MUST NOT persist state that cannot be rebuilt
from Plex, so that deleting the database is always a safe reset. Last-run state
cannot be rebuilt from Plex.

Rather than route around that requirement, this change amends it to admit a
second bounded exception and states the bound: state whose loss produces at most
one redundant, idempotent unit of work. Deleting the database makes the next tick
think no slot has run and start one import — which is exactly what an import that
finds nothing changed already costs, because `plex-import` skips unchanged
posters.

*Alternative rejected:* a marker file beside the settings store. It would keep the
database "pure", at the price of a second persistence mechanism, its own atomic
write, and its own corruption story — for state less valuable than the cache the
database already holds.

The row records the slot, not the wall-clock finish time. "Which slot did we last
complete" is the question being asked, and storing the answer to a different
question invites recomputing it wrongly later.

### Recorded on completion, guarded during the run

The slot is recorded when the import finishes. A crashed run therefore retries at
the next tick rather than being counted as done.

That alone would let a long import overlap the next tick, so a run also takes a
lock for its duration. An import that outruns its interval — plausible at `1h`
against a large library — must not have a second copy started underneath it. The
lock is released on completion and on failure alike, and a stale lock from a
killed process must not disable auto-import forever: it carries the time it was
taken and is treated as abandoned after a generous interval.

### The enabled check moves, and stays

`AutoImportService::run()` already no-ops when disabled, when Plex is
unconfigured, and when no media types are selected. That stays exactly as it is —
the schedule decision sits in front of it, and neither replaces the other. A tick
that is not due exits before doing any of that work; a tick that is due still
passes through every existing guard.

### The init script keeps its other job

`init-marquee-config/run` also creates `/config` directories, sets ownership, and
fixes permissions. Only the scheduling block leaves. `svc-cron/run` loses its
conditional entirely and becomes one `exec`.

### Settings screen

The auto-import section is macros over the controls phase 2 defined: a checkbox
for enabled, four for the media types, and a select for the interval. It appears
after the Plex section.

Wording has to be honest about the tick: a change takes effect on the next hourly
tick, not on the next page load like the rest of the screen.

## Risks / Trade-offs

- **A broken s6 service takes the container down, and CI only builds after a push**
  → Local `docker build` plus a `/health` smoke test before pushing, per
  `docs/docker.md`. The service change is small but it is the kind that fails at
  boot rather than in a test.
- **PHP and `crond` disagreeing about local time would misalign slots** → Both run
  in the same container with the same `TZ`; slot arithmetic uses the same clock
  the tick fires on. Worth an explicit test with a non-UTC timezone.
- **`crond` now always runs, where it used to idle** → It wakes hourly to start a
  PHP process that usually exits within milliseconds. That is the cost of the
  setting being live at all.
- **Catch-up is new behavior an existing install did not ask for** → It only ever
  runs an import that was configured and missed, and an import that finds nothing
  changed is cheap by design.
- **Deleting the database now triggers one extra import** → Stated in the amended
  requirement and in the docs, rather than left as a surprise.

## Migration Plan

No data migration. The last-run table is created by the same idempotent
`CREATE TABLE IF NOT EXISTS` path as every other table, and its absence reads as
"nothing has run".

An upgrading install keeps its schedule: the values were seeded into the settings
store in phase 1, so the interval the crontab used to encode is already stored.
The first boot after upgrading writes the tick crontab, and the first due slot
runs as it would have.

Rollback is reverting the change; the settings store is left as it is, and the
old init script reads the same variables it always did — though an install whose
compose file has since been cleaned would rebuild the crontab from defaults.

## Open Questions

None blocking. One judgement worth confirming during validation: whether catch-up
after downtime is wanted in every case, or whether a container that has been off
for a week should skip straight to its next slot rather than importing on start.
The design runs one catch-up import, which is the behavior that matches "daily".
