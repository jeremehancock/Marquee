## Why

Nothing about a state-changing request to Marquee proves the user meant to make
it. Every action that deletes a poster, overwrites artwork in Plex, or
disconnects the install accepts a plain `POST` with a session cookie attached,
and a session cookie is attached to any request the browser makes — including one
a hostile page provoked.

`SameSite=Lax` closes most of this, but not the case Marquee is actually deployed
into. "Site" ignores the port, so every other container on the same host address
is same-site with Marquee and its requests still carry the cookie. A self-hosted
box runs a dozen of them.

## What Changes

- A state-changing request must carry a secret that only a page Marquee rendered
  could know. Requests without it are refused before they reach a handler.
- The secret is bound to the session, so one browser's cannot be used by another.
- Every form Marquee renders and every scripted request it makes carries it
  automatically. Nothing about how a user works changes.
- Read-only requests are untouched. The poster wall, the health endpoint, and
  every page load behave exactly as before.
- Protection applies with `AUTH_BYPASS` on. Bypass removes the login, which
  makes forged requests more valuable rather than less.

Explicitly out of scope: the `Secure` cookie attribute, still withheld for the
reason recorded in the session-hardening change — it would stop plain-HTTP LAN
installs logging in.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `authentication`: gains a requirement that state-changing requests be proven
  to originate from Marquee's own pages, alongside the existing requirements
  covering who may reach a route. The session-based login, expiry, logout, and
  bypass requirements are unchanged.

## Impact

- New: a session-backed token service and a middleware that enforces it.
- `src/bootstrap.php` — registers the middleware so it runs after the request
  body is parsed, and exposes the token to templates.
- `templates/` — seven forms across five templates gain a hidden field; the
  shared layout gains a meta tag so scripts can read the token.
- `public/assets/gallery.js` — six `fetch` calls send the token as a header.
  They already send a custom header and same-origin credentials, so this adds a
  line to an existing options object rather than a new mechanism.
- Tests: unit coverage for the token and the middleware, plus functional
  coverage that each state-changing route refuses a request without one.
- No configuration, database, or Docker change. Existing sessions are already
  being invalidated by the session-hardening change in this release, so users
  see one re-login, not two.
