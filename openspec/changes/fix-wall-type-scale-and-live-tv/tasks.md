## 1. Live TV classification

- [x] 1.1 In `HttpPlexClient::session()`, promote a session to `PlexSessionType::LiveTv` when `live="1"`, before the `rawType` match, excluding `track` so live music stays music
- [x] 1.2 Fold the existing `clip` + live branch into the new path so Live TV is classified in one place
- [x] 1.3 Add `HttpPlexClientTest` cases: `episode` + `live="1"` → `LiveTv`; `movie` + `live="1"` → `LiveTv`; `track` + `live="1"` → `Music`; `clip` + `live="1"` → `LiveTv` still; `episode` + `live="0"` → `Episode` unchanged

## 2. Never mint an unredeemable token

- [x] 2.1 In `NowPlayingTile::tokenFor()`, return the `StreamToken::LIVE` sentinel when the thumb does not begin with `/`, alongside the existing null/empty check
- [x] 2.2 Add a `NowPlayingServiceTest` case: a non-live session whose thumb is an absolute URL yields a tile pointing at the placeholder rather than a signed token
- [x] 2.3 Confirm `StreamTokenTest` still passes unchanged — `thumbFor()`'s guard is deliberately left as is

## 3. Placeholder fallbacks

- [x] 3.1 In `PosterWallController::streamPoster()`, serve the placeholder when `thumbFor()` returns null instead of throwing `HttpNotFoundException`
- [x] 3.2 Add a controller test: a correctly signed token carrying a non-Plex path returns the placeholder with a 200, not a 404
- [x] 3.3 In `wall.js`, honour `preload()`'s resolved boolean — on false, `rotateStreams` substitutes `/wall/stream-poster/live` and `rotateRandom` skips to the next poster
- [x] 3.4 Verify the duplicate image request per rotation tick is gone (preload followed by `reveal()` setting `.src`)

## 4. Placeholder caching

- [x] 4.1 Give `PosterWallController::placeholder()` a cache-policy parameter instead of hardcoding `private, max-age=3600`
- [x] 4.2 Pass the long policy for the Live TV sentinel and a short `private, max-age=10` for both failure paths (unresolvable token, `PlexException`)
- [x] 4.3 Add tests asserting the Live TV placeholder is cached long, and that both failure placeholders are cached briefly so a recovered poster returns

## 5. Banner type scale

- [x] 5.1 Add `container-type: inline-size` to `.wall__frame` in `wall.css`
- [x] 5.2 Convert font sizes to `cqw`: `.wall__title` → `3.2cqw`, `.wall__meta` → `1.6cqw`, `.wall__banner--top` → `1.65cqw`, removing the `clamp()`/`vw` values
- [x] 5.3 Convert the surrounding spacing: `--top` padding → `1.2cqw 3.5cqw`, `--bottom` padding → `9cqw 4cqw 2.6cqw`, `::after` rule → `4.5cqw × 0.45cqw` with `1.5cqw` margin, user dot → `1cqw`
- [x] 5.4 Change `--top` `letter-spacing` from `5px` to `0.23em` so it tracks its own font size

## 6. Verification

- [x] 6.1 Run `composer test` and `composer stan` (PHPStan max, PHPUnit) — both must pass
- [x] 6.2 Run PHP-CS-Fixer over the touched PHP files
- [ ] 6.3 Load the wall with a live DVR session playing: confirm the placeholder renders with the program title and Live TV details, and no 404s appear in the console
- [ ] 6.4 Load the wall on the target TV browser and confirm container queries are honoured; if not, fall back to the `em`-on-frame approach in design.md
- [ ] 6.5 Eyeball the banner scale on the target display and decide whether `.wall__meta` needs the documented bump from `1.6cqw` to ~`1.9cqw`
