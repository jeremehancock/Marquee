## Why

The original Posteria Poster Wall was not just a random-art screensaver: when
someone was actually watching Plex, the wall showed the poster of what was
playing, with a "Currently Streaming" banner and the media details and viewer's
name. That behavior was lost in the port. Restoring it turns a spare display or
TV into a live "who's watching what" board for the household, which is the whole
point of putting the wall on a screen in the first place.

## What Changes

- Add a **Now Playing** mode to the Poster Wall. While one or more Plex playback
  sessions are active, the wall stops showing random art and instead shows the
  poster(s) of what is being watched.
- **Full takeover**: any active stream replaces the random wall; the wall
  reverts to random art when every stream stops.
- **Overlays** on each now-playing poster: a top banner reading "Currently
  Streaming" and a bottom banner with the media details and the Plex username
  streaming it.
- **One vs. many**: a single stream holds static on its poster; multiple streams
  cycle through the now-playing posters. Sessions are **never deduplicated** —
  two people watching the same title produce two separate tiles, each with its
  own user.
- **Live TV** (a `clip`-type session flagged live) shows a bundled placeholder
  poster styled to match, with the program and channel as details when Plex
  provides them, and the same overlays.
- **Music is ignored** — `track`-type sessions never appear on the wall.
- Poster art for a now-playing item is **proxied live from Plex** (a movie's own
  poster; an episode uses its show's poster), so it works even for items never
  imported into Marquee.
- **Graceful degradation**: if Plex is unconfigured or unreachable, the wall
  stays on random art silently — the display never shows an error. Paused
  sessions still count as watching.
- Add a Plex session-reading capability to the Plex client and a new endpoint the
  wall polls for active streams, plus an endpoint that proxies a stream's poster.

## Capabilities

### New Capabilities
<!-- None. This extends the existing poster-wall capability. -->

### Modified Capabilities
- `poster-wall`: Add a now-playing mode that detects active Plex playback
  sessions and, while any are active, takes over the random wall to display the
  streaming posters with "Currently Streaming" / media-detail overlays, cycling
  when there is more than one, with a placeholder for Live TV and music excluded.

## Impact

- **Specs**: `openspec/specs/poster-wall/spec.md` (delta — new requirements).
- **Plex client** (`src/Plex/`): new `sessions()` capability on the `PlexClient`
  interface and `HttpPlexClient` reading `GET /status/sessions`; a new
  `PlexSession` value object (media type, titles, year, episode info, live flag,
  Plex user, poster thumb path). `FakePlexClient`/test doubles updated.
- **Wall** (`src/Poster/Wall/`, `src/Controller/PosterWallController.php`): a
  service that maps sessions to now-playing tiles (dropping music), a
  `/wall/streams` JSON endpoint, and a `/wall/stream-poster/...` proxy endpoint.
- **Front-end** (`public/assets/wall.js`, `wall.css`, `templates/wall.html.twig`):
  poll for streams, switch between random and now-playing modes, render overlays.
- **Assets**: a bundled Live TV placeholder poster under `public/assets/`.
- **Routing** (`src/Routes.php`): register the two new authenticated wall routes.
- No new environment variables — reuses the existing Plex connection config.
