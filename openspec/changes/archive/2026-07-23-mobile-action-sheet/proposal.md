## Why

The previous mobile fix revealed the per-poster action overlay on tap, but that
overlay never had room for all seven actions on a phone-sized poster, and tapping
the poster to open it could land on a button underneath and fire that action.
Category tabs also still overflowed. Overlaying actions on a small poster is the
wrong pattern for touch.

## What Changes

- **Mobile action sheet.** On touch devices, tapping a poster opens a bottom
  sheet listing that poster's actions (Change poster, Send/Fetch to Plex,
  Download, Copy URL, Full screen, Delete) at full size, with the title as a
  header. There is no overlay on the poster, so nothing can be tapped through.
  The sheet closes on backdrop tap, Escape, or after an action runs.
- **Desktop unchanged in spirit.** Pointer devices keep the hover overlay;
  clicking a poster opens it full screen.
- **Tabs can't overflow.** Category tabs render as a two-column grid on small
  screens instead of a single overflowing row.

## Capabilities

### New Capabilities
- `mobile-action-sheet`: a touch-first poster action sheet and non-overflowing
  category tabs.

## Impact

- Modified: `templates/gallery.html.twig` (sheet markup + wiring),
  `templates/partials/gallery_results.html.twig` (already actionless image),
  `public/assets/app.css` (sheet, hover-only overlay, tab grid),
  `public/assets/gallery.js` (open sheet on touch / full screen on desktop;
  reuse each card's actions in the sheet via delegation).
- Supersedes the touch tap-to-reveal overlay from `mobile-and-ui-polish`.
- No backend changes, no new environment variables.
