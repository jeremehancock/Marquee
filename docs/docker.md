# The Docker image: PHP version & local verification

Marquee ships as a single Docker image built on the official PHP image,
`php:8.5-fpm-alpine`, with nginx, s6-overlay and cron added on top. A few things
about it are easy to get wrong and expensive to discover in CI.

---

## Where PHP comes from, and how it stays current

The official PHP image is rebuilt for every PHP patch release, and builds PHP
from source rather than taking it from Alpine's packages. The `Dockerfile` uses
the **moving tag** `php:8.5-fpm-alpine`, so any rebuild of Marquee picks up the
newest 8.5 patch with no change to the repository.

That is the whole point of this base. Marquee used to build on a LinuxServer
Alpine base and take PHP from Alpine's *community* repository, which is only
updated until the next Alpine release. Rebuilding on that base reinstalled the
same old PHP and nothing failed: 2.15.0 shipped PHP 8.4.16, ten months stale,
and missed the critical PHP2Shell fix (CVE-2026-109618/109619).

**A rebuild is still needed for a patch to reach users.** The scheduled
[`php-currency`](../.github/workflows/php-currency.yml) workflow checks every
Monday whether the published `:latest` runs the newest PHP patch on its line,
and fails (GitHub notifies you) once it has been behind for more than seven
days. The fix is a patch release through `/ship`: CI rebuilds the image, and the
GitHub Release tells existing users through the in-app update notice. Run the
workflow by hand from the Actions tab to check at any time.

**Extensions.** The official image already has everything Marquee uses except
`gd` and `intl`, which the `Dockerfile` compiles with `docker-php-ext-install`.
Adding another extension means adding it there (and its `-dev` build package),
not an `apk add php85-*`: Alpine's PHP packages belong to a different PHP and
would not load.

### Moving to a new PHP version line (8.6 and later)

Patches arrive by themselves; a new version line does not. Miss one of these and
the versions drift apart silently:

- [ ] `Dockerfile`: the `php:8.N-fpm-alpine` tag
- [ ] `composer.json`: **both** `require.php` and `config.platform.php`, then
      `composer update --lock`
- [ ] `.github/workflows/ci.yml` and `php-currency.yml`: `php-version`
- [ ] `README.md`: the PHP version mentions
- [ ] `docs/development-workflow.md`: any PHP version mention
- [ ] `openspec/config.yaml`: the tech-stack line (feeds AI-generated specs)
- [ ] `CLAUDE.md`: the stack line at the top
- [ ] Run the test suite on the new version, and the full smoke test below

> The dev machine may run a newer PHP than the target. `composer.json` pins
> `config.platform.php` so dependencies resolve for the deployment runtime.
> Trust the pin, not `php -v`.

---

## What the image does at startup

s6-overlay (`ENTRYPOINT ["/init"]`) runs the services under
`docker/root/etc/s6-overlay/s6-rc.d/` in dependency order:

1. `init-marquee-user` maps the `abc` user to `PUID`/`PGID` (default 911),
   applies `TZ` to the system clock and to PHP's `date.timezone` (PHP does not
   read `TZ` itself), and prints the startup banner.
2. `init-marquee-config` creates the `/config` folders, gives them to `abc`, and
   writes the hourly auto-import tick.
3. `svc-php-fpm`, `svc-nginx` and `svc-cron` start and are restarted if they die.

nginx's configuration (`docker/root/etc/nginx/`) and PHP's (`php.ini-production`
plus `docker/root/usr/local/etc/php/conf.d/zz-marquee.ini` and the FPM pool in
`php-fpm.d/marquee.conf`) are files in the image, so every upgrade delivers
them. Nothing is copied into `/config`. The FPM pool listens on `127.0.0.1:9000`
only; the official image's own pool files are deleted so no second listener can
appear. nginx and PHP-FPM log to the container log (`docker logs`).

---

## Always build the image locally before pushing a Dockerfile change

CI's lint/analyse/test job never exercises the image. Only the separate `docker`
job (build plus smoke test) does, and it runs *after* you push, so a broken
Dockerfile costs a full red-CI round trip.

Replicate that job locally, with non-default `PUID`/`PGID`/`TZ` so you can tell
they were applied:

```bash
docker build -t marquee:ci-test .
docker run -d --name marquee-test -p 8099:80 \
  -e PUID=1234 -e PGID=1235 -e TZ=America/Denver marquee:ci-test

# poll until ready, then:
curl -fsS http://127.0.0.1:8099/health     # expect {"status":"ok","app":"marquee"}
docker logs marquee-test                    # banner: UID 1234, GID 1235, the TZ, the PHP version
docker exec marquee-test php -v

docker rm -f marquee-test && docker rmi marquee:ci-test
```

**`/health` does not prove the rest came up**: nginx can serve pages perfectly
while cron is dead, `/config` has the wrong owner, or PHP is on the wrong
timezone. Check them:

```bash
for s in svc-php-fpm svc-nginx svc-cron; do docker exec marquee-test s6-svstat /run/service/$s; done   # each "up (pid …)"
docker exec marquee-test cat /etc/crontabs/abc                    # expect the hourly tick
docker exec marquee-test stat -c '%u:%g' /config/data             # expect 1234:1235
docker exec marquee-test stat -c '%u:%g' /var/lib/nginx/tmp       # expect 1234:1235 (nginx buffers big responses here)
docker exec marquee-test php -r 'echo date_default_timezone_get();'   # expect America/Denver
docker exec marquee-test netstat -tln                             # 9000 on 127.0.0.1 only
docker exec marquee-test su -s /bin/bash abc -c /app/auto-import.sh   # one tick, as cron runs it
```

Run the tick as `abc`, as cron does. Running it as root creates log files root
owns, and the next tick as `abc` then cannot write them (until a restart
re-owns `/config`).

The tick is written unconditionally and `crond` always runs, whatever the
auto-import settings say. That is the point of it, so a container started with
auto-import off is exactly the case worth checking.

**For a change to startup or the base image, also test an upgrade**: create a
`/config` with the previous release (for example `bozodev/marquee:2.15.1`), then
recreate from your build on the same volume and check the same list.

---

## Keeping repo-only files out of the image

The Dockerfile does `COPY . /app/www/`, so anything not listed in
`.dockerignore` ends up in the image. When adding a directory that exists only
for developers — docs, specs, tooling — add it to `.dockerignore` too.

Watch the glob rule: **`*` does not match across `/`**, so the `*.md` entry
excludes `README.md` and `CLAUDE.md` at the root but *not* `docs/anything.md`.
Ignore the directory by name (`docs`) rather than relying on the extension
pattern. Use `**/*.md` if you want Markdown excluded at any depth.

## Other image notes

- **Env var changes require recreating the container** (`docker compose up -d`),
  not just restarting it.
- The build stage runs `composer install --ignore-platform-reqs` on purpose: the
  build image only resolves and downloads packages, while the runtime image
  compiles the PHP extensions Marquee needs.
- The image is built for `linux/amd64` and `linux/arm64`. The arm64 half
  compiles `gd` and `intl` under emulation in CI, so a publish run takes longer
  than the CI smoke test, which builds amd64 only.
- Every build gets an immutable `sha-<short>` tag, so any commit's image can be
  pulled for a rollback. See
  [development-workflow.md](development-workflow.md) for the branch → tag map.
