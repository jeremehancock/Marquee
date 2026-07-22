## 1. Config & service

- [ ] 1.1 `Config\AutoImportConfig` from env (enabled, toggles, excluded libraries) + `mediaTypes()`.
- [ ] 1.2 `Plex\Import\AutoImportService::run()` (select libraries/types, reuse ImportService, log).
- [ ] 1.3 Container binding for `AutoImportConfig`.

## 2. CLI & scheduling

- [ ] 2.1 `bin/auto-import.php` — bootstrap container, run service, log/exit.
- [ ] 2.2 `docker/root/app/auto-import.sh` wrapper (source env, run CLI).
- [ ] 2.3 s6 `svc-cron` service; write crontab from `AUTO_IMPORT_SCHEDULE` in the config init.

## 3. Verify

- [ ] 3.1 Unit: config (`mediaTypes`, exclusions), service (imports non-excluded
      libraries with enabled types; no-ops when disabled / unconfigured / nothing enabled).
- [ ] 3.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 3.3 `openspec validate auto-import` passes.
