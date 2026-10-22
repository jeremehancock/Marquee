## 1. Image base and PHP

- [x] 1.1 Change the runtime stage of the `Dockerfile` to `FROM php:8.5-fpm-alpine` and install `nginx`, `s6-overlay`, `tzdata`, `shadow`, `bash` and `curl` with `apk`
- [x] 1.2 Compile `gd` (JPEG, WebP, AVIF, FreeType) and `intl` with `docker-php-ext-configure`/`docker-php-ext-install`, removing the build-only packages in the same layer, and confirm `php -m` lists every extension the current image has that Marquee needs
- [x] 1.3 Install `php.ini-production` as `php.ini` and write `zz-marquee.ini` with the existing upload, memory and execution limits under `$PHP_INI_DIR/conf.d/`
- [x] 1.4 Add the `zz-marquee.conf` FPM pool: user and group `abc`, `listen = 127.0.0.1:9000`, `clear_env = no`, the current `pm` values, worker output to the container log
- [x] 1.5 Create the `abc` user and group with uid and gid 911 at build time, and set `ENTRYPOINT ["/init"]` for s6-overlay

## 2. Startup and services

- [x] 2.1 Add a one-shot `init-marquee-user` service that maps `abc` to `PUID`/`PGID`, links `/etc/localtime` from `TZ` (default `Etc/UTC`), writes PHP's `date.timezone` from the same value, and prints the startup banner
- [x] 2.2 Make `init-marquee-config` depend on `init-marquee-user` instead of the LinuxServer `init-nginx-end`, and replace `lsiown` with `chown -R abc:abc /config`
- [x] 2.3 Add `svc-php-fpm` and `svc-nginx` long-running services that depend on `init-marquee-config`, and add both to the `user` bundle
- [x] 2.4 Check that `svc-cron` still gets the container environment through `with-contenv` and still runs the `abc` crontab
- [x] 2.5 Remove the LinuxServer-only pieces from `docker/root/` (`init-adduser/branding`, the `init-config-end` and `init-nginx-end` dependency links)

## 3. nginx

- [x] 3.1 Ship `/etc/nginx/nginx.conf` from the image: workers as `abc`, temporary paths writable by `abc`, access and error logs to stdout/stderr, include the Marquee site config
- [x] 3.2 Move the site config from `docker/root/defaults/nginx/site-confs/default.conf.sample` to an image-owned file under `/etc/nginx/http.d/`, keeping the sign-in rate limit, body size, FastCGI timeout and PHP restrictions unchanged

## 4. PHP currency check

- [x] 4.1 Write the comparison as a small PHP script: given the image's PHP version and php.net's newest release and date for that line, report pass or fail (behind for more than seven days fails; either version unknown fails), naming both versions
- [x] 4.2 Add `.github/workflows/php-currency.yml`, run weekly and by manual dispatch, that pulls `bozodev/marquee:latest`, reads its `PHP_VERSION`, queries php.net for that line, and runs the comparison. It publishes nothing
- [x] 4.3 Extend the CI `docker` job's smoke test to check that all three s6 services are up, that `PUID`/`PGID` decide `/config` ownership, that `TZ` reaches both `date` and PHP, and that FPM listens only on `127.0.0.1`

## 5. Documentation

- [x] 5.1 Rewrite `docs/docker.md` for the official base: where PHP comes from, the PHP-upgrade checklist for a new version line, the extended local smoke test, and the currency check
- [x] 5.2 Update `README.md`: the stack line, replace the "existing installs will not pick it up" rate-limit section, and add a short note on logs moving to `docker logs` and the leftover `/config` folders being safe to delete
- [x] 5.3 Update `docs/development-workflow.md` (s6 note, repo layout), `CLAUDE.md` (Docker section) and `openspec/config.yaml` (tech stack line) to name the official image
- [x] 5.4 Keep the PHP2Shell security note at the top of `README.md`, and update it so it stays accurate for this release (for example, the PHP version current releases run, and that later releases receive PHP patches through the official image). Do not remove it

## 6. Tests and verification

- [x] 6.1 Add PHPUnit tests for the currency comparison covering each scenario in the spec: behind beyond the grace period, behind within it, current, and unknown versions
- [x] 6.2 Run `composer test`, `composer stan` and `composer cs`
- [x] 6.3 Build the image locally and run the full smoke test from `docs/docker.md`, including a manual auto-import tick, and record the image size against 2.15.1
- [x] 6.4 Run the upgrade test: create a `/config` with `bozodev/marquee:2.15.1` using non-default `PUID`, `PGID` and `TZ`, recreate from the new image on the same volume, and confirm settings, posters and sessions survive, ownership is correct, the sign-in rate limit is active, and the leftover LinuxServer folders are untouched
- [x] 6.5 Run the currency workflow's steps against the published `latest` image and confirm it passes (locally before merging: GitHub only dispatches a workflow once its file is on the default branch, so the first manual dispatch follows the merge)
