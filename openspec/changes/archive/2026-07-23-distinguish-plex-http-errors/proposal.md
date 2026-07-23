## Why

When a poster's linked Plex item no longer exists (an orphan), sending it to
Plex or fetching from Plex fails with "Could not connect to the Plex server.
Check the URL and token." That message is wrong and misleading: Plex answered
fine — it returned a 404 because the item is gone. The user has no way to tell
from the gallery that the poster is orphaned, so they chase a connection problem
that does not exist instead of visiting the Orphans page.

## What Changes

- Classify Plex HTTP failures by their response status instead of collapsing
  every Guzzle error into a single "connection failed" message:
  - **404** on a linked item → a distinct "item no longer exists in Plex, it may
    be orphaned — check the Orphans page" message.
  - **401** (rejected token) → a message that specifically calls out the token.
  - Genuine transport failures (DNS, refused, timeout) keep the existing
    "could not connect" message.
- Surface the new messages on the Re-send to Plex and Fetch from Plex actions so
  the user is pointed toward orphan cleanup rather than a false connection error.
- No new Plex calls: the distinction comes for free from the status of the
  request the user already triggered.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `poster-editing`: The Re-send and Fetch operations gain a requirement that a
  missing linked Plex item (and a rejected token) is reported distinctly from a
  server-connection failure, so the message can guide the user to the Orphans
  page.

## Impact

- `src/Plex/PlexException.php` — add distinct factory methods (e.g.
  `itemNotFound()`, `authFailed()`) alongside `connectionFailed()`.
- `src/Plex/HttpPlexClient.php` — inspect the caught Guzzle exception's HTTP
  status in the `get()`/`write()`/download paths and map 404/401 to the new
  exceptions; everything else stays `connectionFailed()`.
- No route, template, or database changes. Controllers already render
  `PlexException::getMessage()` to a flash message, so new messages flow through
  unchanged.
