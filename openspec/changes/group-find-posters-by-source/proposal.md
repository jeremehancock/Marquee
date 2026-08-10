## Why

Find Posters returns a single undifferentiated grid of up to two hundred
candidates, and nothing on screen says which service supplied any of them. That
matters because the services are not interchangeable to the people using them:
fanart.tv is where textless artwork lives, TMDB is where language variants live,
TVDB is where a show's own artwork lives. A user who knows what they want
currently has to scrub the whole grid hunting for it, with no way to tell when
they have seen everything one service has to offer.

The Plex Posters tab already solved this for its own candidates — labelled
sections with a count in each heading — and users who have learned that shape
find it missing on the tab beside it.

## What Changes

- Find Posters results are split into labelled sections, one per service that
  supplied a candidate: TMDB, TVDB, and fanart.tv.
- Sections appear in a fixed order — **TMDB, TVDB, fanart.tv** — that is the
  same for every item, so the tab's shape does not shift from poster to poster.
  This is the order the provider attribution in the footer already presents.
- Each section heading carries a count of its own candidates, presented exactly
  as the Plex Posters tab presents its group counts. There is no grand total.
- A section with no candidates is omitted rather than shown empty, matching the
  Plex Posters tab.
- Candidates whose service is not one of the three known ones are still shown,
  in a trailing section, so an addition on the service side can never silently
  discard posters.
- **BREAKING (spec-level, not user data):** the existing prohibition on
  re-ordering candidates is narrowed. The service's best-first ranking is
  preserved *within* each section, but no longer across the whole result, since
  sections are ordered by service rather than by rank.

## Capabilities

### New Capabilities

None. This changes how an existing source presents its results.

### Modified Capabilities

- `poster-sources`: the requirement that candidates are presented in the order
  the source returns them, with no re-ordering, is narrowed to apply within a
  section. A new requirement adds the sectioning itself — how sections are
  derived, ordered, labelled, counted, and omitted when empty.

## Impact

- **No API change.** The posteria.app response already carries a `source` field
  on every candidate, and Marquee already forwards it to the browser, where it
  is currently unused. Nothing new is requested from the service.
- `templates/gallery.html.twig` — the Find Posters panel's flat `x-for` becomes a
  sectioned render; the existing Plex group markup is the model for it.
- `public/assets/gallery.js` — `finder` gains the grouped view of its results.
- `public/assets/app.css` — the scroll container moves from the Find Posters grid
  to a sections wrapper, so section headings can stick the way Plex group
  headings do.
- `src/Controller/ChangePosterController.php` — unchanged in shape; only relevant
  if grouping is placed server-side.
- Tests: a functional test over the Find Posters payload, and an asset-shape
  tripwire in the vein of `PlexPosterGroupsTest`.
- Docs: the README's description of Find Posters, if it describes the results as
  a single list.
