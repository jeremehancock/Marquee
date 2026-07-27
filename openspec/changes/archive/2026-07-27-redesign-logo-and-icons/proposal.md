## Why

Marquee's current icon is a flat gold rectangle that reads as a generic "poster"
and leaves the product's most distinctive asset — its name — unused. The header
has no brand mark at all (the topbar is text-only). A single, cohesive logo that
leans lightly into the "marquee" (theatre sign) idea gives the app personality
and anchors a consistent icon system across the favicon, PWA tiles, and header.

At the same time the web app manifest tags both PNGs `"any maskable"`, which is
an anti-pattern: a maskable icon needs padding into a safe zone so the OS can
crop it, and the same padded art looks shrunken when used as `any`. Fixing this
is the one genuinely behavioural part of the change.

## What Changes

- Introduce a new logo — a symmetric fanned stack of posters in Plex-style gold
  with a play triangle knocked out of the front poster (a refined nod to the
  Posteria heritage), on a dark rounded ground. Restrained and clean; the
  "marquee" identity is carried by the name and the cinematic gold-on-dark
  palette rather than a literal theatre sign. (An earlier crown-of-bulbs idea was
  explored and dropped as overkill.)
- Shift the palette to a Plex-ish scheme: punchier brand gold (`#e5a00d`), a
  slightly lifted warm-dark background, and a mid-gray surface tone for cards and
  the topbar.
- Add the logo to the left of the site title in the topbar (new — currently
  text-only), using an inline SVG so it can carry the brand mark and an optional
  subtle hover animation.
- Regenerate the full icon set from the new mark: `favicon.svg` (a simplified
  small-size variant), the `192`/`512` PNG tiles, a maskable-padded pair, and the
  apple-touch icon on a solid dark ground.
- Split the manifest's icon declarations into distinct `any` and `maskable`
  entries so installs pick the right art for each context.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `pwa`: The manifest must declare distinct `any` and `maskable` icon entries
  (rather than a single `"any maskable"` set), and the maskable art must keep the
  logo inside the maskable safe zone so it is not clipped when the OS masks it.

## Impact

- `pwa` spec: one new requirement for distinct `any`/`maskable` icons and safe-
  zone-correct maskable art. Existing manifest requirements (product-name naming,
  no-login availability) are unchanged.
- Code / assets: `public/assets/favicon.svg` (new simplified mark);
  `public/assets/icons/` (regenerated `icon-192.png`, `icon-512.png`, new
  `icon-192-maskable.png`, `icon-512-maskable.png`, updated `apple-touch-icon.png`);
  `src/Controller/ManifestController.php` (split icon purposes);
  `templates/layout.html.twig` (inline brand SVG left of the title, `theme-color`
  if the background token changes); `public/assets/app.css` (palette tokens).
- The logo design itself is a visual asset judged by eye, not by spec; only the
  manifest behaviour is captured as a requirement.
