## Why

Four small rough edges. A poster caption raises a tooltip even when the caption
already shows the whole title, so hovering a short title pops a bubble that just
repeats what is on screen. The "no posters available" message ends in a dangling
"for it". Chrome logs a deprecation warning on every page load. And
`POSTER_SOURCE_URL` is documented as a user setting for a service the user is not
meant to repoint.

## What Changes

- A poster caption's tooltip is shown only when the caption is actually
  truncated. A title that fits shows no tooltip and no `help` cursor. This
  applies to the gallery and the orphans view, which share the caption
  component; every other tooltip host is unaffected.
- The no-artwork search message becomes "This title was found, but no posters
  are available."
- The page head gains the standard `mobile-web-app-capable` meta tag alongside
  the existing `apple-mobile-web-app-capable`, clearing the Chrome deprecation
  warning without dropping iOS home-screen support.
- `POSTER_SOURCE_URL` is removed from the README configuration table. The
  environment variable keeps working exactly as it does now — it is simply no
  longer presented to users as a setting to configure.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the shared tooltip requirement gains a rule that a host
  whose tooltip only restates truncated text shows nothing when the text is not
  truncated, and that its `help` cursor follows the same condition.
- `poster-sources`: the no-artwork outcome's wording, and the documentation
  requirement that currently mandates `POSTER_SOURCE_URL` stay in the
  configuration table.
- `pwa`: the installability requirement covers the standard web-app-capable
  meta tag, not only the Apple-prefixed one.

## Impact

- `public/assets/app.js` — tooltip module gains a truncation gate.
- `public/assets/app.css` — `.card__caption` cursor becomes conditional.
- `templates/partials/gallery_results.html.twig`,
  `templates/orphans/_results.html.twig` — caption tooltip hosts opt into the
  gate.
- `templates/layout.html.twig` — added meta tag.
- `src/Controller/ChangePosterController.php` — message wording.
- `README.md` — configuration table row removed.
- `tests/Functional/PwaTest.php` — asserts the new meta tag.

No behavior changes for touch users, no configuration changes, no migration.
