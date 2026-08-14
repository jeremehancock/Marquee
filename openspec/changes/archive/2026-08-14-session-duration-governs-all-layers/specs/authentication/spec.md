## MODIFIED Requirements

### Requirement: The session cookie's attributes are Marquee's decision
The system SHALL set the session cookie's attributes itself before the session
is started, rather than inheriting whatever the runtime's configuration
supplies. The cookie SHALL be marked `HttpOnly` and SHALL carry
`SameSite=Lax`.

`SameSite=Lax` is what stops another site driving a state-changing request with
the user's session attached. It is set here rather than left to the runtime
because a default is not a decision: an image rebuild, a base-image change, or a
different `php.ini` would silently remove it, and nothing would fail.

The system SHALL NOT set the `Secure` attribute. Marquee is routinely reached
over plain HTTP on a local network, and a `Secure` cookie is never sent over
HTTP, so setting it unconditionally would prevent logging in at all on those
installs. Withholding it is a deliberate trade, not an oversight.

The cookie SHALL carry an expiry of `SESSION_DURATION` rather than being
discarded when the browser closes. Left to the runtime the cookie is a
browser-session cookie, which ends a thirty-day window the moment a window is
closed — the server-side session remains perfectly valid, and the user is
locked out of it because the browser threw away the only reference to it.

That expiry SHALL slide, not be fixed at sign-in. Each request from an
authenticated session SHALL re-issue the cookie with its expiry set
`SESSION_DURATION` from that request, so the cookie's window tracks the
server-side window instead of drifting behind it. An expiry stamped once at
sign-in would reintroduce the absolute deadline this capability already rejects,
one layer down and invisibly: the session would still be live and the browser
would have stopped presenting it.

Re-issuing SHALL be tied to the renewal of an authenticated session. An
unauthenticated request SHALL NOT extend any cookie, so that a caller with no
session cannot lengthen one by asking.

#### Scenario: The cookie is not readable from script
- **WHEN** the session cookie is issued
- **THEN** it carries the `HttpOnly` attribute

#### Scenario: The cookie is withheld from cross-site requests
- **WHEN** the session cookie is issued
- **THEN** it carries `SameSite=Lax`

#### Scenario: Plain HTTP installs can still log in
- **WHEN** Marquee is reached over plain HTTP
- **THEN** the session cookie is sent with subsequent requests, because it is
  not marked `Secure`

#### Scenario: The session survives closing the browser
- **WHEN** an authenticated user closes the browser and reopens it within
  `SESSION_DURATION`
- **THEN** the session cookie is still present and the user is still
  authenticated

#### Scenario: Use slides the cookie's expiry
- **WHEN** an authenticated session makes a request
- **THEN** the session cookie is re-issued with an expiry of `SESSION_DURATION`
  from that request

#### Scenario: An unauthenticated request extends nothing
- **WHEN** a request arrives that is not from an authenticated session
- **THEN** no session cookie expiry is extended

## ADDED Requirements

### Requirement: Server-side session retention matches the configured duration
The system SHALL retain an idle server-side session for at least
`SESSION_DURATION`, and SHALL derive that retention from `SESSION_DURATION`
rather than leaving it to the runtime's default.

The runtime's default retention is twenty-four minutes. Against a thirty-day
window that default is the shorter by three orders of magnitude, so it — not
`SESSION_DURATION` — decides when a user is signed out. A session the
application still considers valid is discarded underneath it, and the user is
returned to a login that requires plex.tv for no reason they can observe.

Retention SHALL be applied before the session is started, for the same reason
the cookie's attributes are: a setting the application depends on but does not
set is a setting that can be removed by an image rebuild without anything
failing.

#### Scenario: An idle session survives well past the runtime default
- **WHEN** an authenticated session goes unused for longer than the runtime's
  default retention but less than `SESSION_DURATION`
- **THEN** the next request is still authenticated

#### Scenario: Retention follows the configured duration
- **WHEN** `SESSION_DURATION` is configured to a value other than the default
- **THEN** server-side retention is that value, not the runtime's default

### Requirement: A session is started only where one is needed
The system SHALL start a session only when serving a route that reads or writes
session state, and SHALL NOT start one for a route that reads none. Whether a
route needs a session is a separate question from whether an anonymous visitor
may reach it, and the system SHALL treat them separately.

This requirement changes no route's reachability. Every route reachable without
authentication SHALL remain so, and every protected route SHALL remain
protected. Only whether session state is created while serving a request
changes.

The health endpoint and the Poster Wall SHALL be served without starting a
session. Both are public, neither reads session state, and between them they
are the most frequently requested routes in the product: the health endpoint is
polled by the container runtime every thirty seconds for the life of the
container, and the wall polls for now-playing and rotates posters continuously
on an unattended display. Starting a session for each of those requests creates
session state that nothing will ever read, and — because the runtime collects
expired sessions probabilistically during session startup — makes those routes
the dominant trigger for the collection that evicts real sessions. A route that
needs no session should not be able to sign a user out.

The routes that render a page carrying a cross-site request token, and the
routes that start, poll, or end a sign-in, SHALL start a session. They read or
write session state, and are how a session is obtained or discarded.

#### Scenario: The health endpoint creates no session
- **WHEN** the health endpoint is requested
- **THEN** the response is served successfully
- **AND** no session is started

#### Scenario: The Poster Wall creates no session
- **WHEN** any Poster Wall route is requested
- **THEN** the response is served successfully
- **AND** no session is started

#### Scenario: Signing in still has a session
- **WHEN** an unauthenticated visitor requests the login screen, or starts or
  polls a Plex sign-in
- **THEN** a session is started, so that the sign-in request and the token can
  be held

#### Scenario: Reachability is unchanged
- **WHEN** an anonymous visitor requests any route
- **THEN** it is served or redirected to login exactly as it was before a
  session ceased to be started for session-less routes
