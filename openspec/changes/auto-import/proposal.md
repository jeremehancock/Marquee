## Why

Posters change in Plex over time as libraries grow. Rather than importing by
hand, users want Marquee to refresh from Plex on a schedule. This change adds a
scheduled import that runs in the container on a configurable interval.

## What Changes

- Add auto-import configuration: enable/disable, which media types to import
  (movies, shows, seasons, collections), and libraries to exclude by name.
- Add an `AutoImportService` that imports the enabled media types from every
  non-excluded Plex library, reusing the existing import logic.
- Add a CLI entry point (`bin/auto-import.php`) that runs one auto-import and
  logs the result.
- Schedule it in the container: an init step writes a crontab from
  `AUTO_IMPORT_SCHEDULE`, and a cron service runs the CLI on that interval.

## Capabilities

### New Capabilities
- `auto-import`: run a scheduled Plex import of the configured media types across
  all non-excluded libraries.

### Modified Capabilities
<!-- None. -->

## Impact

- New: `src/Config/AutoImportConfig.php`,
  `src/Plex/Import/AutoImportService.php`, `bin/auto-import.php`,
  `docker/root/app/auto-import.sh`, s6 `svc-cron` service, crontab setup in the
  config init.
- Environment: `AUTO_IMPORT_ENABLED`, `AUTO_IMPORT_SCHEDULE`,
  `AUTO_IMPORT_MOVIES`, `AUTO_IMPORT_SHOWS`, `AUTO_IMPORT_SEASONS`,
  `AUTO_IMPORT_COLLECTIONS`, `EXCLUDED_LIBRARIES`.
