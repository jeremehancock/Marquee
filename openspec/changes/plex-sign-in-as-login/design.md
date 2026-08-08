## Context

Marquee already owns every piece this change needs. The PIN authorization flow
(`PlexSignInService`, `PlexPinClient`), the fail-closed ownership check
(`PlexServerOwner`), and a server-side session with identifier regeneration on
login all exist and are tested. They are wired to a different job: producing a
stored credential for Marquee to *act with*, behind a separate login that
decides who may *use* Marquee.

That separate login is a single environment-supplied username and password
defaulting to `admin` / `changeme`, plus an `AUTH_BYPASS` switch that disables
authentication entirely. The connection screen already warns that bypass grants
a visitor the use of the stored Plex credential — changing and deleting posters,
altering the Plex library, disconnecting the install.

Plex-as-login was considered and rejected in August 2026 on four prerequisites.
Two have since shipped: session-ID regeneration on login (`harden-session-cookie`)
and fail-closed ownership verification. The remaining two — a break-glass
credential and rate limiting on anonymous sign-in starts — are settled here, the
first by deciding it is not needed and the second by building it.

Two facts about the deployment shape everything below:

- **plex.tv is not in any working path.** The server address comes from
  `PLEX_SERVER_URL`, never from plex.tv discovery. Every operation — libraries,
  import, poster writes, now-playing — goes directly to the user's own server
  with the stored token. `PlexPinClient` is the only code that contacts plex.tv,
  and only during sign-in.
- **Auto-import has no session and never did.** Container cron runs
  `bin/auto-import.php` as a separate CLI process that builds its own container
  and reads the token from `plex-connection.json` on disk.

## Goals / Non-Goals

**Goals:**

- One credential. The Plex account that owns the server is the only identity
  Marquee recognises, for connecting and for access alike.
- One door. No fallback password, no bypass, no second authentication path.
- plex.tv consulted at login and never again, per Overseerr's model.
- A wrong `PLEX_SERVER_URL` on a fresh install must be diagnosable, not a
  lockout.
- Scheduled auto-imports must survive logging out.

**Non-Goals:**

- Per-user accounts, roles, or sharing Marquee with non-owner Plex users.
  Marquee is single-user; the owner is the only identity.
- Revoking individual sessions or "sign out everywhere" (see Decision 8).
- Accurate per-client rate limiting (see Decision 7).
- Changing what Marquee does once logged in. This change is about the door.

## Decisions

### 1. The login screen and the connection screen become one screen

Today `/login` is a public credential form and `/connect` is the PIN flow behind
authentication. Once signing in to Plex *is* logging in, both render the same
single action, and keeping two screens would mean two pages with one button
each, differing only in which one the middleware happened to redirect to.

They collapse into one controller and one template. Two URLs survive over it —
`/login` and `/connect` — because the states they describe are genuinely
different and a URL that misnames what it is showing reads as a fault: nobody
managing a Plex connection should be sitting on a page called `/login`, and
nobody who needs to sign in should be sent to `/connect`. Each redirects to the
other when the visitor is in the wrong state, so neither can be reached
misnamed. The authentication gate sends you to `/login`; the connection gate,
which only ever sees a visitor who already has a session, sends you to
`/connect`.

What the screen does on a completed sign-in depends on state rather than on which
URL was requested:

| Stored token | Approved account | Result |
| --- | --- | --- |
| none | owns the server | store the token, mint a session |
| present | matches remembered owner | mint a session only |
| present | anyone else | refuse, store nothing, mint nothing |

The third row preserves existing behaviour: a refused sign-in must leave an
already-connected install exactly as it was.

**Alternative considered — one URL for both states.** Rejected: whichever name
won would misdescribe the other state. `/connect` would greet a stranger with a
word for something they cannot do yet; `/login` would sit above a signed-in
user's server name and disconnect button. Two paths over one screen costs one
redirect each way and keeps both honest.

**Alternative considered — two screens, as before.** Rejected: two templates and
two sets of tests describing one interaction, which is what made the merge worth
doing. The split here is at the route, not the screen.

### 2. The vocabulary rule narrows to the exits, and is not abandoned

`application-shell` currently requires that joining and leaving the Plex
connection be called *connect* and *disconnect*, and that *log in* and *log out*
be reserved for Marquee's own session — because naming both "signing in" invites
the reading that they are one mechanism.

They now *are* one mechanism at the entrance. The rule cannot survive unchanged,
and deleting it would be worse: the two exits remain genuinely different, and
that is where a user can do real damage by guessing.

So the rule moves. One entrance, described in Plex's words because that is what
the user is doing. Two exits, which must stay distinguishable:

```
IN    Sign in with Plex        one action, one screen

OUT   Log out       →  ends this browser session
                       Plex stays connected · auto-import keeps running
      Disconnect    →  forgets the Plex token
                       Marquee stops working until you sign in again
```

The screen must state the consequence of Disconnect, not merely name it.
"Disconnect" alone does not tell a user that scheduled imports stop.

### 3. The remembered owner

`PlexServerOwner` establishes ownership by asking the *user's Plex server* who
owns it, then comparing that to the account plex.tv reports for the token. That
is correct for a first connection, when no token is stored and the candidate is
the only one there is. As a login check it would make every login depend on the
user's own server being reachable — so a Plex server reboot would lock the owner
out of Marquee, where today it only prevents pushing posters.

The verified owner string is therefore recorded in the connection store when a
sign-in succeeds. Later logins compare plex.tv's answer against the remembered
value and never contact the user's server.

The remembered owner is an identifier, not a secret, but it lives in the
connection store rather than the database because the database is specified as a
deletable cache of Plex data and this is neither Plex data nor safe to lose
silently. Where it is absent — an install that connected before this change —
the full check runs and records it, so the first login after upgrading behaves
exactly like a first connection.

**Alternative considered — always run the full check.** Rejected for the
reboot-lockout above. **Alternative considered — cache the plex.tv account id
instead.** Rejected: the server reports `myPlexUsername`, which may be a username
or an email, and `PlexAccount::matches()` already compares against both. Storing
what the server said keeps one comparison rather than introducing a second
identity format.

### 4. No break-glass credential

The rejected option was to demote `AUTH_USERNAME` / `AUTH_PASSWORD` to a
documented fallback for when plex.tv is unreachable. It is not built, and the
reasoning is recorded here because the question will be asked again.

- **A fallback would be a second path to the same authority that proves nothing
  about ownership.** This change exists to make owner-only the access rule; a
  password beside it makes the weaker path the real one.
- **The defaults are `admin` / `changeme`.** As the front door those are at
  least confronted on day one. As a dormant fallback they become a credential
  nobody sets, rotates, or tests, that still works.
- **There is no throttling.** `attempt()` is a bare `hash_equals` with no
  counter, delay, or lockout, and nothing in the codebase rate-limits anything.
- **"Only active when plex.tv is unreachable" is attacker-triggerable.** Anyone
  able to interfere with egress or DNS could force the app into fallback mode. A
  control that activates on failure hands over the switch, so it would have to be
  always-on, and therefore always attackable.

What made it look necessary was a misreading of the outage. During a plex.tv
outage an existing session is **fully functional** — plex.tv is not in any
working path — so a fallback would not restore capability, only the ability to
start a *new* session. With a 30-day sliding session that window is narrow, and
the cost of hitting it is waiting, not losing work.

Recovery, when genuinely needed, belongs to whoever has host access. That person
can already read `plex-connection.json` and therefore holds the Plex token
outright; requiring host access for recovery grants them nothing they lack. A
network-listening password is strictly more attack surface for the same power.

**Not applicable: Overseerr's local accounts.** Overseerr keeps password logins
alongside Plex OAuth, and its issue tracker carries repeated requests to disable
Plex sign-in in favour of them (sct/overseerr #766, #3291, #4043). The stated
motivation is multi-user privilege escalation — *"someone accidentally uses
PlexOAuth one day and doesn't realise they have admin permissions."* Overseerr
serves the owner plus imported Plex friends plus local users. Marquee has exactly
one identity. That pressure does not exist here, so the precedent does not carry.

### 5. `AUTH_BYPASS` is removed rather than reinterpreted

With the password gone, bypass would be the only remaining non-Plex path — the
same second door, with no credential at all. Keeping it would mean deleting the
better door and retaining the worse one.

There is a real cost. Bypass's legitimate audience is a deployment fronted by
its own SSO (Authelia, Tailscale, Cloudflare Access), and because Marquee has no
roles, *"trust the proxy, act as the owner"* is a coherent thing to want.
Removing it means those users authenticate twice. That is accepted deliberately:
the alternative is an env var that silently makes the app's one security rule
untrue, on an install the maintainer cannot see.

### 6. Reusing the outstanding authorization request

`PlexSignInService::start()` calls `PlexPinClient::create()` unconditionally and
overwrites the session's recorded request. Once the endpoint is unauthenticated
and is the only door, that is an amplifier: each request is one plex.tv call
holding a PHP-FPM worker on an outbound HTTPS round trip for up to
`PLEX_REQUEST_TIMEOUT`.

`start()` instead returns the session's existing request while it is unexpired.
One session can hold one authorization request per request lifetime, so repeated
calls stop multiplying into plex.tv calls and parked workers.

This is keyed on the session and needs no notion of client address, which is what
lets Decision 7 skip the hard part. It also fixes a live bug: double-clicking the
sign-in button today creates two requests and orphans the first.

### 7. Coarse rate limiting in nginx, and no per-client accuracy

A `limit_req` on the sign-in start endpoint goes in the nginx site template. It
runs before PHP-FPM is reached, so it is the only layer that protects the worker
pool and session-file growth.

Accuracy is deliberately not pursued, and `TRUSTED_PROXIES` is deliberately not
introduced:

- Per-client accuracy matters only for per-client limiting, and nginx has the
  same blindness as the application behind an upstream proxy — it would need
  `set_real_ip_from` for the same reason the application would need a trusted
  proxy list. Moving the limit between layers does not avoid the problem.
- Without it the limit degrades to one shared bucket, which is acceptable
  **because** a 30-day sliding session makes the login endpoint one a legitimate
  user touches roughly never. Being refused there during an active flood costs
  almost nothing; an established session is unaffected.
- Any install exposed to the internet is generally behind Cloudflare, Traefik, or
  Nginx Proxy Manager, all of which rate-limit with real client addresses better
  than Marquee could.

If per-client precision is ever wanted, it is nginx configuration, not
application code.

**Known gap, accepted:** LinuxServer base images copy `site-confs/*.sample` into
`/config/nginx/` on first run only. Existing installs already have their copy and
will not pick up the new directive. The limit therefore protects new installs and
must be documented as a manual step for upgrades. Decision 6 is unaffected — it
is application code and reaches every install.

### 8. No session epoch / "sign out everywhere"

Deferred, not rejected.

It must not hang off Disconnect, which is the obvious place to put it: Disconnect
calls `clearToken()`, the one action that stops auto-import. Wiring a security
action to break scheduled imports is exactly backwards. It would have to be a
third exit action, and three exits for a single-user tool needs its own thought
rather than five minutes inside a change this size.

Two facts make deferring safe. Marquee's sessions live in the container's `/tmp`
and are discarded when the container is recreated, so `docker restart` is already
a crude "end every session" that leaves the token and auto-import intact.
And Overseerr — which persists sessions in its database, where a restart does
*not* clear them, and therefore needs the feature more — has exactly one logout
route, `POST /logout` calling `session.destroy()`, and no disconnect verb at all.

### 9. Sliding 30-day session

Expiry is currently absolute: `expires_at` is stamped once at login. At one hour
that is invisible. At thirty days it would eject a user exactly thirty days after
login regardless of activity, which is the opposite of trusting our own session.

The window is renewed on use. 30 days matches Overseerr's session cookie
(`maxAge: 1000 * 60 * 60 * 24 * 30`), which is the model this change follows.

`SESSION_DURATION` survives as the knob; only its default and its semantics
change. With no fallback credential, session length is now the entire tolerance
for a plex.tv outage, which is why the default has to move.

### 10. The connection is navigation *state*, not a navigation *destination*

The header listed "Plex Connection" beside Import and Orphans. That put a screen
a user visits once — you connect, and then you are done — among the actions they
use constantly, and presented it as a place to go rather than something to know.

It is replaced by a status: the connected server's name, or "Not connected", with
a lit dot for at-a-glance state. What is worth carrying on every page is whether
Marquee can still reach Plex, which the old item did not say at all.

Presentation is load-bearing here, and the first attempt got it wrong. Built from
the same icon-and-label button as the items beside it, it still read as a sixth
place to go — the shape was doing more talking than the content. It carries no
glyph and none of the button chrome: a dot, a name a size down, and a hairline
holding it apart from the actions. Every control in that bar wears an icon, so
wearing none is what marks this as a reading rather than an action. The dot is
the whole indicator, which is also what lets it stand alone in the narrow band
where labels drop out.

It stays a link. Disconnecting is offered on that screen and nowhere else, so
removing the item outright would have left the action reachable only by typing a
URL. **Alternative considered — an indicator that is not a link, or nothing at
all.** Both were rejected for exactly that: they trade a small amount of header
tidiness for an action with no route to it.

Colour does not carry the state on its own. The accessible name says "Plex
connection: connected to <server>" or "not connected", so the dot is
reinforcement rather than the signal. The status is read from cached
configuration and never contacts Plex — it renders on every page, and a probe
there would put the connect timeout in front of the whole application whenever
the server was down.

## Risks / Trade-offs

**A fresh install with a wrong `PLEX_SERVER_URL` cannot log in** → This is the
change's most serious new failure, and it is a *local* misconfiguration, not a
plex.tv one. Ownership cannot be established, so the sign-in is refused; the
screen that used to explain "set `PLEX_SERVER_URL`" now sits behind that refusal.
Port typos are common enough that `Treat an unusable Plex address as no address`
already shipped for them. Mitigation: the merged screen must distinguish
*unreachable* from *not the owner* — a distinction `PlexServerOwner` already
draws deliberately — and name `PLEX_SERVER_URL` and the server itself as what to
check. Recovery is editing the compose file and restarting, which needs host
access, consistent with Decision 4. This is the single most important UX
requirement in the change.

**Logging out will be read as revoking Plex access, and is not** → Once the
button says *Sign in with Plex*, "log out" invites the reading that it undoes
that. It does not: the token survives, deliberately, so auto-import keeps
running. Genuine revocation needs Disconnect plus removing the device in the
user's Plex account. Mitigation: Decision 2's wording requirements, and
documenting the revocation path. Note this is not a new behaviour — logout has
never touched the token — only a newly plausible misreading.

**Collapsing logout into disconnect would silently kill scheduled imports** →
The natural implementation of "Plex sign-in is the login" merges the two exits,
and `signOut()` calls `clearToken()`. The failure is delayed and silent: imports
stop at the next cron tick with nothing in the interface to say so. Mitigation:
specified as a requirement and covered by an explicit test that a scheduled
import still runs after logout.

**A deployment behind its own SSO loses `AUTH_BYPASS`** → Accepted per Decision
5; those users sign in to Plex once every 30 days.

**63 `AUTH_BYPASS` call sites plus the CI smoke test** → The bulk of the work is
mechanical test rework, not application code. Mitigation: one helper that seeds
an authenticated session, applied uniformly; the container smoke test needs only
the health endpoint, which is public.

**Rate limiting does not reach existing installs** → Accepted per Decision 7,
mitigated by Decision 6 reaching every install and by documenting the manual
nginx step.

## Migration Plan

Existing installs keep working. A stored token is untouched, the connection is
unchanged, and scheduled auto-imports continue across the upgrade without
interruption. What changes is the next login.

`AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` are read at bootstrap for one
purpose: reporting that they are obsolete. They never authenticate anything. This
copies the `PLEX_TOKEN` precedent exactly — an upgrade that silently changes how
an install is entered, offering no explanation, is the worst version of this
change, and one sentence turns it into an instruction.

The `AUTH_BYPASS` notice matters most. An install running unattended on bypass —
a kiosk, a wall display on a spare monitor — will start demanding a login, and
its operator needs to be told why rather than discovering it as a fault. The
Poster Wall itself is unaffected: it is specified to run unattended without
anyone signing in, and remains public.

Rollback is pinning the previous image tag; nothing written by this change makes
the connection store unreadable by an older version, since the remembered owner
is an added key and unknown keys are ignored.

## Open Questions

None. The decisions above were settled during exploration; the items recorded as
deferred (Decision 8) or dropped (Decisions 4, 5, 7) are closed for this change
rather than pending.
