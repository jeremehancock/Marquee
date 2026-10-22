## Why

Marquee's users were exposed to a critical PHP flaw (PHP2Shell, CVE-2026-109618/109619) for longer than they needed to be, and nothing warned anyone. The image took its PHP from the Alpine release under the LinuxServer base, and Alpine stops updating PHP on a release once the next one ships, so PHP had been frozen on 8.4.16 for ten months while every build reported success. 2.15.1 patched it by moving to a newer base, but the same freeze will recur on that base within months. Users need the PHP inside Marquee to keep receiving security fixes, and the maintainer needs to hear about it when it does not.

## What Changes

- Build the image on the official PHP image (`php:8.5-fpm-alpine`), which is rebuilt for every PHP patch release, in place of the LinuxServer Alpine-nginx base. A rebuild then always picks up the newest PHP 8.5 patch.
- Supply what the LinuxServer base provided and Marquee relies on: nginx, the s6 process supervisor, cron, the `abc` user mapped to `PUID`/`PGID`, `TZ` applied to both PHP and cron, the PHP extensions Marquee needs, and production PHP settings.
- Existing installs upgrade in place with no change to what they see or keep: the same `/config` data, posters and sessions, the same ownership, port 80, `/health`, and the hourly auto-import tick.
- The nginx configuration moves into the image instead of being copied into `/config` once on first start. Every install, including existing ones, now runs the current configuration, so the sign-in rate limit reaches the installs that never received it. **BREAKING** for anyone who hand-edited `/config/nginx/`: those files are no longer read.
- nginx and PHP-FPM logs go to the container log (`docker logs`) instead of `/config/log/`. Marquee's own logs in `/config/data/` are unchanged. **BREAKING** only for anyone reading those two log files directly.
- LinuxServer leftovers in `/config` (`nginx/`, `php/`, `keys/`, `log/`, `www/`) stop being used. They are left in place, never deleted, and documented as safe to remove.
- Add a scheduled check that compares the PHP in the published `:latest` image with the newest upstream PHP patch release for the same version line, and fails visibly when the image has fallen behind for longer than a short grace period. Its remedy is a patch release through the normal flow, so users also get the in-app update notice.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `release-publishing`: adds requirements that a published image runs a PHP patch release kept current with upstream, that an image falling behind is reported rather than shipped silently, and that a change to how the image is built upgrades an existing install in place without loss of data, ownership or behavior.

## Impact

- `Dockerfile`: new base, PHP extensions compiled with the official image's helpers, nginx, s6-overlay, tzdata and user tooling from Alpine packages; FPM pool and ini moved to the official image's paths.
- `docker/root/`: the s6 services gain the startup work LinuxServer did (user mapping, ownership, timezone) and start nginx and PHP-FPM themselves; the nginx site config moves from a first-run sample to a file the image owns.
- `.github/workflows/`: a new scheduled workflow for the PHP currency check; the CI smoke test additionally checks the cron service, `PUID`/`PGID` and `TZ`.
- Docs: `docs/docker.md` (base image, PHP upgrade checklist, smoke test), `README.md` (stack line; the rate-limit section that tells existing installs to copy config by hand; a note on leftover `/config` folders and where logs went), `docs/development-workflow.md`, `CLAUDE.md` (the Docker section), `openspec/config.yaml` (tech stack).
- No application code, settings, routes or stored data change.
