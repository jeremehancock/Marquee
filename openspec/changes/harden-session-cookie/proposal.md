## Why

Marquee's session cookie is created with a bare `session_start()`, so its
security attributes are whatever the base image's `php.ini` happens to ship.
Nothing in this repository decides them. The session is what stands between a
visitor and a stored Plex credential, and its defences are currently inherited
by accident.

A successful login also reuses the session identifier the browser arrived with.
Anyone able to plant an identifier in a user's browser beforehand holds a valid
authenticated session the moment that user logs in, without ever knowing the
password.

## What Changes

- The session cookie's attributes are set by Marquee rather than inherited:
  `SameSite=Lax` and `HttpOnly`, decided in one place and true on every install.
- Logging in issues a **new** session identifier, so an identifier that existed
  before authentication cannot survive it.
- PHP is told to reject a session identifier it did not issue, which is what
  stops one being planted in the first place.
- No change to how anyone logs in, how long a session lasts, or what
  `AUTH_BYPASS` does. Nothing becomes newly reachable or unreachable.

Explicitly out of scope:

- **The `Secure` attribute.** Marquee is routinely reached over plain HTTP on a
  LAN. A `Secure` cookie is never sent over HTTP, so setting it unconditionally
  would lock every such install out of its own login — in the release that
  already asks everyone to reconfigure. It can be offered later as an explicit
  opt-in for installs behind TLS.
- **CSRF tokens.** `SameSite=Lax` stops the cross-site request, but "site"
  ignores the port, so every other container on the same host address remains
  same-site with Marquee. Closing that needs per-request tokens, which is its
  own change with its own surface.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `authentication`: gains two requirements — one fixing the session cookie's
  attributes as Marquee's decision rather than the runtime's default, and one
  requiring a fresh session identifier on login. The existing login, expiry,
  logout, and bypass requirements are unchanged.

## Impact

- `src/Support/Session/SessionInterface.php` — a way to ask for a new
  identifier, so authentication never touches PHP's session functions directly.
- `src/Support/Session/NativeSession.php` — sets the cookie parameters before
  starting the session, and implements regeneration.
- `src/Support/Session/ArraySession.php` — implements it too, observably, so the
  behaviour can be asserted without global session state.
- `src/Auth/SessionAuthenticator.php` — regenerates on a successful attempt.
- Tests: `tests/Unit/Auth/`, plus the functional login coverage.
- No configuration, database, template, or Docker change. Existing sessions are
  invalidated once on upgrade; users log in again.
