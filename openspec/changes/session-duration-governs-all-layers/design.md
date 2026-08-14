## Context

A session in Marquee is three things at once, and only one of them knows about
`SESSION_DURATION`:

```
                       SESSION_DURATION=2592000
                                 │
                                 ▼
                        AuthConfig::fromEnv()
                                 │
                                 ▼
                       $_SESSION['expires_at']        ← the only consumer

     session.cookie_lifetime = 0        ← runtime default, unaware
     session.gc_maxlifetime  = 1440     ← runtime default, unaware
```

The runtime defaults were confirmed against the actual base image
(`alpine:3.21` + `php84`, the same packages the `Dockerfile` installs):
`cookie_lifetime=0`, `gc_maxlifetime=1440`, `gc_probability=1`,
`gc_divisor=1000`, `save_path` unset. Nothing in the `Dockerfile`'s
`zz-marquee.ini` or under `docker/root/` overrides any of them.

Two failures follow, and they are the two the user reported.

**Closing the browser.** `cookie_lifetime=0` makes the session cookie a
browser-session cookie. The server-side session is untouched and valid for
thirty more days; the browser has simply discarded the only reference to it.

**Walking away from an open browser.** PHP's file session handler collects
sessions whose file mtime is older than `gc_maxlifetime`, and it does so
probabilistically *inside `session_start()`* — `gc_divisor=1000` means one
startup in a thousand sweeps. `SessionAuthenticator::renew()` rewrites
`expires_at` on every authenticated request, which changes the session data,
which refreshes the file's mtime. So an actively-used session is immune. Idle
past twenty-four minutes and it becomes eligible, and then it is a lottery:

```
   you click          you walk away           GC fires
 ─────────────►   ──────────────────────►  ──────────────►
  every request       no requests             sweeps any
  rewrites            mtime goes              file idle
  expires_at          stale after             > 24 min
  → mtime fresh       24 min                  → evicted
  → GC-immune         → GC-eligible

 ├─── SAFE ──────┤├── 24 min grace ──┤├──── ROULETTE ────┤
```

How often the lottery is played is decided by something unrelated to
authentication. `AuthMiddleware::process()` calls `session->start()` *before*
testing whether the path is public, so the two highest-traffic routes in the
product both roll the dice:

| Traffic source | Interval | Requests/day | Expected sweeps/day |
| --- | --- | --- | --- |
| Docker `HEALTHCHECK` (`--interval=30s`) | 30s | 2,880 | ~2.9 |
| Wall stream poll (`STREAM_POLL_MS`) | 10s | 8,640 | ~8.6 |
| Wall poster rotation (`ROTATE_MS`) | 8s | ~10,800 | ~10.8 |
| **With a wall running** | | **~22,000** | **~22/day, one per ~65 min** |
| **Without a wall** | | **~2,900** | **~2.9/day, one per ~8 hrs** |

The poster-rotation row is an order-of-magnitude estimate; browser caching may
reduce it. The health-check row is exact. Either way the shape holds: a route
that reads no session state is the dominant cause of real sessions being
evicted.

Neither failure is visible to the test suite. Every session test drives
`ArraySession`, which has no cookie and no garbage collector, so the sliding
window's *arithmetic* is thoroughly covered while the two layers that actually
end the session are unobservable. That is why this shipped.

## Goals / Non-Goals

**Goals:**

- `SESSION_DURATION` governs all three layers — server-side expiry, cookie
  lifetime, and server-side retention — from a single read at bootstrap.
- The cookie's expiry slides with use, matching the server-side window rather
  than being stamped once at sign-in.
- Stop creating session state for requests that read none, so that serving the
  health endpoint or the wall cannot evict a signed-in user.
- Make all of the above assertable in unit tests, so the next regression fails
  CI instead of reaching a user.

**Non-Goals:**

- **Moving sessions off `/tmp`.** Sessions still live in the container's `/tmp`,
  so recreating the container still signs everyone out. Fixing that means
  `/config`, which on a self-hosted install is routinely a network share, and
  PHP's file session handler takes a `flock()` on every request — trading a
  login annoyance for a class of hang on a path every request travels. It needs
  its own decision. Nothing here should be documented as fixing it.
- **Changing which routes are reachable anonymously.** The public-route set is
  unchanged. Only whether a session is started changes.
- **Adding configuration.** No new environment variable, no changed default.
- **Making `Secure` conditional.** Still deliberately absent, for the reasons
  the existing spec gives.

## Decisions

### 1. Skipping the session is a refinement *inside* the public branch, never a parallel one

This is the only part of the change that could plausibly become a security
regression: a route that skips `session->start()` must never thereby skip the
authentication gate. The structure makes that impossible rather than relying on
a correctly-maintained list.

```php
$path = $request->getUri()->getPath();
$public = $this->isPublic($path);

// Only a route that is ALREADY public may skip the session.
if ($public && !$this->needsSession($path)) {
    return $handler->handle($request);
}

$this->session->start();

if ($public || $this->authenticator->isAuthenticated()) {
    return $handler->handle($request);
}

return $this->redirectToSignIn();
```

The early return is guarded by `$public`, so the session-less set is
structurally a *subset* of the public set. If someone later adds a protected
path to the session-less list, the first branch is not taken, the session
starts, and the auth check runs exactly as it does today — the mistake degrades
to current behaviour instead of granting access.

**Alternative considered:** a standalone `if (!$this->needsSession($path))`
check before everything else. Rejected — it reads more simply and it is exactly
the shape where one list edit silently opens a protected route. The invariant
must not depend on two lists agreeing.

A test asserts the subset relation directly, so the invariant is stated twice:
once in the control flow, once in CI.

### 2. Which routes need a session

| Route | Session | Why |
| --- | --- | --- |
| `/health` | no | `HealthController` reads nothing. |
| `/wall`, `/wall/*` | no | `wall.html.twig` is standalone — it does not extend `layout.html.twig`, so it never calls `csrf_token()`. Poster tokens are HMAC-signed via `StreamToken`, deliberately so the wall needs no server-side session. |
| `/assets/*`, `/manifest.webmanifest` | no | Static. |
| `/login` | **yes** | Renders `layout.html.twig`, which calls `csrf_token()` — `CsrfGuard::token()` writes to the session. |
| `/plex/connection/sign-in` | **yes** | Stores the pin in the session. |
| `/plex/connection/status` | **yes** | Reads the pin back. "Another session has no request recorded" is the security model. |
| `/logout` | **yes** | Must have one to destroy. |
| everything else | **yes** | Protected. |

The wall's independence from the session is not incidental — `StreamToken`
exists precisely so the poster proxy can recover an image path "without a
server-side session". This change makes the middleware agree with a decision the
wall already made.

### 3. Cookie lifetime is set in the session params *and* re-issued per request

Both, not either.

| Approach | Result |
| --- | --- |
| `lifetime` in `session_set_cookie_params()` alone | **Absolute.** PHP emits `Set-Cookie` only when creating a session, so the expiry is stamped at sign-in and never moves — reintroducing, one layer down, the absolute deadline the spec rejects. |
| Re-issue on each authenticated request alone | Slides correctly, but `session_regenerate_id(true)` inside `establish()` emits its own `Set-Cookie` from the *ini* params — i.e. lifetime 0 — and correctness then depends on the later `Set-Cookie` winning by header order. |
| **Both** | Params make every PHP-issued cookie correct, including the one `regenerate()` emits at login. The per-request re-issue supplies the slide. |

Header ordering is well defined and would in fact work; the objection is that it
would make the durability of the login depend on it silently. Setting the params
costs one array key and removes the dependency.

### 4. The re-issue lives in `SessionAuthenticator::renew()`

`renew()` is reached from exactly two places, and both are correct:
`isAuthenticated()` after the window is confirmed live, and `establish()` after
a sign-in is verified. It is unreachable for an unauthenticated caller, so
"an unauthenticated request extends nothing" is a property of the call graph
rather than of a check that could be forgotten.

**Consequence — anonymous sessions get a full-duration cookie anyway**, because
the params apply whenever PHP creates a session. Accepted:

- The anonymous session carries no authority. "Authenticated only by a verified
  Plex sign-in" is a separate requirement, and the `authenticated` flag is what
  gates everything.
- Session fixation is already closed independently, by regeneration on login
  plus `use_strict_mode`.
- The sign-in pin carries its own expiry, so a stale one is not usable.
- It is a small improvement: a longer-lived CSRF token makes the "This page
  expired before you signed in" refusal in `CsrfMiddleware` rarer.

**Alternative considered:** `lifetime => 0` in the params and a long cookie only
on authentication. Rejected — that is the header-ordering dependency from
decision 3, taken on to avoid a consequence that is benign.

### 5. `SessionInterface` gains `extendLifetime(int $seconds)`

The interface has no notion of a cookie today, which is exactly why leak 1 was
unassertable: every test drives `ArraySession`, and `ArraySession` has no
browser.

```
SessionInterface
  ├── start()  get()  set()  has()  regenerate()  clear()
  └── extendLifetime(int $seconds)          ← new

     NativeSession  → re-issues the real cookie
     ArraySession   → records the value + a call count
```

`ArraySession` follows the precedent `regenerate()` already set: it counts
calls and exposes the last value, with a comment saying why, so the requirement
is testable at the unit level. Named for its effect rather than its mechanism —
`extendLifetime`, not `renewCookie` — because the in-memory implementation has
no cookie and should not be made to pretend it does.

### 6. Retention is applied by the application, not by an ini file

`ini_set('session.gc_maxlifetime', …)` goes in `NativeSession::start()`,
alongside the `use_strict_mode` call that is already there for the same stated
reason: a setting the application depends on but does not set can be removed by
a base-image change with nothing failing. It also keeps the fix travelling with
the code rather than with the image, so `Dockerfile` needs no edit and no
rebuild-and-smoke-test cycle.

Both `ini_set` calls must precede `session_start()`; they have no effect once a
session is active. That constraint already shapes the existing block.

### 7. Decisions 1 and 6 must land together

Raising `gc_maxlifetime` to thirty days while public routes still manufacture
sessions would replace a login bug with a slow disk leak:

```
gc_maxlifetime = 1440  (today)        gc_maxlifetime = 2592000  (alone)
──────────────────────────────        ─────────────────────────────────
orphans live 24 min                   orphans live 30 DAYS
→ /tmp self-limits (~48 files)        → 2,880/day × 30 = ~86,400 files
✗ but it evicts YOUR session          ✓ your session survives
                                      ✗ 86k files in one flat directory
                                      ✗ GC then stats all of them,
                                        inside a user's request
```

With decision 1 in place there are no orphans to retain, and thirty-day
retention keeps only sessions that are real — a handful, for a single-user app.
Neither half should be merged without the other.

### 8. Wiring: a plain `int`, not `AuthConfig`

```php
SessionInterface::class => static fn (AuthConfig $auth): SessionInterface
    => new NativeSession($auth->sessionDuration),
```

`App\Support` is generic infrastructure; having it depend on `App\Config`
inverts the layering for no gain, and makes `NativeSession` awkward to
construct in a test. `AuthConfig::fromEnv()` still performs the single
bootstrap read, so the project's "read once into typed config objects" rule is
satisfied either way.

## Risks / Trade-offs

- **A session-less route becomes a way past the auth gate** → The early return
  is nested inside `$public`, so the session-less set cannot escape the public
  set (decision 1). A test asserts every session-less path is also public.
  This is the risk that matters most; it is closed structurally, not by review.

- **A future template change gives the wall a session dependency** → If
  `wall.html.twig` ever extends `layout.html.twig`, `csrf_token()` would write
  to a session that was never started. Mitigated by a test that exercises the
  wall routes against a session double that fails if used, so the coupling
  breaks CI rather than production.

- **`renew()` made conditional as an "optimisation"** → Retention works because
  rewriting `expires_at` refreshes the session file's mtime; PHP's
  `lazy_write` skips the write when data is unchanged. Anything that skips
  `renew()` when the value has not moved would silently restore the eviction
  bug. Recorded in a comment at the call site.

- **`Set-Cookie` on every authenticated response** → Makes those responses
  uncacheable by intermediaries. Already true of any response that sets a
  cookie, and irrelevant for a single-user self-hosted app behind its own nginx.

- **Anonymous sessions persist up to `SESSION_DURATION`** → Accepted with
  reasons in decision 4. They carry no authority and fixation is closed
  independently.

- **Container recreate still signs everyone out** → Unchanged and out of scope.
  The temptation is to describe this change as "sessions now last thirty days"
  full stop; the honest claim is "thirty days, until the container is
  recreated". Documentation must not overstate it.

- **Existing users see one more sign-in** → Not caused by this change. Deploying
  a new image recreates the container and wipes `/tmp`, which already ends every
  session. After that sign-in, the behaviour is the fixed one.

## Migration Plan

No configuration change and no data migration. `SESSION_DURATION` keeps its
name, units, default, and documented meaning.

Live sessions upgrade in place where they survive: the first authenticated
request after deployment calls `renew()`, which re-issues the cookie with a real
expiry. In practice a deployment recreates the container, so `/tmp` is cleared
and users sign in once — as they already do on every update today.

Rollback is a plain revert. Nothing persisted changes shape, so an older image
reads everything a newer one wrote.

## Open Questions

- **Should `/config`-backed session storage follow as a separate change?** It is
  the last reason a user signs in more often than `SESSION_DURATION` implies.
  The blocker is whether PHP's `flock()` on a network-mounted `/config` is
  acceptable on every request, or whether it needs an opt-in env var with `/tmp`
  as the default that cannot hang. Worth deciding after this lands, with the
  noise from decision 1 already removed.
- **Is a SQLite-backed session handler the better answer to that question?**
  `/config/data/marquee.sqlite` already exists, GC becomes a single `DELETE`
  rather than a directory scan, and there is no file sprawl. It costs a custom
  handler and puts a write on every session-bearing request — which is only
  tolerable *because* decision 1 removes the high-frequency ones. Noted, not
  proposed.
