## Context

The image is built `FROM ghcr.io/linuxserver/baseimage-alpine-nginx`. That base supplies PHP, nginx and cron from the packages of one Alpine release, plus LinuxServer's init system. Alpine only updates PHP on a release until the next one ships, so the PHP inside Marquee stops receiving fixes a few months after each base bump. That is how 2.15.0 shipped PHP 8.4.16 ten months after it was built, and missed the PHP2Shell fix.

Inspecting a running 2.15.1 container (`PUID=1234 PGID=1234 TZ=America/Denver`) shows everything the base does that Marquee depends on:

- **Processes**: s6-overlay supervises `svc-php-fpm`, `svc-nginx` and Marquee's own `svc-cron` (busybox `crond`), restarting any that die.
- **User**: an `abc` user whose uid and gid are changed to `PUID`/`PGID` at start (default 911). PHP-FPM workers run as `abc`, and `init-marquee-config` runs `lsiown -R abc:abc /config` on every start.
- **Timezone**: `TZ` sets the system clock zone, which `crond` uses, and the base writes it into PHP's `date.timezone`. PHP does not read `TZ` on its own. `AutoImportSchedule` relies on both agreeing.
- **nginx**: the main config and the site config are copied into `/config/nginx/` on first start and never again, so existing installs still run whatever site config they first received. The README tells those users to copy the sign-in rate limit in by hand.
- **PHP**: Alpine's production-style `php.ini`; an FPM pool on `127.0.0.1:9000` with `clear_env = no` and `pm.max_children = 5`; extensions `curl gd intl mbstring pdo_sqlite sqlite3 session openssl dom xml xmlwriter simplexml tokenizer fileinfo ctype phar`. `/config/php/` is scanned for extra ini files.
- **Logs**: nginx and PHP-FPM write to `/config/log/`, with logrotate.
- **Other**: a self-signed certificate in `/config/keys/` (unused: Marquee listens on 80 only), `/config/www/` (unused), and LinuxServer extras such as `DOCKER_MODS` and `UMASK`, which Marquee has never documented.

The official `php:8.5-fpm-alpine` image (currently Alpine 3.24, PHP 8.5.12) is rebuilt for every PHP patch release and moves to new Alpine releases on its own. It builds PHP from source, so its version does not depend on Alpine's package updates. It already includes every extension above except `gd` and `intl`, and it ships no nginx, cron supervisor or user mapping. Alpine's own repository has `nginx`, `s6-overlay` (3.2.3), `tzdata` and `shadow`, none of them tied to a PHP version.

## Goals / Non-Goals

**Goals:**

- PHP patch releases reach Marquee by rebuilding, with no repository change.
- An existing install upgrades by pulling and recreating, and keeps its data, posters, sessions, ownership, timezone behavior, port, health check and hourly tick.
- A published image that falls behind upstream PHP fails a check the maintainer will see.
- Every install runs the nginx configuration its image ships.

**Non-Goals:**

- Moving to a new PHP version line (8.6). That stays a deliberate upgrade with its checklist.
- Rebuilding or republishing automatically on a schedule. The specified publish flow is unchanged, and the remedy for a stale image is a release.
- Supporting LinuxServer extras Marquee never documented (`DOCKER_MODS`, `UMASK`, ini overrides in `/config/php/`, hand-edited `/config/nginx/`).
- Deleting the leftover LinuxServer files in `/config`.
- HTTPS inside the container. Marquee serves port 80 and relies on a proxy for TLS, as before.

## Decisions

### Base on `php:8.5-fpm-alpine`, by moving tag

The `Dockerfile` uses the tag `php:8.5-fpm-alpine` rather than a digest or a full patch version. The tag is what moves when PHP publishes a patch, so a rebuild picks the patch up. Each build is still pinned for rollback by its `sha-<short>` image.

- *Pinning a digest and updating it with Dependabot*: reproducible builds, but it brings back the problem being solved. PHP is current only if someone merges the bump, and a missed bump is silent again.
- *Debian `php:8.5-fpm`*: larger image, and a different package manager from everything in `docker/root`, for no benefit here.

### Keep s6-overlay, installed from Alpine's package

Install `s6-overlay` with `apk` and keep the existing `s6-rc.d` service tree, adding services for PHP-FPM and nginx and an init step for the user and timezone. `with-contenv`, which `svc-cron` already uses to hand the container environment to `crond`, ships with it.

- *A shell entrypoint that starts the three processes*: nothing restarts a process that dies. A dead `crond` with a healthy nginx is exactly the failure `docs/docker.md` warns `/health` cannot see.
- *supervisord*: pulls in Python, and has no ordered one-shot init step to run before the services.
- *The upstream s6-overlay tarball*: one more version pin to update by hand. Alpine's package is upgraded whenever the official image moves to a new Alpine release, which a rebuild already picks up.

### Compile `gd` and `intl` with the official helpers

Use `docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype` and `docker-php-ext-install gd intl`, then remove the build-only `-dev` packages in the same layer and keep the runtime libraries. This matches today's `gd` features (JPEG, PNG, WebP, AVIF, FreeType). `intl` is needed: Marquee uses `Normalizer`, and Guzzle uses it for internationalised domain names when canonicalising hosts, which the poster URL guard depends on.

- *`mlocati/install-php-extensions`*: convenient, but a third-party script fetched at build time, for two extensions the official helpers handle.

### PHP settings: production `php.ini`, Marquee's overrides, Marquee's own pool

- Copy `php.ini-production` to `php.ini`, then add `zz-marquee.ini` with today's upload, memory and execution limits. `display_errors` stays off, and `DISPLAY_ERRORS` keeps working through the application as now. `zz-marquee.ini` also sets `expose_php = Off` (the production `php.ini` leaves it on), so responses stop carrying an `X-Powered-By` header that names the PHP version.
- Delete the official pool files (`docker.conf`, `www.conf`, `zz-docker.conf`) and ship one `marquee.conf` holding both the global settings and the pool: user and group `abc`, `listen = 127.0.0.1:9000` (the official default listens on every interface, which would expose FPM to any container sharing the network), `clear_env = no`, the same `pm` values, and errors and worker output to the container log. One file rather than an override sorted after theirs, so a later official image cannot add a second listener that ours does not replace.
- `date.timezone` is written at start from `TZ` (default `Etc/UTC`) into `conf.d/`, because PHP does not read `TZ`.

### Map `abc` to `PUID`/`PGID` in a one-shot init step

At build time create `abc` with uid and gid 911 (the documented default). A new one-shot service runs first on each start: `groupmod`/`usermod` (from `shadow`) to `PGID`/`PUID` when they differ, link `/etc/localtime` to the zone named by `TZ`, and write the PHP timezone ini. `init-marquee-config` then runs as today, with `lsiown` replaced by `chown -R abc:abc /config`. nginx and PHP-FPM depend on both steps, so nothing serves before ownership and timezone are settled.

### nginx config lives in the image

Ship `/etc/nginx/nginx.conf` and the site config as image files rather than first-run samples. nginx runs its workers as `abc`, so uploads written to its temporary directory belong to the same user as everything else, and it logs to stdout/stderr. The README section telling existing installs to copy the rate limit by hand is replaced with a note that upgrading delivers it.

### Logs go to the container log

nginx access and error logs and PHP-FPM errors go to stdout/stderr, read with `docker logs`. That is the standard place in a container, it needs no rotation, and `/config` stays limited to what is worth backing up. Marquee's application logs in `/config/data/` are unchanged.

### The currency check is its own scheduled workflow

Add `.github/workflows/php-currency.yml`, run weekly and by manual dispatch:

1. Pull `bozodev/marquee:latest` and read `PHP_VERSION` from it.
2. Ask php.net (`https://www.php.net/releases/index.php?json&version=<major.minor>`) for the newest release on that line and its date.
3. Fail, naming both versions, if the image is behind and the release is more than seven days old. Fail if either version cannot be read.

It publishes nothing, so the publish requirements are untouched. GitHub notifies the person who last edited a workflow's schedule when a scheduled run fails, which is the maintainer, and the remedy is a patch release through `/ship`. Checking the published `latest` image rather than a fresh build is deliberate: the question is what users are running.

- *Failing CI on every push when PHP is behind*: blocks unrelated work during the day or two the official image takes to publish a patch, and says nothing during months without pushes, which is when the freeze happened.
- *Dependabot*: it tracks tags and digests, not "the PHP inside this image".

## Risks / Trade-offs

- [Someone hand-edited `/config/nginx/` and loses the edit] → The proposal marks it **BREAKING**, the release notes and README say where the config now lives, and a proxy in front of Marquee is the documented place for custom rules.
- [The official image moves to a new Alpine release under a rebuild] → Every package Marquee installs (`nginx`, `s6-overlay`, `tzdata`, `shadow`, the `gd`/`intl` build libraries) has a stable, version-free name. The CI smoke test catches a breaking change before anything publishes.
- [`chown -R` on a large `/config` slows startup] → Unchanged from today, which already does it on every start.
- [The PHP 8.5 line itself reaches end of life] → The currency check compares within the line, so it would pass on a line with no more releases. Out of scope here (see Open Questions), and years away (8.5 has security support until the end of 2029).
- [Image size grows from compiling extensions] → The build libraries are removed in the same layer; only runtime libraries remain. Size is reported during verification.
- [An undocumented LinuxServer feature someone relied on disappears] → Listed in the release notes (`DOCKER_MODS`, `UMASK`, `/config/php/` overrides, logs in `/config/log/`).

## Migration Plan

1. Build locally and run the full smoke test: `/health`, the sign-in page, `s6-svstat` for all three services, the crontab, a manual auto-import tick, `PUID`/`PGID` ownership, `TZ` in both `date` and PHP, and `listen 127.0.0.1:9000`.
2. Upgrade test: create a `/config` with `bozodev/marquee:2.15.1` using non-default `PUID`/`PGID`/`TZ`, then recreate from the new image on the same volume. Check that data, posters and sessions survive, ownership is right, the rate limit is active, and the leftover folders are untouched.
3. Ship to `:dev` through `/ship`. The maintainer validates on a real install.
4. Release as a version bump chosen at `/ship`. The release notes list the breaking items above.

**Rollback**: pull `:2.15.1` (or any `sha-<short>`) and recreate. The new image deletes nothing and changes no data format, and the old image still finds its own files in `/config/nginx/` and the rest.

## Open Questions

- Should the currency check also fail when the PHP version line is past upstream security support? php.net publishes support dates, so it is possible, but it is a separate decision and years from mattering for 8.5.
