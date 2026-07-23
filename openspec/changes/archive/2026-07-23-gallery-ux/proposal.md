## Why

Local testing surfaced a batch of gallery usability gaps: page reloads on every
poster action, enter-to-search, native `confirm()` dialogs, cramped cards whose
overlay buttons don't fit, a title buried in the overlay, and no way to preview a
found poster before applying it. These are polish items that together make the
edit-in-place model feel smooth.

## What Changes

- **Live search** — the gallery filters as you type (debounced) and restores the
  full list when the box is emptied; no enter, no full reload.
- **No-reload updates** — changing, sending/fetching to Plex, and deleting a
  poster update only the grid via a background request, with a toast for the
  result. Pagination is background-loaded too.
- **Dedicated title** — the media title moves to a caption below each poster
  instead of living inside the hover overlay, freeing the overlay for actions.
- **Larger posters** — wider grid columns so the overlay action stack fits
  comfortably.
- **Lazy-load animation** — each poster lazy-loads over a subtle shimmer and
  fades in.
- **Always-available Plex actions** — Send to Plex and Fetch from Plex show for
  every Plex-linked poster, not conditionally.
- **Confirm modals** — styled confirmation modals replace native `confirm()` for
  destructive actions (delete a poster, delete all orphans).
- **Preview found posters** — each posteria.app result can be opened full screen
  before it is applied.
- **Remembered section** — leaving for Orphans or Import and coming back returns
  to the library section you were viewing.
- **Poster Wall in a new tab** — the wall opens in its own tab.

## Capabilities

### New Capabilities
- `gallery-ux`: live search, background grid refresh, remembered section,
  lazy-load presentation, modal confirmations, previewable found posters,
  always-available Plex actions for linked posters, and Poster Wall in a new tab.

## Impact

- New: `public/assets/gallery.js` (progressive-enhancement layer).
- Modified: `gallery.html.twig`, `orphans.html.twig`, `plex.html.twig`,
  `public/assets/app.css`; `GalleryController`, `OrphanController`,
  `PlexImportController` (session-remembered section).
- No new environment variables. Behaviour degrades gracefully without
  JavaScript: forms still submit and search still works on enter.
