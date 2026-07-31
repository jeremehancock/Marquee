## 1. The SVG marks

The mark exists in exactly eight places, verified by an exhaustive sweep of the
repo — nothing outside this list carries it (`live-tv.svg` is an unrelated Live TV
placeholder poster, and `login.html.twig` inherits the header from the shared
layout rather than repeating it). All eight change in this one commit:

| Artefact | Task |
| --- | --- |
| `public/assets/logo.svg` | 1.1 |
| `public/assets/favicon.svg` | 1.2 |
| inline `.brand__logo` copy in `templates/layout.html.twig` | 1.3 |
| `icon-192.png`, `icon-512.png` | 2.1 |
| `icon-192-maskable.png`, `icon-512-maskable.png` | 2.2 |
| `apple-touch-icon.png` | 2.3 |

- [x] 1.1 In `public/assets/logo.svg`, replace the play-triangle path data with
      the r=1.0 fillet from design.md, and drop the now-doubly-vestigial
      `stroke-linejoin="round"`:
      `M27.80 26.98 L27.80 37.02 Q27.80 38.80 29.32 37.88 L37.59 32.85 Q39.00 32.00 37.59 31.15 L29.32 26.12 Q27.80 25.20 27.80 26.98 Z`
- [x] 1.2 In `public/assets/favicon.svg`, same treatment at r=1.2:
      `M25.00 25.94 L25.00 38.06 Q25.00 40.00 26.74 39.13 L38.85 33.07 Q41.00 32.00 38.85 30.93 L26.74 24.87 Q25.00 24.00 25.00 25.94 Z`
- [x] 1.3 In `templates/layout.html.twig`, update the inline `.brand__logo` copy
      to match `logo.svg` exactly (same path data, same dropped attribute).
- [x] 1.4 Confirm no copy was missed — every artefact in the table above is
      updated, and `grep -rn "M27.8 25.2\|M25 24" --include="*.svg"
      --include="*.twig" --include="*.css" --include="*.js" --include="*.php"
      --include="*.md" . | grep -v vendor` returns nothing.
- [x] 1.5 Render `logo.svg` and `favicon.svg` at 16 / 36 / 256 px and check by
      eye that the tip is softened, the silhouette still reads as a play glyph,
      and nothing but the triangle moved.

## 2. Raster icon set

- [x] 2.1 Regenerate `icon-192.png` and `icon-512.png` straight from the new
      `logo.svg`: `rsvg-convert -w N -h N public/assets/logo.svg -o …`.
- [x] 2.2 Regenerate `icon-192-maskable.png` and `icon-512-maskable.png` by
      rendering the mark at `floor(N × 0.72)` (138 and 368 px) and centring it on
      a solid `#1c1e24` N×N ground — the scale is preserved exactly so the
      maskable safe zone is unchanged.
- [x] 2.3 Regenerate `apple-touch-icon.png` at 180×180, composited on `#1c1e24`
      with `-alpha off` so it carries no transparency.
- [x] 2.4 Diff each regenerated PNG against its committed predecessor
      (`magick compare -metric RMSE`) and confirm the changed pixels are confined
      to the triangle's three corners — a wrong scale factor or a dropped
      background flatten shows up here and nowhere else.
- [x] 2.5 Re-run the circular-crop simulation on both maskable icons to confirm
      the mark survives the OS mask un-clipped (`pwa` spec, safe-zone scenario).

## 3. Lock the header copy to the asset

- [x] 3.1 Add a test to `tests/Functional/ApplicationShellTest.php` that parses
      the shape geometry out of `public/assets/logo.svg` and asserts the rendered
      header's inline brand SVG declares exactly the same set — so editing the
      asset without the template fails the suite instead of shipping two
      different marks.
- [x] 3.2 Sanity-check the new test actually bites: temporarily perturb the path
      in `logo.svg`, confirm the test fails, then restore.

## 4. Verify

- [x] 4.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass.
- [x] 4.2 Check docs for staleness: `README.md`, `docs/`, and `CLAUDE.md`
      describe no logo geometry, so the expected outcome is "no edits needed" —
      confirm that explicitly rather than assuming it.
- [x] 4.3 Manually confirm in a browser: favicon in the tab, mark next to the
      title in the header, and the PWA install tile all show the softened
      triangle and agree with each other. (Validated by the maintainer against
      the `:dev` image built from 61f4929 — `bozodev/marquee:sha-61f4929`.)
