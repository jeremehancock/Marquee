## Context

`PlexServerOwner::forToken()` answers one question — who owns the configured
server — and returns `null` when it cannot say. It catches `Throwable`, so every
way of failing arrives as the same `null`:

```
  wrong PLEX_SERVER_URL ─┐
  Plex not running       ├─▶ Throwable ─▶ null ─┐
  firewall / DNS         ┘                      │
                                                ├─▶ owns() === false
  server answers, names nobody ─▶ null ─────────┤
                                                │
  plex.tv will not identify the account ─▶ null ┘
                                                      │
                                                      ▼
                                            PlexSignInStatus::NotOwner
                                                      │
                                                      ▼
             "That Plex account does not own this server."
```

Three unrelated faults, one verdict, and the verdict names the only component
that is behaving correctly. The user's next action is to go and look at their
Plex account, which is fine.

The `null`-means-refuse rule itself is right and stays. Fail-closed is the whole
point of the check: an identity check that passes when it cannot run is not a
check. What is wrong is that the *refusal* carries a claim it has not earned.

## Goals / Non-Goals

**Goals:**

- Tell the user which of two things to go and fix: the Plex server, or the
  account they signed in with.
- Keep every current refusal a refusal. Nothing that is rejected today becomes
  accepted.
- Stay inside the existing sign-in flow — no new routes, no new configuration,
  no new stored state.

**Non-Goals:**

- Server discovery. `PLEX_SERVER_URL` stays an environment variable set in the
  compose file, and signing in still supplies a credential and never an address.
- Retrying, healing, or probing alternative addresses.
- Reporting reachability anywhere other than during sign-in. The connection
  screen deliberately does not claim Plex is reachable, and that stays true.

## Decisions

### The lookup returns why it failed, not just that it failed

`PlexServerOwner::forToken()` changes from `?string` to a small readonly value
object — `PlexOwnerLookup` — with three named constructors:

| Constructor | Meaning | Sign-in outcome |
| --- | --- | --- |
| `named(string $owner)` | The server answered and named an owner | compare against the account |
| `anonymous()` | The server answered and named nobody | `NotOwner` (fail closed, unchanged) |
| `unreachable()` | The server did not usefully answer at all | `Unreachable` (new) |

*Alternative considered:* throwing a typed exception for the unreachable case.
Rejected because "I could not reach it" is an ordinary answer to this question,
not an exceptional one — the caller has a branch for it either way, and an
exception would make the one class that must never propagate a failure into
`poll()` start doing exactly that.

*Alternative considered:* a second `isReachable()` method. Rejected because it
doubles the number of requests to a server that has already been established as
slow or absent, and invites the two answers to disagree.

### The boundary follows what the server did, not why we asked

| What came back | Classified as |
| --- | --- |
| No HTTP response — connection refused, DNS failure, timeout | `unreachable` |
| HTTP 401 or 403 | `anonymous` → `NotOwner` |
| Any other HTTP error status | `unreachable` |
| 200, but the body will not parse as XML | `unreachable` |
| 200, parses, no `myPlexUsername` | `anonymous` → `NotOwner` |
| 200, parses, `myPlexUsername` present | `named` |

The 401/403 row is the one that matters. A server that answers and rejects the
token has made an ownership statement — this account has no access — and
reporting that as a network problem would be the same mistake in the other
direction. Every other status means the address is not usefully a Plex server
right now, and "check the address and that Plex is running" is the right advice
whether it returned 404 or 502.

The 200-that-will-not-parse row catches `PLEX_SERVER_URL` pointing at some
*other* web application. That is an address problem, and saying so beats
refusing the user's account for it.

### plex.tv failing stops being an ownership verdict

`PlexPinClient::account()` currently swallows `PlexSignInException` and returns
`null`, which `owns()` reads as "not the owner". It stops swallowing and lets the
exception propagate. `PlexSignInService::poll()` already declares
`@throws PlexSignInException`; `PlexConnectionController::poll()` already turns
it into a 502 carrying the message; and the browser's poll loop already treats a
payload with `error` as terminal — `_stop()` clears the timer and shows the text.
So the honest outcome costs no new plumbing.

Fail-closed survives: an exception leaves `poll()` before `storeToken()` is
reached, so nothing is written, exactly as before.

### One new sign-in outcome, not two

`PlexSignInStatus` gains `Unreachable = 'unreachable'`. The plex.tv case gets no
enum case because it already has a home — the existing error path — and adding a
second "something upstream broke" status would put two spellings of the same
idea on the wire.

### Wording

The message must point at the compose file without becoming a support article.
[`PlexFailureMessage`](../../../src/Plex/PlexFailureMessage.php) already says
this in Marquee's voice for the same underlying fault, so the sign-in message
matches it rather than inventing a second phrasing:

> Marquee could not reach your Plex server. Check `PLEX_SERVER_URL` and that the
> Plex server is running.

The existing `NotOwner` text is unchanged, and still does not name the owner.

## Risks / Trade-offs

**A non-owner whose account has partial access sees `NotOwner` via the parse
path rather than via 401** → Not a problem: a server that answers reports
`myPlexUsername` regardless of who is asking, so the comparison runs and refuses
correctly. The 401 row is a shortcut for the no-access case, not the only route
to a refusal.

**A 502 from a reverse proxy in front of Plex now reads as "unreachable" rather
than as an ownership failure** → This is the intended behaviour and is correct
advice, but it is worth naming: the message will say "check that the Plex server
is running" when the truthful answer is "check your proxy". Both live in the
same place in the user's head, and the alternative is the status quo.

**Existing tests encode the old collapsing** →
`testAnUnknownAccountIsRefusedRatherThanAssumedToBeTheOwner` asserts `NotOwner`
when plex.tv returns 500; that assertion becomes a thrown
`PlexSignInException`. `testAServerThatWillNotNameItsOwnerRefuses` still asserts
`NotOwner` and must keep passing unchanged — it is the regression guard for the
`anonymous` row.

**Scope creep toward reachability reporting elsewhere** → Held off explicitly.
`PlexConnectionStatus` is specified never to claim whether Plex is reachable,
and this change does not touch it.

## Migration Plan

None required. No configuration, stored state, database, or Docker change. The
only observable difference is the message a failing sign-in produces, so an
upgrade needs no user action and there is nothing to roll back beyond the code
itself.

## Open Questions

None. The wording, the classification boundary, and the plex.tv path are all
settled above.
