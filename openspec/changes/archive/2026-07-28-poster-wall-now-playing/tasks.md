## 1. Plex session reading

- [x] 1.1 Add a `PlexSession` value object (`src/Plex/PlexSession.php`): media
  type, display title, show/episode fields, year, `live` flag, Plex username,
  and poster thumb path — with a helper to render the bottom-banner detail line.
- [x] 1.2 Add a session media-type enum (movie / episode / live-tv / music /
  other) or reuse/extend `PlexMediaType` for classification, with an
  `isVideo()`-style helper the service uses to drop music and unknown types.
- [x] 1.3 Add `sessions(): list<PlexSession>` to the `PlexClient` interface.
- [x] 1.4 Implement `sessions()` in `HttpPlexClient` against `GET /status/sessions`:
  parse each child, map `type` (movie→thumb, episode→grandparentThumb + SxxEyy,
  clip+live→live-tv, track→music, else other), read `<User title>`, `live`.
- [x] 1.5 Update any `PlexClient` test doubles / fakes to implement `sessions()`.
- [x] 1.6 Unit-test session parsing: movie, episode, Live TV, music, unknown
  type, missing user, missing live-tv program/channel.

## 2. Now-playing service

- [x] 2.1 Add `NowPlayingService` (`src/Poster/Wall/`) that calls `sessions()`,
  drops music/other, and returns ordered now-playing tiles (id, poster URL,
  title, detail, user, live) with no deduplication.
- [x] 2.2 Return an empty list when Plex is not configured or the client throws,
  so the wall silently stays on random art.
- [x] 2.3 Mint an opaque, self-contained tile `id` that encodes the thumb path
  (or a live-tv marker) so `/wall/stream-poster/{id}` can resolve it without a
  server-side session store; reject anything that does not decode to a Plex
  thumb-shaped path or the placeholder.
- [x] 2.4 Unit-test the service: music dropped, same-title sessions kept as two
  tiles, live-tv tile flagged, empty on unconfigured/error.

## 3. Endpoints and routing

- [x] 3.1 Add `streams()` to `PosterWallController`: return
  `{ "streams": [...] }` JSON from `NowPlayingService`.
- [x] 3.2 Add `streamPoster()` to `PosterWallController`: resolve the tile id and
  stream Plex poster bytes for real items, or serve the bundled placeholder for
  live-tv tiles; set an appropriate Content-Type and a short cache header.
- [x] 3.3 Register `GET /wall/streams` and `GET /wall/stream-poster/{id}` in
  `src/Routes.php`.

## 4. Live TV placeholder asset

- [x] 4.1 Add a good-looking Live TV placeholder poster under `public/assets/`
  (portrait poster aspect, styled to sit under the overlays).
- [x] 4.2 Have `streamPoster()` serve this asset for live-tv tiles.

## 5. Front-end now-playing mode

- [x] 5.1 Add overlay markup to `templates/wall.html.twig` (top "Currently
  Streaming" banner, bottom details + user banner), hidden by default.
- [x] 5.2 Style the overlays in `public/assets/wall.css` for both modes.
- [x] 5.3 In `public/assets/wall.js`, poll `GET /wall/streams` (~10s) and switch
  between random and now-playing modes on empty/non-empty results.
- [x] 5.4 Render one tile static; cycle multiple tiles (~8s) reusing the existing
  cross-fade, updating both overlay banners per tile.
- [x] 5.5 On empty streams, tear down overlays and resume random rotation
  exactly as before.

## 7. Public wall, favicon, and polish

- [x] 7.1 Make `/wall` and its endpoints public in `AuthMiddleware` so an
  unattended display needs no sign-in; update the delta spec accordingly.
- [x] 7.2 Add the app favicon (and apple-touch-icon) to `templates/wall.html.twig`.
- [x] 7.3 Restyle the now-playing overlays: solid gold "Currently Streaming"
  bar and a left-aligned, horizontal bottom banner.
- [x] 7.4 Give the Live TV placeholder a Plex-ish charcoal-and-gold scheme.

## 6. Verification

- [x] 6.1 Run PHP-CS-Fixer, PHPStan (max), and PHPUnit — all green.
- [x] 6.2 Manually verify (or via a stubbed sessions response): no stream →
  random wall; one movie → static poster + overlays; two streams of the same
  title → two tiles cycling with distinct users; Live TV → placeholder +
  overlays; music-only → stays random.
- [x] 6.3 Validate the change: `openspec validate poster-wall-now-playing`.
