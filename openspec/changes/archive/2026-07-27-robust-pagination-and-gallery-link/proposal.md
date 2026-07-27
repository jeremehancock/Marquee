## Why

The gallery pagination only offers Previous / Next plus a "Page X of Y"
label, which forces users with large libraries to click through one page at a
time to reach a distant page. The maintainer wants the richer, jump-anywhere
pagination the original app had. Separately, the navigation link back to the
gallery reads "Back to library", but the section it returns to is called the
gallery everywhere else in the UI — the wording is inconsistent.

## What Changes

- Rework gallery pagination into a numbered control: first ("go to
  beginning") and last ("go to end") controls, previous/next steppers, and a
  windowed run of page numbers with an ellipsis so only a bounded set of
  numbers renders regardless of total pages (e.g. `< 1 2 3 … 82 >`).
- The current page is marked as the active, non-navigable number; every other
  number is a link that preserves the active search query and sort order, just
  as Previous/Next already do.
- Rename the "Back to library" link on the Import and Orphans pages to "Back
  to gallery".

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-library`: The "Gallery listing with pagination" requirement gains
  behavior for first/last controls and a windowed, ellipsized page-number run.
  The "Remembered library section" requirement's back link is renamed to "Back
  to gallery".

## Impact

- `templates/partials/gallery_results.html.twig` — pagination markup.
- `src/Poster/Page.php` — a method that computes the windowed page-number
  sequence (numbers plus ellipsis gaps) for the template to render.
- `templates/plex.html.twig`, `templates/orphans.html.twig` — back-link label.
- `public/assets/gallery.js` — no change expected; delegated `.pagination a`
  click handling already drives the no-reload navigation for any page link.
- CSS (`public/assets/*`) — styling for the number list / active state.
- Tests: `tests/Unit/Poster/PageTest.php` (new window logic) and any template
  or controller tests asserting the old link text.
