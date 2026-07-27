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
- Stop repeating the library in the caption: Plex import bakes the source library
  name into the filename, so it shows up at the end of the caption (e.g. "Louis
  and the Nazis 2003 Movies"). Using each poster's known library name, drop that
  trailing token from the caption and its tooltip, keeping the rest of the title
  (year included). Non-Plex posters keep their full title. Display-only.
- Truncate the poster caption to a single line with an ellipsis so a long title
  can never wrap and push the row of posters below it down; the caption is never
  wider than the poster above it.
- In the mobile action sheet, keep the library but show it in parentheses (e.g.
  "Louis and the Nazis 2003 (Movies)"), so a tapped poster still names its
  library even though the caption drops it.
- Replace native browser `title=` tooltips with a single themed, custom tooltip
  used everywhere a tooltip appears (poster captions, pagination controls, the
  Find Posters preview). The caption's tooltip shows the full (library-stripped)
  title when the caption is truncated.
- Remove the Poster Wall's on-screen exit control: the wall is meant for
  unattended display on a monitor, so it should show only posters.
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
  omits the baked-in library token (via the known library name) and truncates to
  a single line with an ellipsis; and the mobile action sheet keeps the library
  in parentheses.
- `plex-import`: the library picker presents each library's type in parentheses,
  and the Step 1 content-type controls center their label text.
- `application-shell`: tooltips across the shared layout use one themed custom
  tooltip component instead of the browser's native `title=` tooltip.
- `poster-wall`: the wall drops its on-screen exit control so it shows only
  posters for unattended display.

## Impact

- Templates: [templates/partials/gallery_results.html.twig](templates/partials/gallery_results.html.twig)
  (caption, sheet title, pagination), [templates/plex.html.twig](templates/plex.html.twig)
  (library type label), [templates/orphans/_results.html.twig](templates/orphans/_results.html.twig)
  (caption tooltip), [templates/gallery.html.twig](templates/gallery.html.twig)
  (preview hint) — native `title=` tooltips become `data-tooltip=`;
  [templates/wall.html.twig](templates/wall.html.twig) — exit control removed.
- Styles: [public/assets/app.css](public/assets/app.css) — `.card__badge--*`
  tints, `.card__actions .btn` hover/focus, `.choice span` text centering,
  `.card__caption` single-line ellipsis, and a new `.tooltip` component;
  [public/assets/wall.css](public/assets/wall.css) — `.wall__exit` styles removed.
- JS: [public/assets/app.js](public/assets/app.js) — a small delegated tooltip
  controller (one shared floating element) covering static, AJAX, and
  Alpine-rendered `[data-tooltip]` targets; [public/assets/gallery.js](public/assets/gallery.js)
  — the mobile sheet reads `data-sheet-title` (library in parentheses).
- PHP: [src/Poster/Poster.php](src/Poster/Poster.php) — `captionTitle()` and
  `sheetTitle()` take the poster's library name to drop or parenthesise the baked-in
  token; `title()` (sorting) untouched. [src/Database/PlexItemRepository.php](src/Database/PlexItemRepository.php)
  gains `librariesForCategory()`, wired through [src/Controller/GalleryController.php](src/Controller/GalleryController.php).
- No changes to on-disk filenames, routing, or import behavior; the library map
  is read from the existing `plex_items` table. Purely presentational; no
  migrations.
