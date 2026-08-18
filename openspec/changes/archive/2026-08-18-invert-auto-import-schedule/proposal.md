## Why

Auto-import is the last thing in Marquee that still requires recreating the
container to change. Phase 2 gave every other setting a screen and deliberately
left this one off it, because a control here would not work: the schedule is
written into the container's crontab once, at boot.

It is also broken in a way nobody can see. `init-marquee-config/run` writes
`/etc/crontabs/abc` only when `AUTO_IMPORT_ENABLED` is true, and `svc-cron/run`
starts `crond` only when that file is non-empty. **A container that booted with
auto-import off has no cron process at all** — so even after this change gives
the setting a switch, nothing would be there to notice it had been flipped.

## What Changes

- **The crontab becomes a fixed hourly tick**, written unconditionally, and
  `crond` always runs. The interval-to-cron-expression case statement and the
  enabled check both leave the init script.
- **`bin/auto-import.php` decides whether a run is due.** It reads enabled, the
  media types, and the interval from the settings store — the same store the web
  process reads — compares against the last completed run, and exits quietly when
  the answer is no.
- **Slots, not elapsed time.** A run is due when the current hour begins an
  interval slot the install has not run yet. `24h` therefore still means midnight
  and `6h` still means 00:00, 06:00, 12:00, 18:00 — exactly the cron expressions
  being removed. The five user-facing choices do not change.
- **A missed slot is caught up rather than skipped.** A container that was down at
  midnight runs at the first tick after it comes back, which the current crontab
  cannot do.
- **Last-run state lives in SQLite**, alongside the Plex cache. Losing it costs one
  extra import.
- **A run in progress blocks the next tick**, so an import that outlives its own
  interval cannot start a second copy of itself.
- **Auto-import joins the settings screen**: enable, per-type toggles, and the
  interval, applying on the next tick with no restart.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `auto-import`: scheduling becomes the application's rather than the container's.
  Adds what "due" means, catch-up after downtime, the overlap guard, and the
  controls on the settings screen.
- `settings`: the screen gains the auto-import section. The requirement recording
  auto-import as deliberately withheld is overturned, which is the point of having
  written it as a requirement.
- `application-shell`: "Persisted state is recreatable" currently forbids
  persisting anything that cannot be rebuilt from Plex. Last-run state cannot be,
  so this admits a second, bounded exception — state whose loss is self-correcting
  — rather than quietly violating the invariant.

## Impact

- **Docker — needs a local build and a `/health` smoke test before pushing.**
  `docker/root/etc/s6-overlay/s6-rc.d/init-marquee-config/run` loses its
  scheduling block; `svc-cron/run` loses its conditional and always execs `crond`.
  CI only exercises the image after a push, so a broken s6 service would be found
  by users rather than by the pipeline.
- **New**: a last-run repository and its table, a service deciding whether a run
  is due, an auto-import section in the settings form and template.
- **Modified**: `AutoImportConfig` (the interval stops being inert),
  `AutoImportService`, `bin/auto-import.php`, `Database` migrations,
  `SettingsForm`, `settings.html.twig`.
- **Docs**: the README's auto-import rows describe first-boot seeding only, and
  the Settings section gains the auto-import group. `docs/docker.md` if the
  service changes alter the smoke test.
- **Unaffected**: what an import does. This change is entirely about when.
