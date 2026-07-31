## Context

Poster titles are currently derived from the filename.
`ImportService::deriveFilename()` builds `"<title> (<year>) [<library>].<ext>"`,
then `FilesystemPosterStorage::sanitizeFilename()` replaces every run of
`[^A-Za-z0-9._-]` with `_`, so the punctuation is gone by the time the file lands
on disk: `Louis_and_the_Nazis_2003_Movies.jpg`. `Poster::title()` turns `.` and
`_` back into spaces, giving "Louis and the Nazis 2003 Movies".
`Poster::trailingLibraryToken()` then removes the library by comparing against
the known name from `plex_items.library_title`.

**The filename is a lossy copy of something the database holds intact.** The same
import writes `title: $item->displayTitle()` to `plex_items`, unsanitised — for a
season, `parentTitle . ' - ' . title`. So a show Plex calls `Lucky (2026)` is
stored exactly that way, while its file is `Lucky_2026_TV_Shows.jpg` and its
season's file is `Lucky_2026_-_Season_1_TV_Shows.jpg`.

That loss is what makes filename-derived titles unfixable in the general case. In
`Lucky 2026 - Season 1` the `2026` is a flattened `(2026)` belonging to the show's
name; in `Class of 2026 - Season 1` it is part of the title. The two strings are
identical in shape, so no rule over the *filename* can separate them — but the
stored titles, `Lucky (2026) - Season 1` and `Class of 2026 - Season 1`, are not
ambiguous at all.

`plex_items` also stores `year` for every mapped item. `deriveFilename()` writes a
year into the filename only for movies, but the column is populated for shows and
seasons too (a season deliberately carries its *show's* year, since Plex reports
none on a season node).

So the design stops reconstructing and starts reading: title and year both come
from the row, and the filename is used only when there is no row.

## Goals / Non-Goals

**Goals:**

- Render a known release year as "(2003)" in the caption, its tooltip, the mobile
  action tray heading, and the change-poster dialog heading — for every poster
  that has a stored year, whether or not its filename carries one.
- Drop the source library from the tray and dialog headings.
- Keep `title()`, filenames, sort keys, and Find Posters lookups untouched.

**Non-Goals:**

- **Any change to stored state.** No schema change, no migration, no backfill, no
  rename, no write of any kind. The year is read at render time from a column
  that already exists and is already populated.
- Changing how `ImportService` builds filenames. Movie filenames keep their
  `(year)`; show and season filenames keep having none. The caption is allowed to
  show a year the filename does not, because the caption describes the *item*,
  not the file.
- Showing a season's own air year. A season row stores its show's year by design;
  the caption shows what is stored.
- Adding a year where none is known. Collections and non-Plex uploads are
  unchanged.

## Decisions

### Read the title Plex gave us; never parse one back out of a filename

`captionTitle(?string $plexTitle, ?int $year)` is now two steps:

1. Use `$plexTitle` when there is one, else fall back to `title()`.
2. Append `' (' . $year . ')'` when a year is known and the title does not
   already contain `'(' . $year . ')'`.

That is the whole rule. No token stripping, no digit matching, no media-type
branching — so `trailingLibraryToken()` and the trailing-year helper are both
deleted rather than extended.

Each piece of the old machinery disappears because the stored title never needed
it: the library was appended to the *filename* only, so there is nothing to
remove; the year was baked into *movie filenames* only, so there is nothing to
move; and the sanitiser never ran, so the punctuation is already right.

The containment check in step 2 is what handles a Plex title that already names
its year — `Lucky (2026)`, and the `Lucky (2026) - Season 1` built from it. It
matches the parenthesised form specifically, not a bare `2026`, so *Class of 2026*
correctly becomes `Class of 2026 (2026)`: the digits in the title are not in
parentheses, so they are not mistaken for the year already being present.

**Alternative rejected — patch the filename rule instead.** Suppressing the
append whenever the year appears anywhere in the derived title fixes `Lucky` and
breaks `Class of 2026`, which would silently lose its year. Wrapping the embedded
occurrence in place fixes `Lucky` and turns `Class of 2026 - Season 1` into
`Class of (2026) - Season 1`. Both are guesses at which digits mean what, and the
information needed to stop guessing is already in the database.

**Alternative rejected — store a rendered display title alongside the file.** A
new column and a backfill for something already on hand, and storage explicitly
does not change here.

### The filename stays the fallback, not the source

A poster with no `plex_items` row — an unlinked file, or one placed by hand —
still gets `title()`. It has no library name and no year either, so it renders
exactly as it does today. An empty stored title is treated as absent for the same
reason, so a blank Plex title cannot produce a blank caption.

### Fold `sheetTitle()` into `captionTitle()`

Once the tray heading drops the library and parenthesises the year, it is
character-for-character the caption. Keeping two methods that must return the
same string is a bug waiting to happen, so `sheetTitle()` goes and every surface
calls `captionTitle(?string $plexTitle, ?int $year)` — including the delete
confirmation and the image `alt` text, which still name the raw filename-derived
title and would otherwise be the last places the library token survives.

Consequences: the `data-sheet-title` attribute on the figcaption is removed, and
`gallery.js` reads the caption's own text (it already falls back to
`caption.textContent.trim()`). The card's `data-title`, which feeds the
change-poster dialog, switches from `sheetTitle` to the same `captionTitle`.

**Alternative rejected:** keep `sheetTitle()` as a thin alias. It would leave two
names for one concept and invite them to drift apart again.

### Carry title and year to Twig as two filename-keyed maps

`titlesForCategory()` and `yearsForCategory()` each return
`array<filename, value>`, matching the shape of the `filenamesForCategory()` and
`addedAtForCategory()` methods already on the repository. `GalleryController`
passes them as `plex_titles` and `plex_years`.

`librariesForCategory()` is **deleted**, not left behind: the gallery was its only
caller, and the stored title never carried a library to strip. Leaving it would be
dead code that reads like a live rule.

Two small indexed reads per category per page render, not per poster.

**Alternative rejected:** one query returning both columns as a nested array. It
saves a round-trip on a page that already does several, at the cost of a row shape
unlike every other method here.

### Truncation stays as it is

The caption gains two characters. It is already clamped to one line with an
ellipsis, so a title that was borderline simply truncates two characters earlier.
No CSS change.

## Risks / Trade-offs

- **A same-titled poster from two libraries is now ambiguous in the tray and
  dialog** → Accepted and explicit in the proposal. The All-view badge still
  names the type, and every action in the tray targets the poster by filename, so
  the ambiguity is cosmetic. The library was noise on every other poster.

- **A movie whose Plex year changed after import** → The stored title and the
  stored year are updated together by the same import, so they cannot disagree.
  The stale year stranded in the filename is no longer read at all. This is a
  hazard the filename-derived rule had and this one does not.

- **Captions change for far more posters than the year alone** → Every title
  containing an apostrophe, ampersand, colon, period, or accent renders
  differently once the sanitiser's output stops being the source. This is a fix,
  but it is a broad visible change and the `:dev` validation should look for it
  rather than only at years.

- **The caption no longer matches the filename on disk** → It already did not:
  the library token was being stripped. Nothing keys off the caption. Search
  matches `title()`, Find Posters and every action use the filename and category,
  and the Orphans page lists filenames — all unchanged.

- **A poster whose row is missing or whose stored title is empty** → Falls back to
  `title()`, i.e. exactly today's rendering, library token and all. Degrades to
  the old behaviour rather than to a blank caption.

- **Orphans and Wall pages render posters too** → They do not call
  `captionTitle()` or `sheetTitle()` today, so they are unaffected. Worth a grep
  during implementation to confirm nothing new has started using them.

- **Search can now match text the caption does not show, and vice versa** →
  Pre-existing (search has always matched `title()`), and out of scope here.
  Worth its own change if the mismatch proves annoying — a title with punctuation
  is the case to watch.
