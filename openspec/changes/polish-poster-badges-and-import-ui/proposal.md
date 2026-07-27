## Why

Several small rough edges in the gallery and import screens undercut an
otherwise cohesive look. The All-view type badges use generic web colors that
clash with Marquee's Plex-ish gold-on-slate palette; the hover action buttons
give no feedback so it is hard to tell what you are about to click; poster
captions repeat the type already shown by the badge and the active tab; and the
import screen has two presentation glitches (an unpunctuated library type and
off-center pill text). None change behavior — they make the UI read as one
deliberate design.

## What Changes

- Recolor the four All-view type badges (Movie, TV Show, TV Season, Collection)
  to sit within the app's theme tokens instead of the current stock
  blue/green/amber/purple, keeping the four types distinguishable at a glance.
- Give the hover action buttons (Change poster, Send/Fetch, Download, Copy URL,
  Full screen, Delete) visible hover/focus states so the button under the
  pointer is unmistakable before clicking.
- Stop repeating the poster type in the caption: drop the trailing bracketed
  library/type token (e.g. `[Movies]`) from the visible caption text, since the
  badge and the active tab already convey it. The full title is preserved in the
  hover tooltip and on disk — this is a display-only change.
- Truncate the poster caption to a single line with an ellipsis so a long title
  can never wrap and push the row of posters below it down; the caption is never
  wider than the poster above it.
- Replace native browser `title=` tooltips with a single themed, custom tooltip
  used everywhere a tooltip appears (poster captions, pagination controls, the
  Find Posters preview, the wall exit). The caption's tooltip shows the full,
  untruncated title.
- On the import library picker, wrap the library's type label in parentheses so
  it reads `Library Name (Movies)` / `Library Name (TV)` instead of a bare
  trailing word.
- Horizontally center the text inside the Step 1 content-type pill buttons.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-library`: the type-badge styling is themed to the app palette; poster
  action controls gain a required hover/focus affordance; the poster caption
  omits the redundant trailing library/type token, truncates to a single line
  with an ellipsis, and exposes the full title through the shared custom tooltip.
- `plex-import`: the library picker presents each library's type in parentheses,
  and the Step 1 content-type controls center their label text.
- `application-shell`: tooltips across the shared layout use one themed custom
  tooltip component instead of the browser's native `title=` tooltip.

## Impact

- Templates: [templates/partials/gallery_results.html.twig](templates/partials/gallery_results.html.twig)
  (caption, pagination), [templates/plex.html.twig](templates/plex.html.twig)
  (library type label), [templates/orphans/_results.html.twig](templates/orphans/_results.html.twig)
  (caption), [templates/gallery.html.twig](templates/gallery.html.twig) (preview
  hint), [templates/wall.html.twig](templates/wall.html.twig) (exit hint + load
  shared script) — native `title=` tooltips become `data-tooltip=`.
- Styles: [public/assets/app.css](public/assets/app.css) — `.card__badge--*`
  tints, `.card__actions .btn` hover/focus, `.choice span` text centering,
  `.card__caption` single-line ellipsis, and a new `.tooltip` component;
  [public/assets/wall.css](public/assets/wall.css) — the same `.tooltip` styles.
- JS: [public/assets/app.js](public/assets/app.js) — a small delegated tooltip
  controller (one shared floating element) covering static, AJAX, and
  Alpine-rendered `[data-tooltip]` targets.
- PHP: [src/Poster/Poster.php](src/Poster/Poster.php) — add a caption-only
  display title that strips the trailing bracket, leaving `title()` (used for
  sorting and the tooltip) untouched.
- No changes to on-disk filenames, routing, data, or import behavior. Purely
  presentational; no migrations.
