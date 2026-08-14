## Why

`SESSION_DURATION` promises a thirty-day window that slides with use, and the
application enforces it — but only in the one layer it owns. The browser cookie
and PHP's session garbage collector never heard of it, so they run on defaults
nobody chose: the cookie is discarded when the browser closes, and the session
file is deleted after twenty-four minutes of disuse. Both of those are shorter
than thirty days by several orders of magnitude, and whichever fires first is
what the user actually experiences.

The result is a login that fails in exactly the two ways reported: closing the
browser loses the session, and walking away from an open browser loses it too.
Signing in again requires plex.tv, so every one of these evictions puts a
third-party dependency in front of a user who did nothing wrong.

## What Changes

- `SESSION_DURATION` governs the browser cookie's lifetime, not just the
  server-side expiry. The cookie's expiry slides with use in the same way the
  server-side window does, rather than being stamped once at sign-in.
- `SESSION_DURATION` governs how long PHP retains an idle session server-side,
  replacing the twenty-four-minute default that currently evicts sessions long
  before their window has elapsed.
- A session is started only for requests that need one. The health endpoint and
  the Poster Wall — the two highest-traffic routes in the product, and the two
  that read nothing from a session — stop creating one per request. **This does
  not change which routes an anonymous visitor may reach.** It changes only
  whether a session is started while serving them.
- The session abstraction gains the ability to express "extend the browser's
  copy of this session", so that the cookie's behaviour can be asserted in tests
  rather than assumed.

Not breaking. `SESSION_DURATION` keeps its name, its units, its default, and its
documented meaning; it simply starts being honoured everywhere instead of in one
place. No configuration needs to change on an existing install.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `authentication`: The requirement governing the session cookie's attributes
  currently names `HttpOnly`, `SameSite`, and the deliberate absence of
  `Secure`, but says nothing about lifetime — leaving the one attribute that
  decides how long a sign-in lasts to a runtime default. It gains lifetime, and
  states that the lifetime slides rather than being fixed at sign-in.
- `authentication`: A new requirement gives server-side session retention an
  owner. Nothing currently specifies how long the server keeps an idle session,
  which is why it keeps one for twenty-four minutes.
- `authentication`: The routing requirement conflates two distinct questions —
  whether an anonymous visitor may reach a route, and whether serving that route
  needs a session. It gains a requirement that separates them, so that a public
  route is not obliged to manufacture session state it never reads.

## Impact

- **Code**: `App\Support\Session\SessionInterface` and its two implementations
  (`NativeSession`, `ArraySession`); `App\Auth\SessionAuthenticator`;
  `App\Auth\AuthMiddleware`; the session wiring in `src/bootstrap.php`.
- **Configuration**: None. No new environment variable, no changed default.
- **Docker image**: None. The PHP settings involved are applied by the
  application before the session starts, not by an ini file, so the fix travels
  with the code rather than with the image.
- **Operational**: Nothing here invalidates a session. Deploying a new image
  recreates the container and clears `/tmp`, which already ends every session
  on every update — so users sign in once, as they do today, and then stop
  being asked repeatedly.
- **Docs**: `README.md` describes `SESSION_DURATION` as idle time renewed by
  use. That description becomes true for the first time; verify the wording
  still reads correctly rather than assuming it does.

### Deferred, deliberately

Session files live in the container's `/tmp`, so recreating the container
discards every session and every user signs in again after an update. Moving
them to `/config` would fix that, and is genuinely worth doing — but `/config`
is routinely a network share on a self-hosted install, and PHP's file session
handler takes a `flock()` on every request. That trades a login annoyance for a
class of hang the application does not currently have, on a path every request
travels. It needs its own decision and its own change; it is not folded in here.
