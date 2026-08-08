## Context

[`NativeSession::start()`](../../../src/Support/Session/NativeSession.php) calls
`session_start()` with no preceding configuration, so `SameSite`, `HttpOnly`,
and `Secure` come from the base image's `php.ini`. Nothing in this repository
sets them, and nothing fails if they are absent — which is what makes it worth
fixing now rather than after a base-image bump silently changes them.

[`SessionAuthenticator::attempt()`](../../../src/Auth/SessionAuthenticator.php)
marks the arriving session authenticated in place:

```
  browser arrives with SID=abc  ──▶  attempt() sets authenticated=true
                                     on SID=abc
                                            │
                        anyone who knew abc beforehand
                        now holds an authenticated session
```

Both are pre-existing. They matter more in 2.0.0 than they did in 1.x, because
this is the release where a stored Plex credential first sits behind that
session: a session is now a key to somebody's media server, not just to a poster
gallery.

## Goals / Non-Goals

**Goals:**

- Decide the cookie's attributes in code, so they survive a base-image change.
- Make an identifier that existed before login worthless after it.
- Keep authentication logic free of PHP's session functions, so it stays
  unit-testable against `ArraySession`.

**Non-Goals:**

- The `Secure` attribute — see the trade below.
- CSRF tokens. `SameSite=Lax` handles the cross-site case; the same-host case is
  a separate change.
- Any change to credentials, expiry, logout, or `AUTH_BYPASS`.

## Decisions

### Regeneration goes on the interface, not in the authenticator

`SessionInterface` gains `regenerate(): void`. `SessionAuthenticator` calls that
rather than `session_regenerate_id()` directly.

Calling the native function from the authenticator would drag global session
state into a class whose whole existing test suite runs against `ArraySession`,
and would make the unit tests either skip the new behaviour or start a real
session. The interface exists precisely so "authentication logic never touches
PHP's session superglobals directly"; regeneration is the same kind of operation
as `clear()`, which is already there.

`ArraySession::regenerate()` counts its calls and exposes
`regenerations(): int`. A no-op double would leave the requirement untestable at
the unit level — the assertion has to be able to see that it happened.

### Regenerate before marking the session authenticated

```php
$this->session->regenerate();
$this->session->set(self::KEY_AUTHENTICATED, true);
```

`session_regenerate_id(true)` carries data across either way, so the end state is
identical. Doing it first means the pre-login identifier never, at any instant,
has `authenticated => true` written under it.

*Alternative considered:* regenerating inside `AuthController` after a successful
`attempt()`. Rejected — it puts a security-critical step in a thin controller,
where the next caller of `attempt()` would silently not get it.

### Only a successful attempt regenerates

A failed login returns before touching the session. Rotating on failure would let
an unauthenticated caller churn identifiers at will, and there is nothing to
protect: no privilege has been granted.

### `use_strict_mode` is part of this change, not a separate one

Regeneration alone closes half of session fixation. It makes a *known* identifier
worthless after login, but does nothing to stop the identifier being planted:
without `session.use_strict_mode`, PHP adopts any well-formed identifier the
browser presents and creates a session for it. Set together they close the loop,
which is why the spec requires both rather than treating strict mode as
incidental hardening.

It is set with `ini_set()` in `start()` alongside the cookie parameters, for the
same reason as the cookie attributes: so it is a decision in this repository
rather than a property of an image.

### `Secure` is deliberately absent

This is the one place the change knowingly stops short.

| | With `Secure` unconditionally | Without |
| --- | --- | --- |
| HTTPS install | Cookie protected from downgrade | Cookie could ride a plain-HTTP request |
| Plain-HTTP LAN install | **Cannot log in at all** | Works |

Marquee is documented and deployed as a LAN app reached at `http://host:port`.
Breaking login for those users to harden a transport they are not using is the
wrong trade, especially in a release that already requires everyone to touch
their compose file.

*Alternative considered:* deriving it from the request scheme or
`X-Forwarded-Proto`. Rejected for now — it makes a security attribute depend on a
client-supplied header, and behind a misconfigured proxy it fails toward the
lockout. An explicit opt-in setting is the better shape if this is ever wanted.

### Cookie parameters must precede `session_start()`

`session_set_cookie_params()` has no effect once the session is active, so it
goes at the top of `start()`, inside the same `session_status()` guard that
already prevents double-starting.

## Risks / Trade-offs

**Every existing session is invalidated on upgrade** → Users log in again once.
Acceptable, and unavoidable: changing the cookie's attributes is the point.
Worth noting it coincides with 2.0.0 already requiring a re-connection to Plex,
so it adds nothing to a migration users are performing anyway.

**`SameSite=Lax` does not cover same-host attackers** → Named openly in the
proposal rather than left implied. "Site" ignores the port, so another container
on the same host address is same-site. This change does not claim to fix that;
CSRF tokens do.

**The Plex sign-in stores its pin in the session, and login regenerates** →
`session_regenerate_id(true)` preserves session contents, so an in-flight
authorization request survives. Covered by a scenario so it cannot regress
silently.

**`ini_set('session.use_strict_mode')` may be ignored if the session is already
active** → The same `session_status()` guard covers it, and the front controller
starts the session exactly once.

## Migration Plan

None beyond the one-time re-login. No configuration to add, nothing to roll
forward. Rollback is reverting the code; users log in once more.

## Open Questions

None.
