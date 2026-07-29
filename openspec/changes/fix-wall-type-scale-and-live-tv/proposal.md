## Why

Two defects make the Poster Wall unpleasant to leave running on a display. The
overlay banners render their text far too large relative to the poster they sit
on, and a now-playing tile can render with no poster at all — a black rectangle
with banners floating on it — whenever Plex reports a session the wall cannot
resolve a poster for. Both are visible on the wall's primary use case: an
unattended TV.

## What Changes

- **Banner type is sized against the poster instead of the viewport.** The
  overlay text currently scales with viewport width while the poster frame is
  sized from viewport height, so the two diverge on every landscape display. The
  banners become container-relative to the poster frame and the type scale is
  halved to roughly half its current rendered size.

- **DVR-backed sessions are recognised as Live TV.** Plex reports Live TV from a
  DVR tuner (for example a Tunarr channel) as a `movie` or `episode` session
  carrying `live="1"`, not as the `clip` the wall currently expects. These
  sessions now get the Live TV placeholder and Live TV details rather than being
  treated as library items.

- **A now-playing tile can never render without a poster.** Three independent
  fallbacks close the gap: the wall stops minting poster tokens it will refuse to
  redeem, the poster endpoint serves the placeholder instead of a 404 when a
  token does not resolve, and the wall page falls back to the placeholder when a
  poster image fails to load.

- **A placeholder shown in place of a failed poster no longer sticks.** The
  placeholder is currently served with an hour-long cache whether it stands for
  Live TV or for a poster Plex could not return. A single transient Plex failure
  therefore pins that item to the placeholder for an hour, long after Plex has
  recovered. Only the Live TV placeholder keeps the long cache; a placeholder
  standing in for a failure becomes short-lived so the real poster returns.

- Music sessions remain excluded from the wall even when Plex marks them live, so
  a live radio stream does not take over the display.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-wall`: the Live TV placeholder requirement is broadened to cover
  sessions Plex types as movie or episode but marks as live; a new requirement
  states that a now-playing tile always renders a poster; and the streaming
  overlay requirement gains a legibility constraint tying banner text to the
  poster's size rather than the viewport's.

## Impact

- `src/Plex/HttpPlexClient.php` — session classification keys off the `live`
  attribute for video sessions.
- `src/Poster/Wall/NowPlayingTile.php` — refuses to sign a thumb that is not a
  Plex-relative path, falling back to the Live TV sentinel.
- `src/Controller/PosterWallController.php` — unresolvable token serves the
  placeholder rather than raising a 404.
- `public/assets/wall.css` — banner type and spacing become container-relative.
- `public/assets/wall.js` — the poster preload result is honoured instead of
  discarded.
- Tests: `tests/Unit/Plex/HttpPlexClientTest.php`,
  `tests/Unit/Poster/NowPlayingServiceTest.php`, and the wall controller's
  feature tests.
- No configuration, database, or API surface changes. No breaking changes.
