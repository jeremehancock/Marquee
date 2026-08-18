## Context

What exists and shapes this:

- `PlexConnectionMiddleware` redirects to `/connect` whenever `PlexConfig` is not
  configured. `/connect` and `/plex/connection/*` are exempt from that gate;
  `/login` is exempt from the auth gate. So the first-run destination already
  exists and is already reachable in the states this needs.
- `PlexConnectionController::screen()` already renders one template in two
  states, signed in and not.
- `PlexConnectionStore::clearToken()` unsets exactly `token` and `owner` and
  writes back the rest. A claim marker stored there therefore survives
  disconnecting **by construction** rather than by remembering to preserve it —
  but nothing asserts that today, and a later edit could change the method to
  rewrite the file wholesale.
- `PlexServerOwner` verifies the signing-in account against the configured
  server's root response.
- Rate limiting lives in nginx: `limit_req_zone ... rate=30r/m` with `burst=10`
  on `/plex/connection/sign-in`. Its own comment records why it is there rather
  than in PHP, and admits it degrades to a single allowance behind a reverse
  proxy because trusting a forwarded-address header would be worse.
- `/wall` and `/wall/*` are exempt from both gates, deliberately, so an
  unattended display keeps working while Plex is reconfigured.

## Goals / Non-Goals

**Goals:**

- An unconfigured install reachable from the internet cannot be claimed by
  someone without host access.
- The property `PLEX_SERVER_URL` provided is replaced before it is removed.
- `clearToken()` cannot reopen a claimed install.
- The compose file ends up carrying no application configuration.

**Non-Goals:**

- Multi-user access. Marquee has one owner; a claim is a one-time global event.
- Re-claiming from the browser. Resetting requires filesystem access — that is
  the property, not a gap.
- Restricting the server address to private ranges. It would break legitimate
  remote installs and stop no attacker, who can point at anything reachable.
- Fixing `ChangePosterService::fetchUrl()`'s missing private-range check. It is
  pre-existing, authenticated, and out of scope by the plan's own note — worth
  proposing separately against `poster-editing`.

## Decisions

### Entropy first, throttling second

The plan specifies at least 20 bits with per-IP throttling. 20 bits is 1,048,576
codes; the existing nginx allowance of 30r/m with burst would exhaust that in
roughly a fortnight of steady guessing, which is a real attack against a
long-lived install.

So the code is **128 bits**, rendered as 26 Crockford base32 characters in
hyphenated groups. There is no usability cost — nobody memorises it, they paste
it from a file or a log line — and it moves brute force from "slow" to "not a
thing". The throttle then stops being what protects the install, which is where a
throttle should sit.

*Both controls are still implemented*, because defence in depth is the point:

- **nginx**, reusing the existing zone pattern on the claim route, for the same
  reason the sign-in route has it — it protects the worker pool from the request
  arriving at all.
- **A global attempt counter in the application**, not per-IP. This is the more
  useful of the two here and deserves its own justification: a claim is one
  global event, not a per-user action, so counting attempts globally is both
  simpler and strictly stronger than counting per address. Per-IP limits are
  defeated by the same proxy situation the nginx comment already describes, and
  by any attacker with more than one address. After a threshold of failures the
  claim endpoint refuses for a cooling-off period, and every failure is logged.

The one thing a global counter can do that per-IP cannot is let an attacker lock
out the legitimate owner. That is acceptable here and nowhere near symmetric: the
owner has host access, so a lockout costs them a wait or a file deletion, while
the alternative risks the install itself. The cooling-off period is therefore
minutes, not hours.

### The marker lives in the connection store, and a test pins it there

`claimed_at` goes beside `client_identifier` and `signing_secret` — values
already preserved across `clearToken()` for the same class of reason. It is not a
`SettingKey`: settings are things a user changes, and this is a fact about the
install that must never be writable from the settings screen.

`clearToken()`'s current shape preserves it for free. A test asserts it anyway,
because "for free" here means "as long as nobody rewrites this method", and the
consequence of that rewrite is a public install reopening silently.

### Not the settings store, and not the database

The settings store is writable from the settings screen by design. The database
is a deletable cache — a claim that a `rm` clears is not a claim.

### The probe is usability and says so

Step 1 probes the entered address unauthenticated and echoes back the server's
name, so a typo or a wrong port is caught before the user commits. The spec
states in as many words that this is **not** a security control: the attacker
chose the server, so a stub satisfies it. Writing that down is the point — the
next person to read the code should not mistake the probe for the gate.

### Wizard shape follows from the architecture

Step ordering is forced, not chosen:

| Step | Needs | Reachable without a session |
| --- | --- | --- |
| 1 — claim code + server URL | nothing | yes |
| 2 — Plex sign-in | a server URL to verify ownership against | yes (it is how a session is obtained) |
| 3 — settings | a live connection to list libraries | no |

Step 1 is the only new surface. Step 2 is the existing sign-in untouched. Step 3
is the settings screen from phases 2 and 3, reached once rather than navigated
to.

### An unclaimed install answers nothing else

While unclaimed, every gated path redirects to step 1 — including `/login`, which
is otherwise exempt from the connection gate. Signing in before the install is
claimed would let a stranger establish a session against a server they named.

`/health` and the Poster Wall stay exempt, as they are today. The wall on an
unclaimed install has no posters to show, because posters only arrive through an
import that requires a connection.

### Claiming is one transaction

Verify the code, store the address, write `claimed_at`, delete the code file. If
the address cannot be stored the claim is not recorded, so a half-claimed install
cannot exist. The code file is deleted only after the marker is written — losing
the file with no marker would lock the install out permanently, which is worse
than a code that lingers a moment longer.

### Logging

The code is logged on generation, because that is one of the two ways an operator
retrieves it. The claim itself is logged with the owner and the server URL, so an
unexpected claim is visible rather than mysterious. Failed attempts are logged.
The code is **not** logged again after a successful claim, and the claim log line
does not contain it.

## Risks / Trade-offs

- **A claim code in `marquee.log` is a credential in a log file** → It is
  deliberate: the log is the reliable way to retrieve it, and reaching either the
  log or the file requires host access. It is written once, before any claim
  exists, and never again.
- **An attacker can lock out the owner via the global attempt counter** → Bounded
  by a short cooling-off, and the owner has filesystem access. See above.
- **Someone loses the code before claiming** → Deleting
  `/config/data/claim-code.txt` and restarting regenerates it. Documented.
- **A publicly reachable install whose connection state is deleted reopens** →
  This is why the reset documentation must say to take it off the network first.
  `/config/posters` is independent of claim state, and the wall is exempt from
  both gates, so the next claimant sees the library.
- **The wizard is the first-run path for every new install, so a bug in it blocks
  everyone** → It is the phase validated most carefully on `:dev`, from a genuinely
  empty volume rather than an upgraded one.

## Migration Plan

**An install that already has a Plex connection is already claimed.** On first
boot after upgrading, an install with a stored token — or with a `PLEX_SERVER_URL`
already seeded into the settings store — records `claimed_at` without ever showing
step 1. Anything else would present the wizard to an existing user and ask them
for a code they have never seen.

A fresh install generates a code and shows step 1.

Rollback is reverting the change. The stored `claimed_at` becomes an unread key
and the address is read from the store exactly as phase 1 left it.

## Open Questions

None blocking. One to confirm during validation: whether the claim code should
also appear on the container's stdout — visible in `docker logs` without exec —
in addition to `marquee.log`. It is more convenient and marginally more exposed.
