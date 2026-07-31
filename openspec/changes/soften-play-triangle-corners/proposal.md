## Why

The play triangle in the Marquee mark is a hard-edged polygon — three needle-sharp
points, the tip especially. Every other shape in the logo is softly rounded (the
dark ground, all three posters), so the triangle reads as the one unfinished
element. Taking the edge off it makes the mark feel deliberate and finished at
every size it appears: browser tab, home-screen tile, header.

## What Changes

- Round the corners of the play triangle in the full mark (`logo.svg` and its
  inline copy in the topbar) with a small, even fillet — enough to kill the
  needle points, not enough to read as a different shape.
- Apply the same treatment, proportionally, to the simplified favicon mark.
- Regenerate the raster icon set from the updated full mark so the PWA tiles,
  maskable tiles, and apple-touch icon match the SVGs.
- No palette, geometry, or layout changes beyond the triangle's corners.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `application-shell`: adds a requirement that the brand mark drawn inline in the
  page header matches `logo.svg`. The header copy is hand-duplicated today with
  nothing enforcing it, and editing the logo without the template is exactly how
  this change could half-ship — so the guarantee is worth pinning down while
  we're here.

The `pwa` requirements around distinct `any`/`maskable` icon entries and maskable
safe-zone art are unchanged, and must continue to hold once the icons are
regenerated.

## Impact

- Assets: `public/assets/logo.svg`, `public/assets/favicon.svg`,
  `public/assets/icons/` (`icon-192.png`, `icon-512.png`, `icon-192-maskable.png`,
  `icon-512-maskable.png`, `apple-touch-icon.png`).
- Templates: `templates/layout.html.twig` carries an inline duplicate of the full
  mark for the topbar; it must be updated in lockstep with `logo.svg` or the
  header and the favicon will disagree.
- Tests: one new assertion in `tests/Functional/ApplicationShellTest.php` tying
  the header's inline mark to `logo.svg`. Existing tests assert only that the
  brand markup and the `/assets/favicon.svg` link are present, not path geometry,
  so they should keep passing untouched.
- No PHP source, no manifest, and no CSS changes are expected.
- The logo design is judged by eye, not by spec; nothing here is a normative
  requirement change.
