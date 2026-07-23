## Context

Manual import already exists (`ImportService.import(sectionKeys, mediaTypes)`).
Auto-import is the unattended version: decide the libraries and media types from
configuration instead of a form, then call the same import. Scheduling lives in
the container (cron), not in PHP.

## Goals / Non-Goals

**Goals:**
- Reuse `ImportService` unchanged; auto-import only selects inputs and logs.
- Testable service: given a fake Plex client and config, it imports the right
  libraries with the right media types.
- Safe no-ops when disabled, unconfigured, or nothing is selected.

**Non-Goals:**
- No in-app scheduler or UI trigger; the schedule is a cron interval.
- No new import mechanics — this is orchestration and configuration only.

## Decisions

- **Config.** `AutoImportConfig` reads the enable flag, the four media-type
  toggles, and `EXCLUDED_LIBRARIES` (comma-separated names). `AUTO_IMPORT_SCHEDULE`
  is consumed by the container's crontab setup, not by PHP.
- **Service.** `AutoImportService::run()` returns early (logging) when disabled,
  when Plex is unconfigured, or when no media types are enabled. Otherwise it
  lists Plex libraries, drops any whose title matches an excluded name
  (case-insensitive), and calls `ImportService::import` with the remaining
  section keys and the enabled media types. It logs the summary.
- **CLI.** `bin/auto-import.php` builds the container, resolves the service, runs
  it, prints the summary, and exits non-zero only on an unexpected error.
- **Scheduling.** The config init writes `AUTO_IMPORT_SCHEDULE` (`1h`/`3h`/`6h`/
  `12h`/`24h`) to a crontab entry that runs `docker/root/app/auto-import.sh`,
  which sources the persisted environment and invokes the CLI. A `svc-cron`
  service runs `crond`. This mirrors the proven container layout.

## Risks / Trade-offs

- **Cron wiring is container-only** and cannot be exercised in unit tests; the
  service and config are fully tested, and the CLI is a thin shell over the
  service. Container build is validated in CI.
- **Excluded libraries match by name.** Renaming a library in Plex changes what
  is excluded — the same behavior users already expect from the variable.
