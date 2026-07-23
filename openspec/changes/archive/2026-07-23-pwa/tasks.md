## 1. Installability

- [x] 1.1 Marquee icons (192/512, apple-touch) + `favicon.svg`.
- [x] 1.2 `ManifestController` at `/manifest.webmanifest` (uses `SITE_TITLE`); public route.
- [x] 1.3 Layout: manifest link, theme-color, icons, install meta tags.

## 2. Offline assets

- [x] 2.1 `public/sw.js` (precache + cache-first for `/assets/*`).
- [x] 2.2 `public/assets/app.js` registers the service worker.

## 3. Version & update check

- [x] 3.1 `Version\VersionService`, `Version\LatestReleaseProvider` +
      `Version\GitHubLatestReleaseProvider` (opt-in, best-effort).
- [x] 3.2 `VersionController` at `/version`; container wiring + Twig `app_version` global.
- [x] 3.3 Footer shows the version; `app.js` appends an update note when available.

## 4. Verify

- [x] 4.1 Unit: version comparison (newer/equal/older/none) via a fake provider.
- [x] 4.2 Functional: `/manifest.webmanifest` returns the manifest (public);
      `/version` returns the current version.
- [x] 4.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 4.4 `openspec validate pwa` passes.
