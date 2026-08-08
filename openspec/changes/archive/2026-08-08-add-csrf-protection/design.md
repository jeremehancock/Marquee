## Context

Eleven `POST` routes change state, and none of them verifies that the request
came from a Marquee page. The damaging ones are `/orphans/delete-all`,
`/library/{category}/delete`, `/plex/import`, `/library/{category}/send-to-plex`,
and `/plex/connection/sign-out`.

Two facts from the codebase shape the whole design:

**All six scripted `POST`s already send a custom header and same-origin
credentials.** Every one carries `X-Requested-With: 'fetch'` and
`credentials: 'same-origin'`, so there is an existing options object to add one
line to rather than a new mechanism to introduce.

**Three of the six build their body with `new FormData(form)`.** A hidden field
in the form flows into those automatically. The other three build a synthetic
body or send none at all, and have no form to draw from.

That split is why both carriers exist. Neither alone covers every call site
without contorting one of them.

## Goals / Non-Goals

**Goals:**

- Refuse a forged state-changing request before any handler acts on it.
- Cover the same-host case that `SameSite=Lax` does not.
- Keep the token off every URL.
- Change nothing about how a user works, with scripting on or off.

**Non-Goals:**

- The `Secure` cookie attribute — decided in `harden-session-cookie`.
- Per-request rotating tokens. Per-session is what the threat needs; per-request
  breaks the back button and multi-tab use for no gain here.
- Protecting `GET` routes. A `GET` that changes state would be the bug, and
  there isn't one.

## Decisions

### One token per session, not per request

Generated on first use and held for the session's life. Compared with
`hash_equals`.

Per-request tokens defend against an attacker who has already read one token and
wants to replay it — which requires the ability to read the response, at which
point same-origin is already lost and the token is the smaller problem.
Per-request costs real usability: two tabs invalidate each other, and the back
button lands on a page whose token is dead. For a single-user self-hosted app
that trade is clearly wrong.

### Both a field and a header are accepted

| Carrier | Serves |
| --- | --- |
| `_token` form field | Native form submits, and the three fetches built with `new FormData(form)` |
| `X-CSRF-Token` header | The three fetches with a synthetic body or no body |

Rendering the field into forms means a form works whether it is submitted
natively or scraped into `FormData`, which is what keeps scripting-off behaviour
correct without a second code path.

*Alternative considered:* header only, with the script attaching it everywhere.
Rejected — it makes every form depend on JavaScript to submit at all, which the
login form in particular must not.

### A Twig function, not a global

Registered next to the existing `asset()` function.

The token needs the session, and the session is started by `AuthMiddleware`
during the request. A Twig *global* is evaluated when the Twig service is
constructed, which invites an ordering bug the first time the container is built
earlier. A function is evaluated at render time, when the session is
unambiguously live.

`csrf_field()` emits the hidden input; `csrf_token()` returns the raw value for
the layout's meta tag, which is where the script reads it from.

### The middleware runs innermost

Slim executes the last-added middleware first, so the current chain is:

```
  error → routing → auth → plex gate → body parsing → handler
```

The check needs a parsed body to read `_token`, so it is added *before*
`addBodyParsingMiddleware()`, which places it inside that layer:

```
  error → routing → auth → plex gate → body parsing → CSRF → handler
```

It therefore also runs after the session exists, which `AuthMiddleware`
guarantees by calling `start()` on every request — including public ones, so a
token is available on the login page before anyone has authenticated.

### The token is not rotated on login

`harden-session-cookie` regenerates the session identifier on login, and
`session_regenerate_id(true)` carries session contents across, so the token
survives.

Rotating it as well was considered and rejected. The attack it would answer is
an attacker priming a token before the victim logs in — but the token is bound
to the session, `use_strict_mode` stops an identifier being planted, and
regeneration replaces the identifier anyway. The attacker's token belongs to the
attacker's session and is worthless against the victim's. Rotating would couple
`SessionAuthenticator` to a CSRF key it otherwise knows nothing about, to close a
hole that is already closed twice.

### Refusal is a 403 through the existing error handler

Rather than a hand-built response. The application already renders errors as
HTML or JSON according to `Accept`, and the scripted callers already treat a
non-OK response as a failure. Reusing it means the refusal looks like every other
error rather than a special case.

### Logging in is the one exception

A token mismatch on `POST /login` re-renders the login page with an explanation
instead of raising 403.

This is not politeness, it is the one place the plain refusal misleads. PHP
sessions live in the container's `/tmp`, so `docker compose up -d` discards every
one of them. Everywhere else that does not matter: a stale page posting after a
restart is unauthenticated, `AuthMiddleware` redirects it to the login page, and
the CSRF check is never reached. Login is the only state-changing route reachable
with no session behind it, so it is the only one where a user meets the token
check after their session has gone — and an error page at that moment reads as a
broken installation rather than as a page to submit again.

The gap is widest with `AUTH_BYPASS` on, where there is no auth redirect to
absorb the stale request first.

The carve-out is one branch keyed on the request path, and it refuses exactly as
hard as the general case: nothing is authenticated, nothing is stored. Only the
rendering differs.

*Alternative considered:* exempting `/login` from the check entirely. Rejected —
it is cheap to keep, and dropping it would mean the one route an unauthenticated
stranger can reach is also the one route with no origin check.

## Risks / Trade-offs

**A form or fetch is missed, and that action silently stops working** → The
realistic failure mode, and the reason the task list enumerates all thirteen
sites individually rather than saying "update the forms". Functional coverage
asserts each state-changing route refuses an untokened request, which catches a
missed *middleware* gap; a missed *template* is caught by the existing
functional tests for that action starting to fail.

**Partials re-rendered into the page must carry tokens too** →
`templates/partials/gallery_results.html.twig` and
`templates/orphans/_results.html.twig` are fetched and injected via `DOMParser`.
They are rendered server-side by the same Twig environment, so `csrf_field()`
resolves in them normally. Called out because injected markup is easy to forget.

**A page left open past a session expiry posts a dead token** → It already posts
a dead session and is redirected to login. The token adds no new failure here;
the user logs in and retries, as before.

**`AUTH_BYPASS` installs get a check they cannot see the point of** → Kept on
deliberately, and specified. With no login, a forged request is worth more, not
less.

## Migration Plan

None. No configuration to add. Sessions are already invalidated once by
`harden-session-cookie` shipping in the same release, so users see a single
re-login covering both changes.

## Open Questions

None.
