## Context

Poster titles are derived from the filename, not from stored metadata.
`ImportService::deriveFilename()` builds `"<title> (<year>) [<library>].<ext>"`,
then `FilesystemPosterStorage::sanitizeFilename()` replaces every run of
`[^A-Za-z0-9._-]` with `_`, so the punctuation is gone by the time the file lands
on disk: `Louis_and_the_Nazis_2003_Movies.jpg`. `Poster::title()` turns `.` and
`_` back into spaces, giving "Louis and the Nazis 2003 Movies".

The library token is already recovered rather than guessed:
`Poster::trailingLibraryToken()` takes the *known* library name from
`plex_items.library_title`, normalises it through the same sanitise+render path,
and strips it only if the title actually ends with it. `captionTitle()` drops the
token, `sheetTitle()` re-renders it as "(Movies)". The map reaches Twig as
`plex_libraries[category][filename]`, built by
`PlexItemRepository::librariesForCategory()`.

`plex_items` already stores `year` for every mapped item. `deriveFilename()`
writes a year into the filename only for movies, but the column is populated for
shows and seasons too (a season deliberately carries its *show's* year, since
Plex reports none on a season node).

This matters because it means the filename is an unreliable source for the year —
present for movies, absent for shows — while the database is reliable for all
three. The design reads the year rather than parsing it back out of the title.

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

### Take the year from stored metadata, never from a digit pattern

`captionTitle(?string $libraryTitle, ?int $year)` renders in three steps:

1. Strip the trailing library token, exactly as today (known name, normalised the
   same way, stripped only if the title really ends with it).
2. If a year is known, strip a trailing `' ' . $year` from what remains — this
   un-does what `deriveFilename()` baked into movie filenames, and is a no-op for
   shows and seasons, whose filenames never had one.
3. If a year is known, append `' (' . $year . ')'`.

Strip-then-append is what makes one rule cover both cases. A movie's year is
moved into parentheses; a show's is added. Neither branch needs to know which
media type it is looking at, so the rule cannot drift out of sync with
`deriveFilename()` if that ever changes.

The alternative — matching `/\s(\d{4})$/` on the title — is one line and wrong on
this user's actual library: the show *1883* and its seasons would render as
"(1883)", and "Blade Runner 2049" would become "Blade Runner (2049)". Comparing
against the known value fails closed instead: no stored year means nothing is
touched, and digits that are not the year are simply left in the title where they
belong.

The step-2 match requires the leading space (`' ' . $year`, mirroring
`trailingLibraryToken()`), so a title that *is* just its year cannot be stripped
to nothing.

**Alternative rejected:** store a rendered display title alongside the file. That
is a new column and a backfill for something fully derivable from data already on
hand — and the user's constraint is explicitly that storage does not change.

### Fold `sheetTitle()` into `captionTitle()`

Once the tray heading drops the library and parenthesises the year, it is
character-for-character the caption. Keeping two methods that must return the
same string is a bug waiting to happen, so `sheetTitle()` goes and every surface
calls `captionTitle(?string $libraryTitle, ?int $year)`.

Consequences: the `data-sheet-title` attribute on the figcaption is removed, and
`gallery.js` reads the caption's own text (it already falls back to
`caption.textContent.trim()`). The card's `data-title`, which feeds the
change-poster dialog, switches from `sheetTitle` to the same `captionTitle`.

**Alternative rejected:** keep `sheetTitle()` as a thin alias. It would leave two
names for one concept and invite them to drift apart again.

### Carry the year to Twig the way the library name already travels

Add `PlexItemRepository::yearsForCategory(string $category): array<string, int>`
next to `librariesForCategory()`, and a `plex_years` template variable next to
`plex_libraries` in `GalleryController`. Two queries per category instead of one;
both are small indexed reads over `plex_items` and run once per page render, not
per poster.

**Alternative rejected:** one query returning both columns. It would change the
shape of `librariesForCategory()`, which has its own tests and a second caller
risk, for one saved round-trip on a page that already does several.

### Truncation stays as it is

The caption gains two characters. It is already clamped to one line with an
ellipsis, so a title that was borderline simply truncates two characters earlier.
No CSS change.

## Risks / Trade-offs

- **A same-titled poster from two libraries is now ambiguous in the tray and
  dialog** → Accepted and explicit in the proposal. The All-view badge still
  names the type, and every action in the tray targets the poster by filename, so
  the ambiguity is cosmetic. The library was noise on every other poster.

- **A movie whose Plex year changed after import** → Import updates the stored
  year while the filename keeps the old one, so step 2 finds no match and step 3
  appends the new year: "Some Film 2002 (2003)". Visibly odd, but it is the
  honest read of a genuine disagreement between file and metadata, and it
  resolves itself the next time the poster is re-imported. Worth watching for
  during the `:dev` validation.

- **A title that legitimately ends in its own release year** (e.g. a film called
  "2003" released in 2003 → filename "2003 (2003) [Movies]" → title "2003 2003")
  → Step 2 strips one token and step 3 re-adds it: "2003 (2003)". Correct. The
  leading-space requirement means a bare "2003" with stored year 2003 becomes
  "2003 (2003)" rather than "(2003)".

- **The caption can now show a year the filename does not** (every TV show and
  season) → Intended, and the reason the strip step exists rather than a
  reformat-in-place. The Orphans page keys off filenames and does not call
  `captionTitle()`, so nothing that matches files to records is affected.

- **Orphans and Wall pages render posters too** → They do not call
  `captionTitle()` or `sheetTitle()` today, so they are unaffected. Worth a grep
  during implementation to confirm nothing new has started using them.
