## Context

The app is already responsive and served over one origin. Turning it into a PWA
is additive: a manifest, icons, install meta tags, and a service worker. The
version check is a small service plus an endpoint the footer enhances.

## Goals / Non-Goals

**Goals:**
- Installable on mobile and desktop; static assets cached by a service worker.
- Manifest reflects `SITE_TITLE`.
- Version always visible; update check is optional and never blocks rendering.

**Non-Goals:**
- No offline caching of authenticated pages or poster images (assets only).
- No push notifications.

## Decisions

- **Manifest.** Served dynamically at `/manifest.webmanifest` so its name follows
  `SITE_TITLE`; it is a public route so the browser can read it. Icons are PNG
  (192/512, maskable-safe) plus an SVG favicon.
- **Service worker.** `public/sw.js` is served from the root so its scope is the
  whole app. It precaches the CSS/JS/icons and serves same-origin `/assets/*`
  cache-first, passing everything else through to the network. Registered by
  `public/assets/app.js`.
- **Version.** `VersionService` reads the current version from the `VERSION`
  file. `LatestReleaseProvider` is an interface; `GitHubLatestReleaseProvider`
  reads the latest release tag (best-effort, short timeout) only when
  `UPDATE_CHECK_ENABLED` is true, returning null on any failure or when disabled.
  `updateAvailable()` compares with `version_compare`.
- **Endpoint + footer.** `GET /version` returns `{version, updateAvailable,
  latest}`. The footer renders the current version server-side; `app.js` calls
  `/version` and appends an update note when one is available.
- **Default off.** The update check defaults to disabled so a fresh install makes
  no outbound calls; the maintainer enables it. Version display always works.

## Risks / Trade-offs

- **Service worker caching** can serve a stale asset after an update; the cache
  is versioned and cleared on activate, and assets are small.
- **Update check against a third party** is opt-in and fails silent, so it can
  never break the footer or leak calls by default.
