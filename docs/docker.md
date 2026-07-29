# The Docker image — PHP version & local verification

Marquee ships as a single Docker image built from a LinuxServer Alpine-nginx
base. Two things about it are easy to get wrong and expensive to discover in CI.

---

## The PHP version comes from the base image tag

The shipped PHP version is dictated by the **LinuxServer base image tag** in the
`Dockerfile`, not by the `php8x-*` extension packages the Dockerfile `apk add`s.

| Base tag | Bundles |
| --- | --- |
| `ghcr.io/linuxserver/baseimage-alpine-nginx:3.21` | PHP 8.3, `php83-fpm`, `/etc/php83/` |
| `ghcr.io/linuxserver/baseimage-alpine-nginx:3.22` | PHP 8.4, `php84-fpm`, `/etc/php84/` |

**Bumping only the extension packages fails the build.** The `php84-*` packages
install a second PHP alongside the base's, but php-fpm and `/etc/phpNN/` stay at
the base's version — so the fpm-config `sed` step errors with *"No such file or
directory"*. This is exactly how PR #23 (0.5.1, PHP 8.3 → 8.4) first went red.

### Upgrading PHP — the full checklist

Miss one of these and the versions drift apart silently:

- [ ] `Dockerfile` — the base image tag
- [ ] `Dockerfile` — every `php8N-*` package name
- [ ] `Dockerfile` — the `/etc/php8N/` paths in the fpm `sed` and the ini `printf`
- [ ] `composer.json` — **both** `require.php` and `config.platform.php`
- [ ] `.github/workflows/ci.yml` — `php-version`
- [ ] `README.md` — the PHP version mentions
- [ ] `docs/development-workflow.md` — any PHP version mention
- [ ] `openspec/config.yaml` — the tech-stack line (feeds AI-generated specs)
- [ ] `CLAUDE.md` — the base-tag example in *Docker gotchas*

> The dev machine may run a newer PHP than the target. `composer.json` pins
> `config.platform.php` so dependencies resolve for the deployment runtime —
> trust the pin, not `php -v`.

---

## Always build the image locally before pushing a Dockerfile change

CI's lint/analyse/test job never exercises the image. Only the separate `docker`
job (build + `/health` smoke test) does, and it runs *after* you push — so a
broken Dockerfile costs a full red-CI round trip.

Replicate that job locally:

```bash
docker build -t marquee:ci-test .
docker run -d --name marquee-test -p 8099:80 -e AUTH_BYPASS=true marquee:ci-test

# poll until ready, then:
curl -fsS http://127.0.0.1:8099/health     # expect {"status":"ok","app":"marquee"}
docker exec marquee-test php -v            # confirm the PHP version if relevant

docker rm -f marquee-test && docker rmi marquee:ci-test
```

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
  installs the PHP extensions via `apk`.
- Every build gets an immutable `sha-<short>` tag, so any commit's image can be
  pulled for a rollback. See
  [development-workflow.md](development-workflow.md) for the branch → tag map.
