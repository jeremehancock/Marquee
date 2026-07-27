## 1. Palette tokens

- [x] 1.1 In `public/assets/app.css`, update the brand tokens to the Plex-ish
      scheme: `--accent: #e5a00d`, lift `--bg` to a warm dark (`#1c1e24`), raise
      `--surface` to the Plex panel gray (`#282a2d`), warm `--border` (`#34373d`),
      and add `--accent-dim` (`#b9800c`) for depth in the logo.
- [x] 1.2 Give the topbar a `--surface` background (Plex-style layered bar);
      cards already use `--surface`, so the palette now reads as layered dark
      rather than flat black.
- [x] 1.3 Update `theme-color` / `background_color` references
      (`templates/layout.html.twig` meta and `ManifestController`) to the new
      `--bg` (`#1c1e24`) so the PWA chrome matches.

## 2. The logo mark

- [x] 2.1 Author the full mark as SVG (`public/assets/logo.svg`): symmetric
      fanned stack (front poster upright in `--accent`; two rear posters rotated
      ~±10°, offset out/up, in `--accent-dim`) with a dark play triangle knocked
      out of the front poster, on a dark rounded ground. (Bulbs were dropped
      during build as overkill — see proposal.)
- [x] 2.2 Author a simplified small-size variant for `public/assets/favicon.svg`:
      a single poster + play, no fan, legible at 16px.
- [x] 2.3 Verify both variants render cleanly (rsvg) at 16 / 32 / 256 px before
      generating raster assets.

## 3. Icon set + header

- [x] 3.1 Add the full mark inline to the topbar in
      `templates/layout.html.twig`, left of the site title inside the `.brand`
      link; size/align via `.brand__logo` in `app.css`.
- [x] 3.2 Regenerate raster icons from the full mark: `public/assets/icons/`
      `icon-192.png` and `icon-512.png` (edge-tight, purpose `any`), plus
      `icon-192-maskable.png` and `icon-512-maskable.png` (mark scaled ~72% and
      centered inside the maskable safe zone, full-bleed dark ground). Verified
      the maskable art survives a simulated circular crop un-clipped.
- [x] 3.3 Regenerate `apple-touch-icon.png` (180×180) with the full mark on a
      solid full-bleed dark ground and no transparency.

## 4. Manifest split

- [x] 4.1 In `src/Controller/ManifestController.php`, replace the single
      `"any maskable"` icon list with distinct entries: `icon-192.png` /
      `icon-512.png` as purpose `any`, and `icon-192-maskable.png` /
      `icon-512-maskable.png` as purpose `maskable`.

## 5. Verify

- [x] 5.1 Extend `tests/Functional/PwaTest.php` to assert the icon list contains
      a distinct `any` entry and a distinct `maskable` entry, and that no entry
      carries both purposes. Updated `ApplicationShellTest` for the new brand
      markup (logo + title span).
- [ ] 5.2 Manually confirm in a browser: favicon shows in the tab; the header
      mark renders next to the title; installing the PWA shows the logo
      un-clipped under the OS mask; the apple-touch icon looks correct on an iOS
      add-to-home. (Not done here — needs a live browser / device; automated
      render checks and the circular-crop simulation stand in for now.)
- [x] 5.3 Run `composer` checks (PHP-CS-Fixer, PHPStan, PHPUnit) — all pass
      (172 tests, PHPStan clean, CS clean).
