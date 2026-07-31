## Why

The gallery grid tells you an image is on its way: a shimmer holds the poster's
place and the poster fades in when it arrives. Nowhere else does. Find Posters
opens on a grid of blank cells that snap in one by one, and opening any poster
full screen — a library poster or a Find Posters candidate — gives you a black
screen with nothing in it until the full-resolution image finishes downloading.
On a slow connection that reads as a broken overlay rather than a loading one,
and the Find Posters grid fetches every candidate at once whether or not it is
anywhere near the visible area.

## What Changes

- The Find Posters candidate grid gets the gallery's loading treatment: each
  candidate's cell reserves its space with the same shimmer placeholder, and the
  thumbnail fades in when it resolves — or fails — instead of appearing abruptly
  over an empty cell.
- Candidate thumbnails are deferred until they are at or near the visible part
  of the scrolling results, so opening Find Posters no longer fetches every
  candidate image at once.
- The full-screen poster viewer shows a placeholder while the image loads and
  fades the poster in when it arrives, instead of showing an empty backdrop.
- The Find Posters full-screen preview gets the same treatment, and its action
  bar stays put while the image is loading so the buttons do not jump when the
  poster appears.
- Every one of these placeholders resolves on failure as well as success — a
  poster that cannot be fetched stops the animation rather than shimmering
  forever.

Scope note: "lazy" here means the images, not the results. The Find Posters
search still returns its full candidate list in one request; what changes is
that their images load as they are needed and are visibly accounted for while
they load.

## Capabilities

### New Capabilities

None — this refines presentation in two existing capabilities.

### Modified Capabilities

- `poster-library`: the existing lazy-load placeholder/fade-in requirement
  covers poster cards only; it is extended to the full-screen viewer, whose
  image is re-pointed at a new poster each time it opens rather than being a
  card that loads once.
- `poster-sources`: the candidate grid and the full-screen candidate preview
  gain a stated loading behaviour — deferred thumbnails, placeholder, and
  fade-in on resolve or failure.

## Impact

- `templates/gallery.html.twig` — Find Posters grid cell markup and the
  `viewer--finder` preview.
- `templates/partials/_overlays.html.twig` — the shared full-screen viewer.
- `public/assets/app.css` — a reusable placeholder/fade-in treatment, applied to
  the find grid and both viewers alongside the existing card rules.
- `public/assets/gallery.js` — the overlay component's viewer state, so a
  re-pointed viewer image starts unresolved again on each open.
- `tests/Unit/Asset/` — a shape tripwire in the style of the existing asset
  tests; there is no JS test runner in this repo, so timing and paint behaviour
  is verified by hand against the `:dev` image.
- No PHP services, routes, database, or Plex/posteria.app interaction change.
