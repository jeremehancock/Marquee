## Context

Marquee reaches Plex with a token supplied as `PLEX_TOKEN`. `PlexConfig` reads
it once at bootstrap, `HttpPlexClient` sends it as `X-Plex-Token`, and an
unconfigured user is told to set the variable and restart the container.

A first cut of this change added signing in *alongside* `PLEX_TOKEN`, with the
variable taking precedence. Testing the built image showed the cost of that
choice: two ways to connect meant four connection states, a status line that had
to explain which one was live, and a "signed in but not in use" state that
existed only to stop the interface lying. None of that complexity served a user
who just wants Marquee talking to their server. Marquee is in alpha, so the
simpler design — one way in — is worth a one-time break.

Three properties of the running system shape the rest, all confirmed against the
built image rather than assumed:

- **The scheduled import has no session.** `bin/auto-import.php` is a separate
  PHP CLI process started by busybox cron. It builds its own container with no
  HTTP request behind it, so any credential it needs must be readable from disk.
- **Cron crosses no privilege boundary.** `crond` runs jobs as `abc`, the same
  user as the php-fpm pool and the owner of `/config`. A file readable by the
  web process is readable by cron.
- **The environment already reaches cron by inheritance**, so nothing about
  removing `PLEX_TOKEN` disturbs how the remaining variables get there.

The application also has a latent coupling: the poster wall signs its
now-playing tokens with the Plex token, which is safe only while that token is a
boot-time constant.

## Goals / Non-Goals

**Goals:**

- Exactly one way to connect Marquee to Plex
- Make connecting the first thing a new install asks for, not something to find
- Keep the scheduled auto-import working with a token obtained by signing in
- Explain themselves to users disconnected by the upgrade
- Give Plex failures a remedy that still exists

**Non-Goals:**

- Replacing Marquee's own username and password login. Signing in to Plex is an
  action an already-authenticated user takes.
- Server discovery or a picker. `PLEX_SERVER_URL` stays an environment variable.
- Backwards compatibility with `PLEX_TOKEN`. Removing it is the point.
- Reporting whether Plex is reachable right now.

## Decisions

### One credential source, and it is the store

`PLEX_TOKEN` is no longer read as a credential. The token comes from the
connection store or Marquee is not connected.

Supporting both was tried and rejected on the evidence. Precedence has to be
explained wherever the connection is described; it produces a state where a
stored token exists but is inert, which the interface must call out or mislead;
and it forces every Plex error message to branch on which source is live. The
variable's remaining argument was automated deployment — real, but not worth
that surface area for a self-hosted app in alpha whose users configure it once.

The variable is still *read*, for exactly one purpose: if it is set, the
connection screen says it is no longer used. An upgrade that silently
disconnects an install and offers no explanation is the worst version of this
change; one sentence turns it into an instruction.

### A gate, not a hint

A middleware redirects to `/connect` until a token is stored. The gallery,
import, and orphan detection are unreachable before then.

The alternative — leave the app usable and let each feature fail on its own —
is what the previous design did, and it buries the one action a new install
needs behind pages that cannot work yet. A gate states the precondition once.

It sits **after** authentication so an anonymous visitor is asked to log in
before being asked to connect Plex; the reverse would expose the connection
screen, and its sign-in action, to anyone who can reach the host.

Exempt: `/connect`, login and logout, `/health`, the manifest, `/assets/`, and
the **poster wall**. The wall is specified as publicly reachable so it can run
unattended on a display, and a gate in front of it would break that contract for
the case where it matters most — a wall left running while someone
reconfigures Plex.

`PLEX_SERVER_URL` stays in the environment, which the gate has to respect: a
missing address cannot be fixed by signing in, so the connection screen
distinguishes the two and says which one is missing. Moving the address into the
app would mean fetching candidate connections handed back by plex.tv — the one
genuinely SSRF-shaped surface in this feature — and is deliberately excluded.

### No app-wide connection status

The first cut put the connected server's name and source in the footer and the
mobile menu. It is removed.

Behind the gate it is invariant: every page a user can reach is one where Plex
is connected, so a status line saying so carries no information. It was also the
place the two-source design leaked into every screen, which is what made it read
as noise. The connected server's name still appears where it is actionable — on
the connection screen.

Nothing here contacts Plex on page render. That remains deliberate: with a
ten-second connect timeout, a probe on render would stall every page in the app
exactly when the server is down.

### Popup and polling, not a redirect back to Marquee

Plex's PIN flow can either redirect the browser back to a `forwardUrl` or let
the caller poll. Marquee polls.

The redirect variant requires Marquee to know its own externally reachable URL
and to trust `X-Forwarded-*` to build it — which is exactly what a reverse proxy
makes unguessable — and arrives as a cross-site navigation that a strict session
cookie would not survive. Polling needs neither.

Two constraints follow: the Plex window must be opened synchronously inside the
click handler, because opening it after the request resolves is what popup
blockers stop; and polling must be short request/response, never long-polling or
SSE, which proxy buffering and CDN request caps mangle.

### The token lives in a file, not in SQLite

`marquee.sqlite` is specified as a pure cache that is safe to delete. A
credential in it would make deleting it destructive.

A separate file under the data directory, written `0600` and owned by `abc`,
keeps the database a cache, gives the credential explicit permissions rather
than an inherited umask, and is readable by cron for free. It also holds the
generated client identifier, so repeated sign-ins present Marquee to Plex as one
device rather than accumulating entries.

### Server identity is the server's name

`GET /` on the Plex server returns both `friendlyName` and `myPlexUsername`, and
`myPlexUsername` is an email address.

The connection screen shows `friendlyName`. It is not personal, which matters
because this screen is what users screenshot into support threads; it says which
server is connected, which is the more useful fact for a poster manager; and
obtaining it at all proves the address and the token work together. Reading it
from the Plex server rather than plex.tv keeps the display working with no
internet access.

### Plex errors carry a reason; presentation supplies the remedy

`PlexException`'s messages embedded the fix ("Check `PLEX_TOKEN`."), which is now
advice about a variable the application no longer reads.

The exception carries a typed reason and the presentation layer renders the
remedy. With one credential source the remedy no longer branches, but the
separation still earns its place: it keeps user-facing copy out of a value
object, and it is what lets the auto-import log say the same thing the interface
does.

### The poster wall gets its own signing secret

`StreamToken` signs the now-playing proxy's tokens so it cannot be used to fetch
arbitrary paths from Plex. Its secret was the Plex token.

Once that token is mutable, signing in again rotates the secret and invalidates
tokens already on a running wall; and a secret that can be empty makes
signatures computable by anyone. A random secret generated once removes the
coupling. No wall behaviour changes, so this is an implementation concern rather
than a specified one — but it needs a regression test.

## Risks / Trade-offs

- **Every existing install is disconnected on upgrade** → Accepted; this is the
  breaking change. Mitigated by the connection screen detecting a leftover
  `PLEX_TOKEN` and saying what to do, so the install explains itself instead of
  looking broken. Called out in the release notes and the README.

- **Automated and GitOps deployments lose their non-interactive path** → A real
  loss, accepted knowingly. Such a deployment must now sign in once by hand
  after first start. If this bites, the answer is a first-run provisioning
  mechanism, not restoring a second credential source.

- **The credential lives in `/config` and so enters backups of it** → Documented.
  Previously it lived in `docker-compose.yml`, which people commit to version
  control and paste into support threads; the file is `0600` and absent from
  `docker inspect`.

- **A gate can strand a user** → The failure mode is `PLEX_SERVER_URL` unset: no
  amount of signing in helps. The screen must name that case specifically, which
  is why it is a spec scenario rather than a detail.

- **The functional test suite configures Plex through `PLEX_TOKEN`** → Every
  such test must instead write the connection store, and every authenticated
  route test must satisfy the gate. Mechanical but broad.

- **Popup blockers** → The likeliest practical failure, and not proxy-related.
  Mitigated by opening the window inside the click gesture and offering a
  visible link as a fallback.

## Upgrade Notes

There is no migration path and none is offered — the token cannot be moved from
the environment into the store on the user's behalf without persisting a
credential they did not ask to persist, and without creating a stale copy that
would silently outlive a rotation.

What an upgrading user sees: Marquee starts, login works as before, and the
first page redirects to the connection screen, which says `PLEX_TOKEN` is no
longer used and offers to sign in. After signing in they remove the variable
from their compose file at leisure; leaving it set changes nothing except that
the notice keeps appearing.

Rolling back means returning to the previous image, where `PLEX_TOKEN` still
works. The stored token is ignored by that version and left in place, so rolling
forward again needs no repeat sign-in.

## Open Questions

- Confirm the exact `plex.tv` PIN expiry so the client stops polling when the
  authorization request can no longer succeed, rather than on a guessed timeout.

Resolved during implementation: the service worker cannot serve a stale
connection screen — `public/sw.js` returns early for any path outside
`/assets/`, so pages and the connection JSON are never cached.
