## 1. Installability

- [ ] 1.1 Marquee icons (192/512, apple-touch) + `favicon.svg`.
- [ ] 1.2 `ManifestController` at `/manifest.webmanifest` (uses `SITE_TITLE`); public route.
- [ ] 1.3 Layout: manifest link, theme-color, icons, install meta tags.

## 2. Offline assets

- [ ] 2.1 `public/sw.js` (precache + cache-first for `/assets/*`).
- [ ] 2.2 `public/assets/app.js` registers the service worker.

## 3. Version & update check

- [ ] 3.1 `Version\VersionService`, `Version\LatestReleaseProvider` +
      `Version\GitHubLatestReleaseProvider` (opt-in, best-effort).
- [ ] 3.2 `VersionController` at `/version`; container wiring + Twig `app_version` global.
- [ ] 3.3 Footer shows the version; `app.js` appends an update note when available.

## 4. Verify

- [ ] 4.1 Unit: version comparison (newer/equal/older/none) via a fake provider.
- [ ] 4.2 Functional: `/manifest.webmanifest` returns the manifest (public);
      `/version` returns the current version.
- [ ] 4.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 4.4 `openspec validate pwa` passes.
