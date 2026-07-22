## Why

Part of Marquee's appeal is showing off a curated poster collection. The Poster
Wall is a full-screen, ambient display that cycles through the library's posters
at random — ideal for a spare screen, a media room, or a kiosk.

## What Changes

- Add a full-screen `/wall` page that continuously cross-fades between random
  posters drawn from the whole library.
- Add a JSON endpoint that returns a fresh random batch of poster URLs, so the
  wall keeps pulling new posters without reloading and never repeats predictably.
- Show a gentle message when the library has no posters yet.
- Link to the wall from the gallery.

## Capabilities

### New Capabilities
- `poster-wall`: a full-screen, auto-rotating display of random library posters.

### Modified Capabilities
<!-- None. -->

## Impact

- New: `src/Poster/Wall/PosterWallService.php`,
  `src/Controller/PosterWallController.php`, `templates/wall.html.twig`,
  `public/assets/wall.css`, `public/assets/wall.js`.
- Modified: gallery toolbar (link to the wall).
- Routes: `/wall`, `/wall/posters`.

## Out of scope (follow-up)

- Now-playing / active-stream awareness and the Plex image proxy — a separate
  change, since it depends on live Plex sessions to validate.
