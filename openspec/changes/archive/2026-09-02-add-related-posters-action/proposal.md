## Why

Making a show's seasons — or a film trilogy — share one artwork style means
looking at them together, and today that takes three deliberate steps: read the
title off the card, type it into the search box, switch to the All view. The
poster you are working from already knows what it is called. This turns that
into one action on the card.

The same work exposes a defect worth fixing on its own account: search matches
text the user is never shown. It matches the poster's *filename*, while the card
caption shows the title Plex recorded. For most titles the two agree closely
enough that nobody notices, but a title with an accented character does not
survive the filename at all — **"Amélie" cannot currently be found by searching
for "Amélie"** — and the library name that import appends to every filename is
silently searchable even though it appears nowhere on screen.

## What Changes

- **A new poster action, Related posters**, on every card and in the phone action
  sheet. It searches for everything sharing the poster's title and shows the
  results in the All view: a show together with all of its seasons, a movie
  together with the rest of its trilogy and its collection poster.
- **BREAKING (user-visible): Copy URL is removed** from the poster actions to make
  room. A poster's URL sits behind the session, so a copied link only ever worked
  in the browser of the person who copied it — it is the one action whose result
  could not be used anywhere else. Download is unaffected and remains the way to
  get a poster's image out of Marquee.
- **Search now matches the title shown on the card**, falling back to the
  filename-derived title only for a poster with no Plex record. Accented titles
  become findable by name; the appended library name stops being a silent match.
- **Import records a season's show title**, so a season's Related search can look
  for the show rather than for that one season. Existing installs backfill on
  their next ordinary import — no forced re-import, and no re-downloaded posters.

Deliberately unchanged: the action stack stays at seven controls for a poster
linked to Plex and five otherwise, so the grid's minimum column width and the
number of posters per row do not move. Sort order is untouched — searching still
filters without reordering.

## Capabilities

### New Capabilities

None. Every part of this lands on an existing capability.

### Modified Capabilities

- `search`: what a query is matched against — the recorded Plex title where there
  is one, rather than the filename-derived title in every case.
- `poster-library`: a new action in the poster action stack, present on pointer
  and touch surfaces alike; Copy URL leaves that stack.
- `poster-editing`: "Download and copy a poster" loses its copy half and becomes
  a requirement about downloading.
- `plex-import`: the item mapping additionally records a season's show title, and
  reconciles it on re-import like every other recorded fact.

## Impact

**Code**

- `src/Poster/Search/PosterSearch.php` — match against a supplied title where one
  exists.
- `src/Poster/PosterLibrary.php` — build the titles map and pass it to the filter.
  It already holds `PlexItemRepository`, and `paginate()` is the single call site,
  so no controller signature changes.
- `src/Database/Database.php`, `src/Database/PlexItemRecord.php`,
  `src/Database/PlexItemRepository.php` — the new `parent_title` column.
- `src/Plex/Import/ImportService.php` — record and reconcile it.
- `src/Controller/GalleryController.php` — pass the per-poster related query to
  the template, alongside the title and year maps it already passes.
- `templates/partials/gallery_results.html.twig` — the new action replaces Copy
  URL, rendered through the existing `action_body()` macro so the phone sheet,
  which clones the same markup, needs no second edit.
- `templates/partials/_icons.html.twig` — one new glyph.
- `public/assets/gallery.js` — intercept the action, and drop the now-unused
  `copy` branch and its clipboard handling.

**Data**

One additive, nullable-by-default column on `plex_items`, applied by the existing
idempotent `ensureColumn` migration. No data is rewritten and no poster is
re-downloaded. Nothing needs to be done by hand on upgrade.

**Docs**

`README.md` names the poster actions and will go stale on the Copy URL removal;
it is fixed in the same commit. `docs/configuration.md` and
`docs/development-workflow.md` are unaffected — no setting, no environment
variable, and no part of the toolchain changes.

**Not affected**

Poster Wall, orphan detection, export to Plex, and the Find Posters / Plex
Posters sources are all untouched.
