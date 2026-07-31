## Why

A movie's release year currently trails its caption as a bare number — "Louis
and the Nazis 2003" — so it reads as part of the title rather than as metadata.
Parenthesising it ("Louis and the Nazis (2003)") is how every media app writes a
release year, and it is what the filename already said before import flattened
the punctuation away.

The mobile action tray and the change-poster modal have the same problem, plus a
second one: they append the source library in parentheses ("… 2003 (Movies)").
Once the year is parenthesised too, that reads as two competing parentheticals,
and the library is redundant there anyway — the user just tapped that poster in a
view they chose.

## What Changes

- The gallery caption (and its hover tooltip) shows a poster's known release year
  in parentheses — movies, TV shows, and TV seasons alike — instead of as a
  trailing bare number or not at all.
- The mobile action tray heading uses the same parenthesised-year title.
- The change-poster modal/tray heading uses the same parenthesised-year title.
- The action tray and change-poster modal headings **no longer append the source
  library** in parentheses. Caption, tray, and modal now all show one identical
  title.
- Posters with no known year (collections, non-Plex uploads) are unchanged.

Not changing, and deliberately so: **nothing about how posters are stored**. No
filename is renamed, no database column is added, no row is written, no
migration runs. The year comes from the `year` column `plex_items` already
carries, read at render time. Sort order, Find Posters lookups, and the derived
`title()` those depend on are all untouched. This is presentation only.

Accepted tradeoff: in the All view two same-titled posters from different
libraries no longer differ in the tray heading. The badge and the actions
themselves still target the right poster, and the library was noise for every
other poster, so the trade favours the common case.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the caption/tooltip requirement gains parenthesised-year
  rendering; the action-sheet requirement drops the parenthesised library and
  adopts the same title as the caption.
- `poster-editing`: gains a requirement that the change-poster dialog names the
  poster with that same title — nothing today says what its heading holds.

## Impact

Read-only with respect to stored data — no schema change, no migration, no
backfill, no rename.

- `src/Poster/Poster.php` — title rendering for caption and sheet.
- `src/Controller/GalleryController.php` and `src/Database/PlexItemRepository.php`
  — the per-poster year must reach the template the way the library name already
  does.
- `templates/partials/gallery_results.html.twig` — caption, tooltip, sheet title,
  and the change-poster `data-title`.
- `public/assets/gallery.js` — the tray's `data-sheet-title` fallback, if caption
  and sheet titles become identical.
- `tests/Unit/Poster/PosterTest.php` — existing caption/sheet expectations.
- No database migration: the year is already stored on `plex_items`.
