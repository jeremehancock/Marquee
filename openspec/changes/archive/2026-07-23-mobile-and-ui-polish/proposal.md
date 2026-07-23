## Why

Mobile testing surfaced layout problems — tabs and toolbar buttons overflow, the
poster action overlay is always visible (hiding the poster), and posters are too
large on a phone. Two smaller UI fixes came with it: the logout link shows even
when authentication is bypassed, and the found-poster "Use this" label reads
awkwardly.

## What Changes

- **Hide logout when auth is bypassed.** When `AUTH_BYPASS` is on there is no
  session to end, so the logout link is not shown.
- **Rename "Use this" to "Select"** in the found-poster results.
- **Mobile layout.** Category tabs scroll horizontally instead of wrapping; the
  search box and toolbar buttons each take a full row; posters use smaller
  columns so at least two fit per row on a phone.
- **Tap-to-reveal overlay.** The per-poster action overlay is hidden by default
  and revealed on hover (desktop) or by tapping the poster (touch), instead of
  being permanently visible on touch devices. Hidden overlays ignore pointer
  events so their buttons can't be tapped by accident.

## Capabilities

### New Capabilities
- `mobile-and-ui-polish`: responsive gallery layout, tap-to-reveal poster
  actions, auth-aware logout, and clearer found-poster labelling.

## Impact

- Modified: `templates/layout.html.twig`, `templates/gallery.html.twig`,
  `templates/partials/gallery_results.html.twig`, `public/assets/app.css`,
  `public/assets/gallery.js`, `src/bootstrap.php` (Twig `auth_bypass` global).
- No new environment variables; no backend behaviour changes.
