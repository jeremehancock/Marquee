## Context

The Poster Wall today (`templates/wall.html.twig` + `public/assets/wall.js` +
`PosterWallController` + `PosterWallService`) is a random-art screensaver: the
client polls `GET /wall/posters` for a batch of local poster URLs and cross-fades
between two layers on a fixed interval. It has no awareness of Plex playback.

The `PlexClient` interface (`src/Plex/`) reads libraries, items, seasons,
collections, and posters, but has no session/now-playing capability. All poster
art the wall shows today comes from local files served by `PosterImageController`.

This change adds a "Now Playing" mode driven by Plex's `GET /status/sessions`
endpoint. It is cross-cutting (Plex client + new value object + wall service +
two new endpoints + front-end mode switch + a bundled asset), which is why it
warrants a design doc. Requirements live in the `poster-wall` spec delta;
motivation in the proposal.

## Goals / Non-Goals

**Goals:**
- Detect active Plex playback and take over the wall while any qualifying stream
  is active, reverting to random art when they stop.
- Show each stream's poster with a top "Currently Streaming" banner and a bottom
  banner of media details + Plex user; one stream holds static, many cycle.
- Never deduplicate: one tile per session, even for identical titles.
- Handle Live TV with a bundled placeholder; ignore music.
- Work for items never imported into Marquee by proxying art live from Plex.
- Degrade silently when Plex is unconfigured/unreachable.

**Non-Goals:**
- Playback controls, progress bars, transcode/bandwidth details, or "who paused."
- A configurable poll cadence or per-user filtering (env-free; fixed cadences).
- Persisting session history or any DB writes.
- Changing the random-wall behavior itself (it stays exactly as-is when idle).
- New environment variables — reuse the existing Plex connection config.

## Decisions

### D1: Read sessions via a new `sessions()` on `PlexClient`

Add `sessions(): list<PlexSession>` to the `PlexClient` interface, implemented in
`HttpPlexClient` against `GET /status/sessions`. A new immutable `PlexSession`
value object carries what the wall needs: media type (movie / episode / live-tv /
music / other), display title, show + episode info, year, live flag, the Plex
username (`<User title>`), and the poster thumb path to proxy (`thumb` for
movies, `grandparentThumb` for episodes).

Parsing rules from the `<MediaContainer>` children:
- `type="movie"` → movie; poster = `thumb`.
- `type="episode"` → episode; poster = `grandparentThumb`; details use
  `grandparentTitle` (show) + `parentIndex`/`index` (SxxEyy) + `title`.
- `type="clip"` with a `live="1"` marker → live-tv; placeholder poster; details
  use `grandparentTitle` (channel) + `title` (program) when present.
- `type="track"` → music; produced but marked so the wall drops it. (Filtering in
  the service keeps the client a faithful mapping of the API.)
- Unknown types → `other`, dropped by the service.

*Alternative considered:* reusing `PlexItem`. Rejected — sessions carry user,
live, and episode fields `PlexItem` lacks, and overloading it would muddy the
import path. A dedicated value object keeps both clean.

*Alternative considered:* parsing sessions in the controller. Rejected — the
project convention is thin controllers → services → value objects, and the XML
parsing belongs next to the other Plex XML parsing in `HttpPlexClient`.

### D2: `NowPlayingService` maps sessions → tiles, dropping non-video

A new service (alongside `PosterWallService`) calls `sessions()`, filters out
music and unknown types, and returns an ordered list of now-playing tiles. Each
tile has: an opaque `id`, a poster URL (`/wall/stream-poster/{id}`), a title, a
detail line, a user, and a `live` flag. No deduplication — the list mirrors the
sessions one-to-one. If `isConfigured()` is false or the client throws, it
returns an empty list (→ random wall).

### D3: Two new authenticated endpoints on `PosterWallController`

- `GET /wall/streams` → JSON `{ "streams": [ {id, poster, title, detail, user,
  live}, ... ] }`. Empty array means "no takeover."
- `GET /wall/stream-poster/{id}` → proxies the poster bytes. For a real item it
  streams Plex art (reusing the `thumb`-fetch path already in `HttpPlexClient`);
  for a live-tv tile it serves the bundled placeholder asset.

The tile `id` is an **opaque token minted by `/wall/streams`**, not a raw Plex
thumb URL. The proxy resolves the token back to the thumb path (or placeholder)
server-side, so the client never sees Plex internals and cannot be used to fetch
arbitrary Plex URLs. Because sessions are transient, the token encodes the thumb
path for the current snapshot (e.g. a signed/opaque encoding of the thumb),
resolved on demand — no server-side session store needed. Both routes sit behind
the existing auth middleware like `/wall` and `/wall/posters`.

*Alternative considered:* returning Plex thumb URLs directly to the client.
Rejected — leaks the Plex token/host and turns the endpoint into an open proxy.

### D4: Front-end mode switch in `wall.js`

Add a stream poll (~10s) alongside the existing rotation. On each poll:
- Non-empty streams → enter/stay in now-playing mode: render the tile's poster
  into a layer plus the two overlay banners. One tile → no cycling; many tiles →
  advance one per rotation tick (~8s, reusing the existing `ROTATE_MS`).
- Empty streams → leave now-playing mode and resume the random rotation exactly
  as today.

Overlays are two absolutely-positioned banners in `wall.css`, hidden in random
mode. The existing two-layer cross-fade is reused for both modes.

*Alternative considered:* server-rendered overlays. Rejected — the wall is a
long-lived single page that already updates itself over fetch; keeping render on
the client avoids a full reload and matches the current architecture.

### D5: Cadence and paused handling

Poll `/wall/streams` every ~10s; cycle multi-stream posters every ~8s. Paused
sessions are returned by Plex in `/status/sessions` and are treated as active, so
no special handling is needed — the poster simply stays up.

## Risks / Trade-offs

- **Plex Live TV markup varies by version/DVR** → key detection off `type="clip"`
  plus a `live` marker and fall back to the placeholder + generic "Live TV" text
  when program/channel attributes are absent, rather than mis-detecting.
- **Opaque token must not become an open proxy** → the token resolves only to a
  Plex *thumb*-shaped path (or the placeholder); the proxy refuses anything else
  and always attaches the server's own Plex token, never a client-supplied one.
- **Poll load on Plex** → a single lightweight `/status/sessions` call every ~10s
  per open wall; acceptable for a household display. Poster proxy responses can
  carry a short cache header to avoid re-fetching within a hold.
- **Session `type` coverage** → anything not movie/episode/live-tv/music maps to
  `other` and is dropped, so an unexpected type degrades to "no takeover" rather
  than a broken tile.
- **No imported art needed** → proxying live means art always reflects Plex's
  current poster, but adds one proxied request per visible tile; mitigated by the
  short hold and cache header.

## Migration Plan

Purely additive. New routes, one new asset, and new methods on the Plex client;
no schema changes, no env changes, no change to random-wall behavior when idle.
Rollback is removing the two routes and the front-end poll — the wall falls back
to today's random-only behavior with no residual state.

## Open Questions

- Exact placeholder artwork for Live TV (a designed asset vs. a simple styled
  card) — resolved at implementation; the spec only requires it "look good" and
  carry the same overlays.
- Whether to show a small user avatar/initial vs. plain username in the bottom
  banner — plain username satisfies the spec; avatar is a possible later polish.
