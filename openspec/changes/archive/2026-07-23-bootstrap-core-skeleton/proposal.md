## Why

Marquee is a clean-room rewrite of a 10k-line PHP monolith. Before any feature
can be rebuilt cleanly, the project needs a foundation: a front controller, a
router, typed configuration from the environment, session-backed
authentication, structured errors and logging, and the developer tooling
(tests, static analysis, style, CI, Docker) that keeps the rewrite honest.

This change establishes that foundation so every later capability
(poster-library, plex-import, poster-wall, …) plugs into a consistent,
tested shell instead of being bolted onto a monolith.

## What Changes

- Introduce a Slim 4 application with a single public front controller.
- Load all configuration once at bootstrap into immutable, typed config objects
  sourced from environment variables.
- Add session handling and an authentication middleware: a login page, logout,
  session-duration expiry, and an `AUTH_BYPASS` escape hatch for trusted LANs.
- Add centralized error handling and Monolog logging to `/config/data`.
- Expose an unauthenticated `/health` endpoint for container healthchecks.
- Render pages with Twig using a shared base layout; add Alpine.js for
  interactivity with no build step.
- Establish the Composer project, PSR-4 autoloading, PHPUnit, PHPStan, and
  PHP-CS-Fixer, plus a GitHub Actions CI workflow.
- Provide the Docker image (LinuxServer Alpine-nginx base, s6-overlay) with the
  `/config` volume, `PUID`/`PGID`/`TZ` support, and a healthcheck.
- Remove the legacy Posteria source in favor of the new structure.

## Capabilities

### New Capabilities
- `application-shell`: HTTP bootstrap, routing, typed config, sessions,
  error handling, logging, and the container healthcheck.
- `authentication`: environment-based credentials, session login/logout,
  session expiry, and the authentication-bypass option.

### Modified Capabilities
<!-- None: this is the first change. -->

## Impact

- New: `composer.json`, `public/index.php`, `src/` (App namespace),
  `templates/`, `tests/`, `Dockerfile`, `docker/`, `.github/workflows/ci.yml`,
  tooling configs (`phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`).
- Removed: legacy `src/index.php` monolith and `src/include/`, old `Dockerfile`,
  and `docker/` layout.
- Runtime: `/config/posters` (images) and `/config/data` (SQLite + logs)
  volumes; environment variables `SITE_TITLE`, `AUTH_USERNAME`,
  `AUTH_PASSWORD`, `AUTH_BYPASS`, `SESSION_DURATION`, `PUID`, `PGID`, `TZ`.
