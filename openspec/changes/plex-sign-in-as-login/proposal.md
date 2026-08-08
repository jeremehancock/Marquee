## Why

Marquee asks for two unrelated credentials to do one job. A user signs in to
Plex to connect their server, then signs in again — with a different, invented
username and password — to open the app that manages it. The second credential
protects a tool whose entire authority comes from the first, and it ships with a
default of `admin` / `changeme` that a self-hosted install can run on for years.

The Plex account that owns the server is already the only identity Marquee
recognises: signing in verifies it, and the ownership check refuses everyone
else. Making that sign-in the login removes a credential rather than adding one,
and makes the app's access rule the same as its authority — the owner of the
Plex server, and nobody else.

## What Changes

- **BREAKING** `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS` are removed.
  There is no password login, no fallback, and no way to disable the login. The
  variables are read only to tell an upgrading user they are obsolete.
- Signing in to Plex becomes the way to log in to Marquee. The login screen and
  the connection screen collapse into one screen offering one action.
- Only the Plex account that owns the configured server may log in. This rule
  already governs connecting; it now governs access.
- Sessions last 30 days and slide, renewed by use rather than expiring a fixed
  interval after login. plex.tv is consulted at login and never again.
- The verified owner is remembered when the connection is first made, so later
  logins no longer depend on the user's own Plex server being reachable.
- The login screen distinguishes an unreachable Plex server from an account that
  does not own it, and names `PLEX_SERVER_URL` as what to fix. Without this a
  mistyped address locks a new install out of the screen that would explain it.
- Logging out ends the browser session and nothing else. The stored Plex token
  survives, so scheduled auto-imports keep running. Disconnecting remains the
  only action that forgets the token.
- An outstanding sign-in request is reused rather than replaced, so repeated
  attempts do not each mint a new authorization request at plex.tv. A coarse
  request limit is added in front of the endpoint that starts one.

## Capabilities

### New Capabilities

None. Both affected capabilities already exist.

### Modified Capabilities

- `authentication`: the credential is replaced by a Plex sign-in restricted to
  the server's owner; the bypass is removed; session expiry becomes a sliding
  30-day window; logout is specified not to disturb the Plex connection.
- `application-shell`: the connection screen and the login screen become one
  screen; the ownership check consults a remembered owner; the connection gate's
  ordering relative to authentication collapses; the vocabulary rule separating
  connection from login is narrowed to the two exit actions; the obsolete
  environment notice covers the removed authentication variables.

## Impact

**Removed**: `SessionAuthenticator::attempt()` and all credential comparison;
the `AUTH_BYPASS` branch in `SessionAuthenticator::isAuthenticated()`; the
bypass warning block in `connect.html.twig`; the login form.

**Changed**: `AuthConfig` keeps only the session duration; `PlexSignInService`
mints a session on success and reuses an outstanding request; `PlexConnectionStore`
gains the remembered owner; `AuthMiddleware` and `PlexConnectionMiddleware`
public-path lists; `Routes.php`; `CsrfMiddleware` documentation; the nginx site
template.

**Tests**: 63 `AUTH_BYPASS` usages across 11 test files, plus the container smoke
test in CI, all need a helper that seeds an authenticated session instead.

**Docs**: `README.md` documents the removed variables and the two-credential
model in four places; `docs/` and `CLAUDE.md` need checking in the same commit.

**Upgrade**: existing installs keep working — a stored token is untouched — but
the next login is a Plex sign-in, and an install running on `AUTH_BYPASS` starts
demanding one.
