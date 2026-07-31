## Why

On a phone, opening the Orphans tray a second time shows the results of the first
scan — no loading indication, no fresh check of Plex. Nothing tells the user the
list is old, so it reads as current. That invites deleting a poster that has
since stopped being an orphan, which is the one mistake this screen exists to
help avoid.

Desktop does not have the problem: there the Orphans link is a normal navigation,
so every visit re-scans. Users calibrate on that behaviour and reasonably expect
the tray to match it.

## What Changes

- Opening the orphans tray SHALL re-scan Plex every time, not only the first time
  in a page session. Reopening shows the tray's loading state and then the current
  result, exactly as the first open does.
- The import tray keeps fetching its contents once. That is not an inconsistency
  to be tidied away later: the import tray holds a configuration form, which does
  not go stale, while the orphans tray holds a scan result, which is precisely the
  thing that does. The specs are updated to say so, so the difference reads as a
  decision rather than an oversight.
- No new refresh button, staleness window, or time-based cache. Reopening is the
  refresh gesture.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the requirement covering import and orphans inside their trays
  gains the freshness guarantee — reopening the orphans tray SHALL re-scan and
  SHALL show its loading state, while the import tray's contents may still be
  fetched once, because a form does not go stale and a scan result does.

## Impact

- `public/assets/gallery.js` — `openOrphans` currently returns early once the tray
  has been loaded. That guard is what suppresses both the re-scan and the spinner.
- `tests/Unit/Asset/` — a tripwire covering the reopen path, in the style of the
  existing asset tests.

Notes and risks:

- **This is not a regression from `fix-mobile-tray-dismissal`.** The caching guard
  dates to `e3970df` (2026-07-27); that change touched neither `openOrphans` nor
  the shared tray loader. It only made the bug easier to reach, because closing
  the tray became reliable enough that people started reopening it instead of
  reloading the page.
- Re-scanning on every open costs a Plex round trip the cached path avoided. That
  is the intended trade: the scan is the whole value of the screen, and a fast
  wrong answer is worse than a slow right one here.
- The obvious implementation — clearing the loaded flag when the tray closes — is
  unsafe for a reason that is not visible at the call site. See `design.md`; it
  must not be adopted.
- No PHP, routing, or data behaviour changes. Orphan detection and deletion
  themselves are untouched; only when the scan is run changes.
