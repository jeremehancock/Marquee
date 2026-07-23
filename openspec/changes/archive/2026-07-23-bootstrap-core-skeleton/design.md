## Context

The legacy app put routing, HTML, CSS, JS, auth, and Plex logic in a single
10,292-line `index.php`, with configuration read via scattered `getenv()`
calls and state stored in ad-hoc JSON files. The rewrite keeps the same
deployment shape (single Docker image, nginx + PHP-FPM, `/config` volume) but
replaces the internals with a layered PHP application.

## Goals / Non-Goals

**Goals:**
- A minimal, framework-light shell that later capabilities extend without
  touching the front controller.
- Configuration read exactly once into typed, immutable objects.
- Authentication equivalent to the legacy app (single env credential pair,
  session duration, bypass) but isolated in middleware.
- Green tooling from commit one: PHPUnit, PHPStan (max), PHP-CS-Fixer, CI.

**Non-Goals:**
- No poster, Plex, or wall features in this change (later phases).
- No multi-user accounts or password hashing store (single env credential
  matches current scope; revisit if a users capability is added).
- No backward compatibility with the legacy on-disk layout.

## Decisions

- **Slim 4 + PHP-DI container.** Routes are registered in `src/Routes.php`;
  controllers are invokable classes resolved from the container. Chosen over a
  full framework to keep the image small and the code explicit.
- **Config objects.** `Config\AppConfig`, `Config\AuthConfig` are built once in
  `src/bootstrap.php` from `$_ENV`/`getenv()` via small typed readers
  (`env_str`, `env_int`, `env_bool`). Nothing else reads the environment.
- **Auth as middleware.** `AuthMiddleware` short-circuits to the login page for
  unauthenticated requests, except for `/health`, `/login`, and static assets.
  When `AUTH_BYPASS=true`, the middleware sets an authenticated session and
  passes through. Session expiry is enforced against `SESSION_DURATION`.
- **Storage paths.** `/config/data` holds the SQLite database (created lazily in
  a later phase) and logs; `/config/posters` holds images. Paths come from
  config, defaulting to `/config/...`, overridable for tests.
- **Errors + logging.** A Slim error middleware renders a friendly error page
  (or JSON for `Accept: application/json`) and logs via Monolog to
  `/config/data/marquee.log`.
- **Templates.** Twig with a `layout.html.twig` base; Alpine.js is vendored as a
  static asset (no bundler). `SITE_TITLE` is injected as a global.

## Risks / Trade-offs

- **Single credential pair** keeps parity but is not multi-user; acceptable for
  a self-hosted single-admin tool, revisited only if requested.
- **No build step** means Alpine.js is pinned as a vendored file; upgrades are
  manual, traded for a much simpler image.
- **Dev PHP is 8.4, runtime targets 8.3.** Composer `platform.php` is pinned to
  8.3 so dependency resolution matches the image.
