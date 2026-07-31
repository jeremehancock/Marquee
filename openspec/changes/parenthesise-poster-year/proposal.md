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

- The gallery caption (and its hover tooltip) shows the title **Plex reports for
  the item**, recorded at import, rather than one reconstructed from the poster's
  filename. The filename is a sanitised copy — punctuation is flattened and the
  library name is appended — so rebuilding a title from it loses information the
  database already holds intact.
- The caption shows a movie's or TV show's known release year in parentheses,
  appending it only when the title does not already carry it.
- **TV seasons show no year.** A season record holds its show's year, so
  "Breaking Bad - Season 5 (2008)" would date a 2012 season to the year the show
  began. A year that is part of the show's own name ("Lucky (2026) - Season 1")
  still shows, because Plex reported it as the title.
- The mobile action tray heading uses the same parenthesised-year title.
- The change-poster modal/tray heading uses the same parenthesised-year title.
- The action tray and change-poster modal headings **no longer append the source
  library** in parentheses. Caption, tray, and modal now all show one identical
  title.
- Posters with no known year (collections, non-Plex uploads) are unchanged.
- A poster with no Plex mapping keeps today's filename-derived title, so an
  unlinked or hand-placed file still shows something.

Not changing, and deliberately so: **nothing about how posters are stored**. No
filename is renamed, no database column is added, no row is written, no
migration runs. Both the title and the year come from columns `plex_items`
already carries, read at render time. Sort order, search, Find Posters lookups,
and the derived `title()` those depend on are all untouched. This is
presentation only.

Two consequences worth naming:

- **Punctuation comes back.** The filename sanitiser replaces every run of
  non-alphanumerics with `_`, so today's captions read "Marvel s Agents of S H I
  E L D" and "Spider-Noir B W". Reading the stored title restores them. This
  touches more captions than the year does.
- In the All view two same-titled posters from different libraries no longer
  differ in the tray heading. The badge and the actions themselves still target
  the right poster, and the library was noise for every other poster, so the
  trade favours the common case.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the caption/tooltip requirement changes source — the title
  comes from Plex rather than from the filename — and gains parenthesised-year
  rendering; the action-sheet requirement drops the parenthesised library and
  adopts the same title as the caption.
- `poster-editing`: gains a requirement that the change-poster dialog names the
  poster with that same title — nothing today says what its heading holds.

## Impact

Read-only with respect to stored data — no schema change, no migration, no
backfill, no rename.

- `src/Poster/Poster.php` — title rendering. The library-token and year-token
  stripping helpers are deleted rather than extended: with the stored title there
  is no appended library to remove and no flattened year to move.
- `src/Database/PlexItemRepository.php` — gains `titlesForCategory()` and
  `yearsForCategory()`; `librariesForCategory()` is deleted as its only caller
  goes away.
- `src/Controller/GalleryController.php` — passes those two maps to the template.
- `templates/partials/gallery_results.html.twig` — caption, tooltip, sheet title,
  the change-poster `data-title`, and the delete-confirm and `alt` text, which
  still name the raw filename-derived title.
- `public/assets/gallery.js` — the tray's `data-sheet-title` fallback, if caption
  and sheet titles become identical.
- `tests/Unit/Poster/PosterTest.php` — existing caption/sheet expectations.
- No database migration: both columns already exist and are already populated.
