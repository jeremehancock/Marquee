## Why

Changing a poster throws the user's place in the gallery away. The grid is
rebuilt from the first page, so on a phone — where the grid grows by infinite
scroll rather than pagination — everything the user scrolled through is dropped
and re-loaded batch by batch behind the loading dim. They watch the grid churn
and then land somewhere near the top, far from the poster they just changed.
Changing one image should leave the view exactly where it was.

## What Changes

- After an operation that replaces a single poster's image (upload, from URL,
  Find Posters, Fetch from Plex), the gallery updates **that poster's card in
  place** instead of re-fetching and re-rendering the whole grid. Scroll
  position, the pages already appended by infinite scroll, and the rest of the
  grid are untouched.
- "Send to Plex" changes nothing locally, so it stops refreshing the grid at
  all — it reports its result and leaves the view alone.
- If the changed poster's card is not present in the current DOM, the existing
  full-grid refresh still runs, so nothing can be left stale.
- Delete and Import keep the full-grid refresh: both change which posters exist,
  which invalidates the counts and pagination the grid renders. The same
  loses-your-place behaviour therefore still applies to those two; fixing it
  there needs a different mechanism and is out of scope here.
- Docs: drop the personal-name attribution from the README's acknowledgement of
  Posteria. Marquee and Posteria have the same author, so naming him as the
  author of the other project reads as crediting a third party. No spec impact —
  bundled here to avoid a release cycle for two lines of prose.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-editing`: the requirement that a changed poster is visible immediately
  gains the other half of the promise — the change SHALL be presented without
  disturbing the user's position in the gallery, and an operation that does not
  alter the stored image SHALL not re-render the grid at all.

## Impact

- `public/assets/gallery.js` — the mutation-submit path (`submitForm`), the
  `gallery:refresh` listener, and `applyFinderSelection` in the change tray.
- `templates/gallery.html.twig`, `templates/partials/gallery_results.html.twig` —
  the poster-mutating forms need to declare which poster they act on and what
  kind of refresh they need.
- `README.md` — acknowledgements wording.
- No PHP, route, database, or Plex-facing changes. The endpoints, their
  responses, and the cache-busting poster URL are all unchanged.
- Behaviour is browser-side only, so it is verified in the running `:dev` image
  rather than by PHPUnit; the PHP tests cover the markup contract the script
  relies on.
