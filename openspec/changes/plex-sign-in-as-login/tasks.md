## 1. Configuration and the connection store

- [x] 1.1 Strip `AuthConfig` to `sessionDuration` only, defaulting `SESSION_DURATION` to 2592000 (30 days); remove `username`, `password`, and `bypass`.
- [x] 1.2 Add read-only flags to `AuthConfig` recording whether `AUTH_USERNAME`, `AUTH_PASSWORD`, or `AUTH_BYPASS` is present in the environment, following the `PlexConfig::$obsoleteEnvToken` precedent. These are never used as credentials.
- [x] 1.3 Add the recorded owner to `PlexConnectionStore` (`owner()`, `storeOwner()`), alongside the existing token, client identifier, and signing secret.
- [x] 1.4 Clear the recorded owner in `clearToken()`, so disconnecting returns the install to a first-connection state and the next sign-in verifies against the server again. The client identifier and signing secret still survive, as specified.

## 2. Session and authentication

- [x] 2.1 Remove `SessionAuthenticator::attempt()` and every credential comparison; remove the `bypass` branch from `isAuthenticated()`.
- [x] 2.2 Add the method that marks a session authenticated after a verified Plex sign-in, regenerating the session identifier before setting the authenticated flag (preserving the ordering the current `attempt()` documents).
- [x] 2.3 Make expiry sliding: renew `expires_at` by `sessionDuration` on each request from an authenticated session, rather than stamping it once at login.
- [x] 2.4 Update `AuthMiddleware`'s public paths so the merged screen and the routes that start and poll a sign-in are reachable without a session; keep `/health`, the manifest, assets, and the wall as they are.
- [x] 2.5 Update `PlexConnectionMiddleware`'s open paths for the merged screen, and confirm the two gates now send an unauthenticated, unconnected visitor to the same place.

## 3. Sign-in becomes the login

- [x] 3.1 Teach `PlexSignInService::refusal()` to compare against the recorded owner when one exists, and to fall back to the full `PlexServerOwner` check when none does, recording the result on success.
- [x] 3.2 Make `PlexSignInService::start()` return the session's existing unexpired authorization request instead of creating a new one; create only when there is none or it has expired.
- [x] 3.3 Make a successful `poll()` establish an authenticated session, and store the token only when none is stored. A refusal must still store nothing, create no session, and leave an existing connection untouched.
- [x] 3.4 Ensure the refusal statuses reaching the screen still distinguish unreachable, not-owner, and plex.tv-unavailable, and that each carries the remedy the spec requires.

## 4. Routes, controllers, and screens

- [x] 4.1 Merge `/login` and `/connect` into one route and one controller action; remove `POST /login` and the credential form handling from `AuthController`.
- [x] 4.2 Rework `Routes.php` for the merged screen and for the sign-in start/poll/disconnect routes now that starting a sign-in is unauthenticated.
- [x] 4.3 Merge `login.html.twig` into the connection template: one sign-in action when signed out; server name, disconnect, and the way back when signed in.
- [x] 4.4 Remove the `AUTH_BYPASS` warning block from the connection template.
- [x] 4.5 Add the obsolete-variable notices for `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS`, calling out bypass specifically.
- [x] 4.6 State what disconnecting costs on the disconnect control — Marquee stops working until someone signs in again, and scheduled imports stop with it.
- [x] 4.7 Check the logout control's wording against the spec: it must not read as revoking Plex access, and must say the connection and scheduled imports survive it.
- [x] 4.8 Update `CsrfMiddleware`: rewrite the docblock reasoning that references `AUTH_BYPASS`, and point its explained-refusal special case at the merged screen's path so a stale token there still re-renders the screen rather than an error page.

## 5. Container and web server

- [x] 5.1 Add a coarse `limit_req` for the sign-in start route to `docker/root/defaults/nginx/site-confs/default.conf.sample`, generous enough that a user signing in, failing, and retrying is never refused.
- [x] 5.2 Build the image locally and smoke-test `/health` plus a full sign-in against a real Plex server, per `docs/docker.md`. CI only exercises the image after a push.

## 6. Tests

- [x] 6.1 Add a helper to `AppTestCase` that seeds an authenticated session, and replace all 63 `AUTH_BYPASS` usages across the 11 functional test files with it.
- [x] 6.2 Update `.github/workflows/ci.yml` so the container smoke test no longer sets `AUTH_BYPASS`; the health endpoint is public and needs no session.
- [x] 6.3 Cover the authentication deltas: owner signs in and gets a session, non-owner is refused, no credential authenticates, a session works while plex.tv is unreachable.
- [x] 6.4 Cover sliding expiry: an idle session expires, use renews the window, a regularly used session outlives `SESSION_DURATION` from its creation.
- [x] 6.5 Cover logout: the stored token is unchanged, the install still reports Plex as connected, and **a scheduled auto-import still runs after logout**.
- [x] 6.6 Cover the recorded owner: a later login does not contact the Plex server, the owner can log in while the server is down, a non-owner is still refused, and an install with no recorded owner performs the full check and records it.
- [x] 6.7 Cover request reuse: repeated starts return the same authorization request, an expired one is replaced, separate sessions get separate requests.
- [x] 6.8 Cover the merged screen: one action when signed out, both gates land on it, one sign-in clears both, a mistyped `PLEX_SERVER_URL` names the address setting, and the obsolete-variable notices render.
- [x] 6.9 Remove tests that only asserted removed behaviour (credential login, bypass grants access, bypass hides the logout link).

## 7. Documentation

- [x] 7.1 Update `README.md`: remove `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` from the compose sample and the variable table; correct the `SESSION_DURATION` default and describe it as sliding.
- [x] 7.2 Rewrite the README section describing Marquee login versus Plex connection as two things, and the trusted-network guidance that referenced bypass.
- [x] 7.3 Document the upgrade path: existing installs keep their connection and their scheduled imports; the next login is a Plex sign-in; a bypassed install will start asking for one.
- [x] 7.4 Document the nginx rate-limit gap — existing installs already have their own copy of the site config and must add the directive by hand to pick it up.
- [x] 7.5 Check `docs/` and `CLAUDE.md` for staleness against this change and fix it in the same commit; if nothing else is stale, say so explicitly.

## 9. Sign-in at `/login`, and the connection as a status

- [x] 9.1 Split the merged screen across two paths: `/login` (public, redirects a signed-in visitor to `/connect`) and `/connect` (requires a session, reached via the authentication gate otherwise). One controller, one template.
- [x] 9.2 Point `AuthMiddleware` at `/login` and `PlexConnectionMiddleware` at `/connect`, and open both paths on the connection gate.
- [x] 9.3 Replace the "Plex Connection" nav item with a connection status in the header and tray: server name or "Not connected", a state dot, linking to `/connect`.
- [x] 9.4 Add the `plex_connection()` Twig function, reading cached configuration only so no page render can contact Plex.
- [x] 9.5 Style the status as a reading rather than an action: no glyph, no button chrome, a size down, hairline separator, green/amber dot with a halo.
- [x] 9.6 Update tests for the two paths, and cover the status indicator, its link, and its text description of the state.
- [x] 9.7 Update `README.md` for `/login`, the status indicator, and the two exits.

## 10. Reported defects

- [x] 10.1 Cache the server's friendly name at sign-in, taken from the response the ownership check already reads, so the status names the connection without the connection screen having been opened.
- [x] 10.2 Move the connection status after Log out, so it stops splitting the action group.
- [x] 10.3 Cut the signed-out screen's copy to one sentence.
- [x] 10.4 Stop the sign-in control reporting "Waiting for Plex…" after the user closes the Plex window; read the window's state before each poll and act on it only once the poll has answered, so an approval confirmed after the close still completes.

- [x] 10.5 Sweep the remaining connect-era vocabulary: the signed-in-but-disconnected copy, the `PLEX_TOKEN` notice, and the `NotConfigured` Plex failure remedy.
- [x] 10.6 Name both exits on the connection screen instead of describing one and referring to the other, and say that disconnecting also stops the Poster Wall reporting what is playing.

- [x] 10.7 Serve the wall's posters from a public, wall-scoped route so an unattended display can load its rotation; keep the gallery's poster route behind the login and refuse categories the wall does not draw.

## 8. Gates

- [x] 8.1 `composer test` — PHPUnit passes.
- [x] 8.2 `composer stan` — PHPStan level 10 clean over `src/` and `tests/`.
- [x] 8.3 `composer cs` — PHP-CS-Fixer clean (`composer cs:fix` to apply).
- [x] 8.4 `openspec validate plex-sign-in-as-login --strict` passes.
