## Why

On the orphans page a poster never becomes visible: the card sits under an
endlessly shimmering placeholder, so the user cannot see which poster they are
about to delete. Deleting posters sight-unseen is exactly the situation the
orphans page exists to avoid.

## What Changes

- Poster images render and fade in on every page that shows poster cards, not
  only the main gallery. Today the fade-in is wired up only inside the gallery's
  interactive root, so pages that reuse the card markup (the orphans page) leave
  every image permanently transparent behind its loading placeholder.
- The loading placeholder stops animating once its image has resolved, including
  when the image fails to load, so a broken poster shows as a broken poster
  rather than as "still loading forever".

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the "Poster presentation" requirement's lazy-load animation
  scenario is broadened from "the gallery" to any page that renders poster
  cards, and gains a scenario for the image that never loads.

## Impact

- `public/assets/gallery.js` — the fade-in wiring currently lives behind the
  `[data-gallery]` early return in the DOMContentLoaded handler.
- `public/assets/app.css` — the shimmer placeholder is unconditional on
  `.card__frame`, so it must be suppressed once the image resolves.
- `templates/orphans.html.twig` — the page that surfaces the bug; expected to
  need no change beyond confirming it uses the shared card markup.
- No PHP, routing, or data changes. No user-visible change to the main gallery.
