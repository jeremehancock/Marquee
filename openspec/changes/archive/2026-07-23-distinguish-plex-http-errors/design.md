## Context

All outbound Plex calls run through `HttpPlexClient`, which wraps Guzzle with
`http_errors => true`. Every `catch (GuzzleException $e)` block in that client
collapses the failure into a single `PlexException::connectionFailed()`
regardless of cause. Because a non-existent `ratingKey` returns HTTP 404, an
orphaned poster's Re-send (`uploadPoster` → `write()`) or Fetch (`itemPoster` →
`get()`) surfaces the false "Could not connect to the Plex server. Check the URL
and token." Controllers already render `PlexException::getMessage()` into a
flash message, so improving the message requires no controller or template work.

## Goals / Non-Goals

**Goals:**
- Map HTTP 404 on a Plex request to a distinct, orphan-pointing message.
- Map HTTP 401 to a token-specific message.
- Preserve the existing "could not connect" message for genuine transport
  failures.
- Add zero new Plex requests; classify from the exception already thrown.

**Non-Goals:**
- No gallery badge or proactive orphan scan (possible later follow-up).
- No change to how orphans are detected, listed, or deleted.
- No new routes, templates, config, or database changes.

## Decisions

**Classify by inspecting the caught Guzzle exception's response status.**
Guzzle's `RequestException` (the base for `ClientException`/`ServerException`
raised under `http_errors`) carries the PSR-7 response via `hasResponse()` /
`getResponse()`. In each catch block, read the status code when a response is
present and branch: `404 → PlexException::itemNotFound()`, `401 →
PlexException::authFailed()`, everything else (including `ConnectException`,
which has no response) → `PlexException::connectionFailed()`.

- _Alternative — catch `ClientException` separately by type:_ rejected. A
  `ConnectException` is also a `GuzzleException` and has no response; branching
  on status inside one catch handles both cleanly without ordering pitfalls.
- _Alternative — inspect status in the controller:_ rejected. The controller
  only sees a `PlexException` and has no HTTP context; classification belongs at
  the boundary where the HTTP response exists.

**Add two factory methods to `PlexException`** — `itemNotFound()` and
`authFailed()` — alongside the existing `connectionFailed()`. Their messages are
user-facing (the class already documents its messages as safe to show):
- `itemNotFound()`: names that the item no longer exists in Plex, that the
  poster may be orphaned, and points to the Orphans page.
- `authFailed()`: names that the Plex token was rejected.

**Centralize the mapping in one private helper** so every catch block in
`HttpPlexClient` (the two in `get()`/`write()` plus the poster-download paths in
`downloadPoster()` and `itemPoster()`) classifies identically. The helper takes
the caught `GuzzleException` and returns the appropriate `PlexException`; each
catch does `throw $this->classify($e);`.

## Risks / Trade-offs

- **A 404 could theoretically come from a wrong base path, not a gone item.** →
  In practice a misconfigured base URL fails as a `ConnectException` (no
  response) and stays "could not connect"; a 404 with a response body on a
  metadata endpoint means Plex is reachable and the item is absent, which is
  exactly the orphan case. Acceptable.
- **The message says "may be orphaned," not "is orphaned."** → Intentional: the
  operation confirms the item is gone but not the full orphan bookkeeping;
  pointing to the Orphans page lets the existing detection confirm and clean up.

## Migration Plan

Pure code change, no data or config migration. Ships in a normal release; roll
back by reverting the commit. Covered by unit tests that stub the HTTP client to
return 404/401/transport-error and assert the mapped exception message.

## Open Questions

None.
