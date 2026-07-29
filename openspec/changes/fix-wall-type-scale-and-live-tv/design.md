## Context

Two independent defects surfaced on a wall left running against a live Plex
server.

**Banner type scale.** The overlay banners live inside `.wall__frame`, whose size
derives from viewport *height* (`height: 92%` with `aspect-ratio: 2 / 3`). The
text inside them is sized from viewport *width* (`clamp(1.4rem, 3.8vw, 2.6rem)`
and friends). The two references diverge on every landscape display. Worse, the
`vw` term only does work between roughly 589px and 1095px of viewport width —
above that every clamp pins to its maximum, so on any ordinary display the type
is effectively a fixed pixel size sitting in a box that resizes independently of
it. At 1920×1080 the frame is 662px wide and the title renders at 41.6px (6.3% of
the frame); at 3840×2160 the frame is 1325px wide and the title is still 41.6px
(3.1%). One stylesheet, opposite complaints.

**Live TV poster.** Live television delivered through a DVR tuner — a Tunarr
channel, in the reported case — arrives in `/status/sessions` as `type="episode"`
(or `movie`) with `live="1"`, and a `thumb`/`grandparentThumb` pointing at an
absolute external URL rather than a Plex-relative path. `HttpPlexClient::session()`
only maps `type="clip"` to `PlexSessionType::LiveTv`, so these sessions are
classified as ordinary episodes. `NowPlayingTile::tokenFor()` then signs the
absolute URL, and `StreamToken::thumbFor()` later refuses it because it does not
start with `/` — the SSRF guard doing exactly its job. The controller turns that
refusal into a 404, and `wall.js` discards the failed preload result and sets the
image source anyway, producing a blank frame that repeats every rotation tick.

The `PlexSession->live` flag is already parsed and carried all the way into the
`/wall/streams` JSON, but nothing reads it. Meanwhile `NowPlayingTile->live` is
derived from `type === LiveTv` — a second flag of the same name with a different
meaning.

## Goals / Non-Goals

**Goals:**

- Banner text sized against the poster frame, at roughly half its current
  rendered size, holding its proportions at any display size.
- DVR-backed live sessions recognised as Live TV and given the placeholder.
- A now-playing tile that can never render without a poster, whatever fails.
- Close the mint/redeem asymmetry: the wall stops issuing poster tokens it is
  guaranteed to reject.

**Non-Goals:**

- Proxying artwork from the DVR/tuner host. Fetching the real Tunarr program art
  would look better, but it would turn a signed same-origin proxy into a fetcher
  for external URLs on unauthenticated endpoints. The placeholder is the chosen
  outcome; see Decisions.
- Any change to the random rotation, the streams poll cadence, or the wall's
  public-access model.
- Restyling the banners beyond size and spacing — colours, layout, and copy stay
  as they are.

## Decisions

### Size banner type with container queries against the poster frame

`.wall__frame` becomes a containment context (`container-type: inline-size`) and
the banners switch from `vw`/`rem` to `cqw`. This makes the type's reference
frame the same box it is pinned to, which is the actual defect — not the specific
numbers.

The sizes are taken from Posteria's Poster Wall, the app this one replaces,
whose overlay is the look being reproduced. Its geometry is near enough to be
directly comparable: its poster is `90vh` tall at 2:3, so 648px wide at 1080p
against Marquee's 662px. Its type is fixed `em` against a 16px root, so the
values convert straight into a share of the frame:

| Selector | Posteria | Marquee before | `cqw` | @1080p | @1440p | @4K |
|---|---|---|---|---|---|---|
| `.wall__title` | `1.5em` = 24px | 41.6px | `3.6cqw` | 23.8px | 31.8px | 47.7px |
| `.wall__meta` | `1em` = 16px | 20.8px | `2.4cqw` | 15.9px | 21.2px | 31.8px |
| `.wall__banner--top` | `1.2em` = 19.2px | 21.6px | `2.9cqw` | 19.2px | 25.6px | 38.4px |

This reframes the original complaint. Only the title was genuinely oversized —
41.6px against Posteria's 24px. The detail line and the badge were close to
right, so a uniform reduction overshoots them; matching Posteria per-element is
what actually lands.

The fixed-pixel spacing around the text scales with it, or the banners become
mostly padding:

| Property | Before | Becomes |
|---|---|---|
| `--top` padding | `16px 32px` | `2.1cqw 4.3cqw` |
| `--top` letter-spacing | `5px` | `0.23em` |
| `--bottom` padding | `96px 40px 34px` | `8.4cqw 3.5cqw 3cqw` |
| `.wall__meta` gap / margin-top | `6px 18px` / `10px` | `0.7cqw 2.2cqw` / `1.1cqw` |
| `.wall__user` padding-left | `20px` | `2.3cqw` |
| user dot | `9px` | `1cqw` |

Letter-spacing becomes `em` rather than `cqw` so it tracks its own font size
directly. Marquee keeps its own banner treatment — the gold bar, the uppercase
label, the tracking — since only the type scale is being matched, not the design.

### Drop the gold accent rule under the details

`.wall__banner--bottom::after` drew a short gold dash below the detail line,
described in the stylesheet as echoing the top bar. At a glance it reads as a
progress bar that never moves. Posteria's wall did carry a real progress bar
(`.stream-progress`, driven by playback position), which makes a decorative
stand-in for one actively misleading rather than merely redundant. It is removed
outright; the banner's bottom padding already provides the spacing it occupied.

*Alternatives considered.* Simply lowering the clamp maximums keeps the wrong
reference frame — it fixes 1080p and makes 4K worse. Setting a `cqw` font-size on
`.wall__frame` and using `em` throughout works without container queries but
couples every child to an invisible parent value; container queries say what is
meant. Container queries are broadly supported, but the wall's target is a TV
browser, so this is the one decision worth confirming against the actual display
before it ships.

### Classify a live session as Live TV before matching its media type

In `HttpPlexClient::session()`, `live="1"` promotes a session to
`PlexSessionType::LiveTv` ahead of the `rawType` match. This is the reported
signal and it is present on the affected sessions.

The promotion is video-only: `type="track"` is excluded, so live radio stays
music and stays off the wall. Without that carve-out this change would silently
break the existing "Music is excluded" requirement. The existing `clip` + live
branch folds into the new path.

Downstream this needs nothing else: `NowPlayingTile::tokenFor()` already returns
the `LIVE` sentinel for `LiveTv`, `title()` already falls back to the program
title, and `detail()` already produces the channel/show plus "Live TV".

*Alternatives considered.* Keying off "the thumb is not a Plex-relative path"
also identifies these sessions and needs no trust in the `live` attribute — but
it infers intent from a symptom, and would misclassify any future non-live
session with an unusual thumb. It is kept as a second layer below rather than as
the primary signal.

### Never mint a token that cannot be redeemed

`NowPlayingTile::tokenFor()` gains a check: a thumb that does not begin with `/`
yields the `LIVE` sentinel instead of a signed token. `StreamToken::forThumb()`
and `thumbFor()` currently disagree about what is acceptable — `forThumb()` signs
anything, `thumbFor()` accepts only absolute Plex paths — and the existing
`StreamTokenTest::testRejectsANonAbsolutePath` codifies that asymmetry as
intended. The guard in `thumbFor()` stays exactly as it is, and that test stays
valid; the change is that nothing legitimate can reach it any more.

This is defence in depth behind the `live` classification, covering any other
source of a non-Plex thumb.

### Degrade to the placeholder at every remaining layer

Two more fallbacks, both small:

- `PosterWallController::streamPoster()` serves the placeholder when
  `thumbFor()` returns null, instead of raising `HttpNotFoundException`. This
  matches the `PlexException` branch immediately below it, which already falls
  back rather than erroring.
- `wall.js` honours `preload()`'s resolved boolean. Both call sites
  (`rotateRandom`, `rotateStreams`) currently discard it and set `.src`
  regardless. For a now-playing tile a false result swaps in
  `/wall/stream-poster/live`; for the random rotation it skips to the next
  poster.

Honouring the preload result also removes the duplicate request per tick — today
`preload()` fetches and then `reveal()` sets `.src`, which fetches again on
failure.

### Cache the Live TV placeholder, but not a placeholder standing in for failure

`PosterWallController::placeholder()` serves one `Cache-Control` for all of its
callers: `private, max-age=3600`. That suits the Live TV sentinel, whose URL
(`/wall/stream-poster/live`) always resolves to the same bundled art. It is wrong
for the two failure paths, because a poster token is deterministic for a given
thumb and secret — the URL is stable per item. One transient Plex failure caches
the placeholder against that item's URL for an hour, so the item keeps showing
the placeholder long after Plex recovers, and nothing retries because the request
never leaves the browser cache.

The method takes its cache policy from the caller. The Live TV sentinel keeps
`private, max-age=3600`; both failure paths get `private, max-age=10`.

`max-age=10` rather than `no-store`: a single reveal touches the poster URL three
times (the `preload()` probe, the `<img>`, and the background image), so
`no-store` would triple the request count on exactly the path that is already
failing. Ten seconds is long enough for those three to collapse into one and
short enough that the next poll or rotation re-requests. The coupling to
`ROTATE_MS`/`STREAM_POLL_MS` is soft — a longer or shorter tick only shifts how
promptly a recovered poster reappears, and nothing breaks either way.

The unresolvable-token path shares the short policy rather than getting a third
branch. Such a token is deterministically invalid so caching it would be harmless,
but two categories — "the intended art" and "a stand-in" — is one fewer thing to
reason about than three.

## Risks / Trade-offs

- **Container queries may not be supported by the target TV browser** → Verify on
  the actual display before merging. The `em`-on-frame fallback described above
  is a drop-in if needed.
- **A uniform reduction reads as too small** → Confirmed on the display and
  corrected: halving every element left the detail line at 10.6px and the badge
  at 10.9px, both well under Posteria's 16px and 19.2px. Sizes are now matched
  per-element against Posteria rather than scaled by a single factor.
- **Live promotion changes how non-DVR live sessions present** → Any session
  Plex marks live now shows the placeholder rather than library art. For genuine
  Live TV this is the intended behaviour; if some Plex feature marks a normal
  library item live, it would lose its poster. No such case is known, and the
  session type reported by Plex remains the fallback for everything not marked
  live.
- **DVR program artwork is not shown** → Accepted. The placeholder is the chosen
  outcome; proxying external hosts is out of scope for the reason given in
  Non-Goals, and could be revisited later behind a host allowlist.
