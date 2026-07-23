## Why

Marquee is used on phones and living-room screens as much as desktops. Making it
an installable Progressive Web App lets people add it to a home screen and open
it like a native app, and a service worker makes its assets load fast and work
offline. A version indicator with an optional update check rounds out the
polish.

## What Changes

- Add a web app manifest (named after `SITE_TITLE`), Marquee icons, and the meta
  tags that make the app installable.
- Add a service worker that caches the static assets (CSS/JS/icons) for fast,
  offline-tolerant loads.
- Show the current version in the footer, and — when enabled — check for a newer
  release and surface an "update available" note without blocking the page.

## Capabilities

### New Capabilities
- `pwa`: installable manifest and icons, an asset-caching service worker, and a
  version display with an optional update check.

### Modified Capabilities
<!-- None. -->

## Impact

- New: `public/assets/site` icons + `public/assets/favicon.svg`, `public/sw.js`,
  `public/assets/app.js`, `src/Version/**`, `src/Controller/ManifestController.php`,
  `src/Controller/VersionController.php`.
- Modified: `templates/layout.html.twig` (manifest link, theme-color, icons, SW
  registration, version footer), `AuthMiddleware` (manifest is public),
  container wiring (version + Twig global).
- Routes: `/manifest.webmanifest`, `/version`.
- Environment: `UPDATE_CHECK_ENABLED` (default off), `UPDATE_REPO`.
