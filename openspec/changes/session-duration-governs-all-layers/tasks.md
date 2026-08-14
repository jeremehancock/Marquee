## 1. The session abstraction learns about lifetime

- [x] 1.1 Add `extendLifetime(int $seconds): void` to `App\Support\Session\SessionInterface`, documenting that it extends the *browser's* copy of the session and that it is named for the effect rather than the mechanism, because the in-memory implementation has no cookie.
- [x] 1.2 Implement `extendLifetime()` on `App\Support\Session\NativeSession`: re-issue the session cookie with `expires => time() + $seconds`, carrying the same `path`, `httponly`, and `samesite` attributes and still omitting `Secure`. Guard on `session_status() === PHP_SESSION_ACTIVE` and `!headers_sent()`, matching the guard `regenerate()` already uses.
- [x] 1.3 Implement `extendLifetime()` on `App\Support\Session\ArraySession`: record the last value and a call count, exposed by accessors, following the precedent `regenerate()`/`regenerations()` set. Comment why — a no-op double would leave the sliding cookie untestable, which is how this defect shipped.

## 2. `SESSION_DURATION` reaches the cookie and the session store

- [x] 2.1 Give `NativeSession` a constructor taking `int $lifetime` (the session duration in seconds). Keep `App\Support` free of `App\Config` — take a plain int, not `AuthConfig`.
- [x] 2.2 Add `'lifetime' => $this->lifetime` to the existing `session_set_cookie_params()` call so that every cookie PHP issues itself — including the one `session_regenerate_id(true)` emits during login — carries a real expiry instead of dying with the browser.
- [x] 2.3 Add `ini_set('session.gc_maxlifetime', (string) $this->lifetime)` next to the existing `use_strict_mode` call, before `session_start()`. Extend the class docblock: retention is Marquee's decision for the same reason the cookie's attributes are, and both `ini_set` calls are inert once a session is active.
- [x] 2.4 Update the `SessionInterface` factory in `src/bootstrap.php` to `static fn (AuthConfig $auth): SessionInterface => new NativeSession($auth->sessionDuration)`, so one bootstrap read of `SESSION_DURATION` now governs all three layers.

## 3. The cookie's expiry slides with use

- [x] 3.1 Have `App\Auth\SessionAuthenticator::renew()` call `$this->session->extendLifetime($this->config->sessionDuration)` alongside the existing `expires_at` write, so the browser's window tracks the server's.
- [x] 3.2 Extend the `renew()`/`isAuthenticated()` docblocks: the slide is reachable only from `isAuthenticated()` (after the window is confirmed live) and `establish()` (after a sign-in is verified), so "an unauthenticated request extends nothing" is a property of the call graph rather than a check that can be forgotten.
- [x] 3.3 Add a comment at the `expires_at` write warning that `renew()` must not be made conditional on the value having changed: PHP's `lazy_write` skips an unchanged write, the write is what refreshes the session file's mtime, and the mtime is what keeps garbage collection from evicting a live session.

## 4. A session is started only where one is needed

- [x] 4.1 In `App\Auth\AuthMiddleware`, add a session-less path list (`/health`, `/wall`, `/manifest.webmanifest`) and prefix list (`/assets/`, `/wall/`) with a `needsSession()` helper, plus a docblock stating the invariant: the session-less set is a subset of the public set.
- [x] 4.2 Restructure `process()` so the session-less early return is nested inside the public check — `if ($public && !$this->needsSession($path))` — and `session->start()` moves below it. Never a parallel branch: a stray entry in the session-less list must degrade to today's behaviour, not skip the auth gate.
- [x] 4.3 Comment why `/login`, `/logout`, and both `/plex/connection/*` routes still start a session (CSRF token render, session destruction, and holding/reading the sign-in pin), and why the wall does not — `wall.html.twig` is standalone and `StreamToken` exists precisely so the poster proxy needs no server-side session.

## 5. Tests

- [x] 5.1 Update the existing `new NativeSession()` call sites in `tests/Unit/Support/NativeSessionTest.php` for the new constructor argument; confirm the `HttpOnly`/`SameSite`/not-`Secure` cases still pass unchanged.
- [x] 5.2 Add to `NativeSessionTest` (in separate processes, as the file already does): the configured lifetime reaches `session_get_cookie_params()['lifetime']`, and `SESSION_DURATION` reaches `ini_get('session.gc_maxlifetime')` rather than the runtime's 1440.
- [x] 5.3 Add to `tests/Unit/SessionAuthenticatorTest.php`: a request from an authenticated session extends the browser lifetime by `sessionDuration`; `establish()` extends it; an expired session does not; an unauthenticated session does not.
- [x] 5.4 Add a unit test asserting the subset invariant directly — every path and prefix in `AuthMiddleware`'s session-less lists is also matched by `isPublic()`. This is the guardrail for the one change that could become a security regression.
- [x] 5.5 Add functional coverage in `tests/Functional/` that `/health` and the wall routes are served with **no session started**, using a session double that fails the test if any method beyond construction is called. This is what catches a future template giving the wall a session dependency.
- [x] 5.6 Add functional coverage that reachability is unchanged: every currently-public route is still served anonymously, and a protected route still redirects to `/login`. Assert against `AuthenticationTest`'s existing expectations rather than writing new ones from scratch.
- [x] 5.7 Confirm `tests/Functional/PosterWallTest.php` and `CsrfTest.php` still pass — the wall losing its session and `/login` keeping one are the two places existing behaviour could shift.

## 6. Docs and gates

- [x] 6.1 Re-read `README.md` around the `SESSION_DURATION` compose comment and the environment-variable table. The description ("idle time, not total time; renewed every time you use Marquee") becomes true for the first time — verify it reads correctly rather than assuming it does, and correct it if not.
- [x] 6.2 Check whether `docs/` or `CLAUDE.md` describe session behaviour and are made stale by this change. If nothing user-facing changed in them, say so explicitly rather than inventing edits.
- [x] 6.3 Do not document this as making sessions survive a container restart. They still live in `/tmp`; that is deliberately out of scope, and overstating it is the likeliest way this change misleads a user.
- [x] 6.4 Run `composer test`, `composer stan`, and `composer cs` — all three must pass before any commit.
- [x] 6.5 Run `openspec validate session-duration-governs-all-layers --strict`.

## 7. Live validation

- [x] 7.1 Build the image locally and smoke-test `/health`, per `docs/docker.md`. No `Dockerfile` change is involved, but the session start path now runs different code on every request.
- [ ] 7.2 On the `:dev` image, confirm by hand: sign in, close the browser completely, reopen — still signed in. Leave a tab idle well past twenty-four minutes with the Poster Wall running on another screen — still signed in. These are the two reported symptoms and neither is observable from the test suite.
- [x] 7.3 Confirm `/tmp` no longer accumulates a session file per health check inside the running container.
