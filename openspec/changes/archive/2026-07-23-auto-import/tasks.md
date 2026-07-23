## 1. Config & service

- [x] 1.1 `Config\AutoImportConfig` from env (enabled, toggles, excluded libraries) + `mediaTypes()`.
- [x] 1.2 `Plex\Import\AutoImportService::run()` (select libraries/types, reuse ImportService, log).
- [x] 1.3 Container binding for `AutoImportConfig`.

## 2. CLI & scheduling

- [x] 2.1 `bin/auto-import.php` — bootstrap container, run service, log/exit.
- [x] 2.2 `docker/root/app/auto-import.sh` wrapper (source env, run CLI).
- [x] 2.3 s6 `svc-cron` service; write crontab from `AUTO_IMPORT_SCHEDULE` in the config init.

## 3. Verify

- [x] 3.1 Unit: config (`mediaTypes`, exclusions), service (imports non-excluded
      libraries with enabled types; no-ops when disabled / unconfigured / nothing enabled).
- [x] 3.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 3.3 `openspec validate auto-import` passes.
