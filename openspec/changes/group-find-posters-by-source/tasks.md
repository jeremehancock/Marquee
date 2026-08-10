## 1. The provider enum

- [x] 1.1 Add `App\Poster\Source\PosterProvider`, a string-backed enum whose case
      values are the exact wire slugs the poster source emits: `tmdb`,
      `thetvdb`, `fanart.tv`.
- [x] 1.2 Give it a `label(): string` returning the user-facing name — `TMDB`,
      `TheTVDB`, `fanart.tv`.
- [x] 1.3 Give it an ordered static accessor returning the cases in section
      order: TMDB, TheTVDB, fanart.tv. Document in the docblock that this order
      deliberately follows the provider attribution footer, and that the two must
      not be allowed to disagree.
- [x] 1.4 Confirm `tryFrom()` is the only parse path, so an unrecognised slug
      yields `null` rather than an error.

## 2. Sectioning the result

- [x] 2.1 Add a method to `PosterSearchResult` that partitions its candidates
      into sections — iterating once in arrival order and appending to buckets,
      never sorting — so within-section order is preserved by construction.
- [x] 2.2 Place candidates whose `source` is `null` or unrecognised into a
      trailing section labelled `Other`, after every named provider.
- [x] 2.3 Omit any section that ends up with no candidates.

## 3. Controller payload

- [x] 3.1 Change the Find Posters JSON to emit an ordered `sections` array of
      `{label, posters}` in place of the flat `posters` array, keeping each
      poster's existing `url` / `thumb` fields unchanged.
- [x] 3.2 Drop `source` from the per-poster objects — it is now carried by the
      section that holds them.
- [x] 3.3 Leave the `partial` flag and every outcome message exactly as they are;
      grouping changes nothing about how failures are reported.

## 4. Shared group styling

- [x] 4.1 Rename `.plex-groups`, `.plex-group`, `.plex-group__heading`, and
      `.plex-group__count` to `.poster-group*` in `app.css`, and update the Plex
      Posters markup in `gallery.html.twig` to match.
- [x] 4.2 Move the scroll container for Find Posters: take `max-height: 62vh`,
      `overflow-y: auto` and `overscroll-behavior: contain` off `.find-grid` and
      onto the sections wrapper, with the inner grids reset to
      `max-height: none; overflow: visible`, mirroring `.plex-groups .find-grid`.
- [x] 4.3 Verify the Plex Posters tab is visually unchanged after the rename —
      sticky headings, group spacing, and counts all still behave.

## 5. Find Posters rendering

- [x] 5.1 Replace the flat `x-for` over `finder.results` with a wrapper and an
      `x-for` over `finder.sections`, rendering `section.label`, a count from
      `section.posters.length`, and the existing `.find-item` cell unchanged.
- [x] 5.2 Keep the client free of any provider name — labels and order come from
      the payload only.
- [x] 5.3 Space sections with a flex `gap`, never `.poster-group + .poster-group`;
      Alpine leaves a `<template>` between every pair and the adjacent-sibling
      selector silently matches nothing.
- [x] 5.4 Update `findPosters()` and every reset of `finder` state to carry
      `sections` instead of `results`, including the empty/error resets.
- [x] 5.5 Keep the "Tap a poster to preview it." line and the loading, error and
      notice lines behaving as they do now, driven by whether any section has
      candidates.
- [x] 5.6 Confirm preview and apply are untouched — a candidate opens full screen
      and applies through the same confirmation, from any section.

## 6. Tests

- [x] 6.1 Unit-test `PosterProvider`: slug round-trip, labels, section order, and
      `tryFrom()` returning `null` for an unknown slug.
- [x] 6.2 Unit-test the partition: candidates land in the right sections, order
      within a section matches arrival order, empty sections are omitted, and the
      count of posters across all sections equals the count in.
- [x] 6.3 Unit-test that a `null` source and an unrecognised slug both land in the
      trailing `Other` section rather than being dropped.
- [x] 6.4 Extend the functional Find Posters test to assert the payload's section
      order is TMDB, TheTVDB, fanart.tv for a fixture carrying all three.
- [x] 6.5 Move `PlexPosterGroupsTest` onto the `.poster-group*` selectors so the
      adjacent-sibling tripwire keeps guarding both tabs, and rename it to reflect
      that it now covers more than the Plex tab.
- [x] 6.6 Add an asset-shape assertion that the Find Posters sections wrapper owns
      the scroll and the inner grid does not.

## 7. Docs and gates

- [x] 7.1 Update the README's Find Posters answers so the description matches
      sectioned results — the "What's the difference between Plex Posters and
      Find Posters?" answer in particular.
- [x] 7.2 Add a Find Posters row to the live round-trip checklist in
      `docs/testing.md` covering section order and per-section counts.
- [x] 7.3 Run `composer test`, `composer stan`, and `composer cs` and fix
      anything they report.
- [ ] 7.4 Confirm on a phone-width viewport that section headings stick correctly
      inside the change dialog and the panel still scrolls as one.
