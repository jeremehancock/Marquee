## Context

Marquee reaches Plex with a token supplied as `PLEX_TOKEN`. `PlexConfig::fromEnv()`
reads it once at bootstrap, `HttpPlexClient` sends it as `X-Plex-Token`, and
`templates/plex.html.twig` tells an unconfigured user to set the variable and
restart the container.

Three properties of the current system shape this design, all confirmed against
the running image rather than assumed:

- **The scheduled import has no session.** `bin/auto-import.php` is a separate
  PHP CLI process started by busybox cron. It builds its own container with no
  HTTP request behind it, so any credential it needs must be readable from disk.
- **Cron crosses no privilege boundary.** `crond` runs jobs as `abc`, the same
  user as the php-fpm pool (`user = abc` in `www.conf`) and the owner of
  `/config`. A file readable by the web app is readable by cron and vice versa.
- **The environment already reaches cron by inheritance.** `svc-cron/run` is a
  `with-contenv` script, so `crond` and its children inherit the container
  environment directly. `docker-env.sh` contributes nothing to this and its
  `PLEX_`/`AUTO_IMPORT_` patterns match nothing, which is harmless and out of
  scope here.

The application also has a latent coupling: `bootstrap.php:93` uses the Plex
token as the HMAC secret for poster-wall now-playing tokens. That is safe while
the token is a boot-time constant and stops being safe once it becomes mutable
user state.

## Goals / Non-Goals

**Goals:**

- Obtain a Plex token by signing in to Plex from within the application
- Break nothing for anyone currently setting `PLEX_TOKEN`, with no migration
- Keep the scheduled auto-import working with a token obtained by signing in
- Make the active connection legible wherever a user acts on Plex
- Give Plex failures a remedy that matches how the connection was made
- Add no new security exposure relative to a token in `docker-compose.yml`

**Non-Goals:**

- Replacing Marquee's own username and password login. `AUTH_USERNAME`,
  `AUTH_PASSWORD`, `AUTH_BYPASS`, and sessions are untouched. Signing in to Plex
  is an action taken by an already-authenticated user.
- Server discovery or a server picker. `PLEX_SERVER_URL` stays a manual setting.
- Deprecating or removing `PLEX_TOKEN`.
- Reporting whether Plex is reachable right now.
- Supporting Marquee at a URL sub-path, which it does not support today.

## Decisions

### Popup and polling, not a redirect back to Marquee

Plex's PIN flow can either redirect the browser back to a `forwardUrl` or let
the caller poll for completion. Marquee polls.

The redirect variant requires Marquee to know its own externally reachable URL
and to trust `X-Forwarded-*` headers to construct it. Marquee has no such code —
the only `getUri()` call in the codebase reads a path in `AuthMiddleware` — and
self-hosted deployments behind a reverse proxy are exactly where guessing that
URL goes wrong. The redirect is also a cross-site top-level navigation back into
Marquee, which a `SameSite=Strict` session cookie would not survive.

Polling needs none of that. The browser opens `app.plex.tv` in a new window,
Marquee's server talks to `plex.tv` directly, and the page polls its own origin.
No inbound callback ever traverses the proxy.

Two constraints follow:

- The Plex window MUST be opened synchronously inside the click handler.
  Awaiting the request that creates the authorization code and opening the
  window afterwards gets blocked as a popup.
- Polling MUST be short-interval request/response, never long-polling or SSE.
  `proxy_buffering` and CDN request caps mangle held-open connections. The
  existing wall poller in `public/assets/wall.js` is the pattern to follow.

### The environment keeps precedence, and nothing is migrated automatically

`PlexConfig` resolves its token from the environment first and the store second.

Precedence this way round means an existing deployment behaves identically, the
test suite keeps working unchanged (`tests/AppTestCase.php` sets `PLEX_TOKEN`),
and automated or GitOps deployments retain a non-interactive path. The reverse
precedence would let an in-app action silently override a deliberately declared
configuration, which is worse in every scenario.

Copying an existing `PLEX_TOKEN` into the store at boot would make removing the
variable a single step, and is rejected: it persists a credential the user did
not ask to persist, and it creates a stale-copy trap where rotating `PLEX_TOKEN`
leaves an old token waiting to take over.

The cost is a state where a stored token exists but is not in use. That state is
real and must be surfaced explicitly rather than papered over — see the panel
below.

### The token lives in a file under the data directory, not in SQLite

`marquee.sqlite` is documented and specified as a pure cache that is safe to
delete. Putting a credential in it would make deleting it a destructive act and
would force the recreatable-state invariant to bend further than necessary.

A separate file under the data directory, written `0600` and owned by `abc`,
keeps the database a cache, gives the credential its own explicit permissions
rather than an inherited umask, and is readable by cron for free because cron
runs as the same user.

The same file holds the generated client identifier, so repeated sign-ins
present Marquee to Plex as one device rather than accumulating entries.

### Server identity comes from the Plex server, and it is the server's name

The panel needs something to display. `GET /` on the Plex Media Server returns
both `friendlyName` and `myPlexUsername`, and `myPlexUsername` is an email
address.

The panel shows `friendlyName`. It is not personal, which matters because this
panel is precisely what users screenshot into support threads; it identifies
which server is connected, which is more operationally useful for a poster
manager than which account; and obtaining it at all proves the URL and the token
work together.

Using the Plex server for this — rather than `plex.tv/api/v2/user` — means one
code path for both connection sources, no divergence between them, and no
`plex.tv` dependency for anyone who never signs in.

### Cached status app-wide, and no health check anywhere

The connection status appears with the application's other status information,
which renders on every authenticated page. It is served from a cached server
name, refreshed when the connection panel renders.

Contacting Plex on every page render was rejected: `PLEX_CONNECT_TIMEOUT`
defaults to 10 seconds, so an unreachable Plex server would stall every page for
up to ten seconds — worst exactly when the user needs the interface to work.

Restricting a live indicator to the import page was also rejected, because Send
to Plex and Fetch from Plex are gallery actions; an indicator that is accurate
only on the page where those actions do not happen is worse than none.

The cached name is Plex data and therefore recreatable from Plex, so it belongs
in SQLite and needs no exception to the persistence invariant. The status is
text and carries no health claim — no coloured dot, because a dot that is one
page-load stale asserts something this design deliberately does not check.

### Plex errors carry a reason; the presentation layer supplies the remedy

`PlexException`'s messages currently embed the fix: "Check `PLEX_TOKEN`.",
"Set `PLEX_SERVER_URL` and `PLEX_TOKEN`." Those become actively misleading for a
signed-in user, so they have to change regardless of anything else in this
change.

Passing configuration into the exception would put presentation logic in a value
object. Instead the exception carries a typed reason — not configured, rejected
credential, connection failed, item missing, unexpected response — and the
presentation layer renders the remedy for the source actually in use.

This is also why no health indicator is needed: every entry point that can fail
already surfaces these errors, so making them source-aware gives the right advice
at the moment of failure, everywhere, at no runtime cost.

### The poster wall gets its own signing secret

`StreamToken` signs the now-playing poster proxy's tokens so it cannot be used
to fetch arbitrary paths from Plex. Its secret is currently the Plex token.

Once the token is mutable, signing in again rotates the secret and invalidates
tokens already on the wall; and a secret that can be empty makes signatures
computable by anyone. Neither is reachable today, and both become reachable if
the coupling is left in place.

A random secret generated once and stored alongside the connection removes the
coupling. No wall behaviour changes, so this is an implementation concern rather
than a specified one — but it needs a regression test proving that signing in
again does not break wall tokens.

### No server discovery

Resolving `PLEX_SERVER_URL` automatically from `plex.tv/api/v2/resources` is the
obvious follow-on and is excluded. It returns candidate connection URIs that
Marquee would then have to fetch — the one genuinely SSRF-shaped surface in the
whole feature — and container networking makes probing them unreliable. Keeping
the server URL manual holds that surface at zero.

## Risks / Trade-offs

- **The credential now enters `/config` backups** → Accepted and documented.
  Today it lives in `docker-compose.yml`, which people commit to version control
  and paste into support threads; that is the leak that actually happens. The
  new file is `0600` and absent from `docker inspect`.

- **A stored-but-overridden token can mislead** → The panel names this state
  explicitly rather than showing a bare "signed in". Without that, a user who
  removes `PLEX_TOKEN` and restarts could find the stored token belongs to a
  different account.

- **`AUTH_BYPASS=true` lets any LAN visitor sign in or out** → Documented, not
  fixed. Bypass already grants deleting every poster; this is consistent with
  its stated "trusted network only" contract.

- **No CSRF protection exists anywhere in the application** → Pre-existing and
  unchanged by this work, so not a regression. It now guards a credential as
  well as destructive actions, which strengthens the case for addressing it in
  its own change.

- **The cached server name can go stale** → Cosmetic only. Renaming a Plex
  server leaves the old name in the status until the connection panel is next
  visited.

- **`plex.tv` becomes an outbound dependency** → Only for the sign-in flow.
  Deployments that set `PLEX_TOKEN` never contact it, so air-gapped and LAN-only
  installs are unaffected. Outbound HTTPS is already exercised by the poster
  source and the update check.

- **Popup blockers** → The likeliest practical failure, and not proxy-related.
  Mitigated by opening the window inside the click gesture and by offering a
  visible link as a fallback.

## Migration Plan

No migration is required and no user action is ever forced. `PLEX_TOKEN` keeps
working indefinitely.

Because the environment wins, an existing deployment will never surface the
sign-in flow on its own — so the panel must offer signing in *while* the
variable is still set. That gives a zero-downtime opt-in path:

1. Sign in to Plex from the connection panel. The token is stored but not yet in
   use.
2. Remove `PLEX_TOKEN` from `docker-compose.yml`.
3. Restart. The stored token takes over with no gap.

Refusing to store a token until the variable is removed would invert this and
leave Plex unconfigured between the restart and the sign-in, during which import
fails and a scheduled auto-import would skip.

Rollback at any point is putting `PLEX_TOKEN` back and restarting; it wins again
immediately, whatever is stored.

The in-app update notice cannot carry this explanation — it sets a single string
of the form "Update available (vX)" with no link or body — so the release notes
and `docs/plex-connection.md` are the only prose channels, and the panel copy
must stand on its own.

## Open Questions

- Does the service worker in `public/sw.js` cache pages in a way that would
  serve a stale connection panel after signing in or out? Unverified; check
  during implementation and add a cache exclusion if so.
- Confirm the exact `plex.tv` PIN expiry so the client stops polling when the
  authorization request can no longer succeed, rather than on a guessed timeout.
