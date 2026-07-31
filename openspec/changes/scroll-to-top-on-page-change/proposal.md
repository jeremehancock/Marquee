## Why

On a pointer device the gallery swaps in the new page of posters without
reloading, which leaves the viewport wherever it was — usually parked on the
pagination control at the bottom. The user lands mid-grid on the new page and
has to scroll back up to see the posters they just asked for.

## What Changes

- Following a pagination link on the gallery returns the view to the top of the
  grid, so a new page always starts at its first poster.
- The return is animated (smooth scroll) so the page change reads as a
  transition rather than a jump, and it starts as soon as the link is activated
  rather than waiting for the new page to arrive.
- Users who have asked their system for reduced motion get the same result
  without the animation.
- Narrow screens are unaffected: they replace pagination with infinite scroll,
  where moving the viewport would be wrong.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `poster-library`: adds a scroll-position guarantee alongside the existing
  "Gallery listing with pagination" requirement — activating a pagination link
  returns the view to the top of the listing, animated unless reduced motion is
  preferred. The pagination control's own structure and link semantics are
  unchanged.

## Impact

- `public/assets/gallery.js` — the delegated pagination click handler.
- No server-side, template, or CSS change; no impact on the no-JavaScript
  fallback, where a pagination link is a normal navigation and the browser
  already starts the destination page at the top.
- `openspec/specs/poster-library/spec.md` on archive.
