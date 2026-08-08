## Why

Getting Marquee talking to Plex means hunting down an `X-Plex-Token` through a
browser dev-tools walkthrough and pasting it into a compose file — the first
thing a new user has to do, and the thing most likely to stop them. That token
then lives in `docker-compose.yml`, a file people commit to git and paste into
support threads.

Signing in to Plex from inside the app removes the walkthrough and removes the
credential from the compose file. Making it the *only* way in also removes a
question a new user should never have to answer: which of two ways to connect
they are supposed to use.

## What Changes

- **Sign in to Plex from the app.** A connection screen at `/connect` runs
  Plex's PIN flow: the user opens Plex's own sign-in page in a popup, approves,
  and Marquee stores the resulting token under `/config`.
- **BREAKING: `PLEX_TOKEN` is no longer read as a credential.** Signing in is
  the only way to connect. An existing install that sets `PLEX_TOKEN` will be
  disconnected on upgrade and must sign in once. Marquee is in alpha, and one
  way to connect is worth the one-time interruption.
- **BREAKING: Marquee is unusable until Plex is connected.** A gate stands in
  front of the gallery, import, and orphan detection, redirecting to `/connect`
  until a token is stored. Connecting is the first thing a new install asks for
  rather than something to discover.
- **Upgrading users are told what happened.** When `PLEX_TOKEN` is still set in
  the environment, the connection screen says it is no longer used and to sign
  in instead — so a disconnected install explains itself rather than looking
  broken.
- **Plex error messages stop naming a variable that no longer exists.** Every
  Plex failure currently advises checking `PLEX_TOKEN`. They now point at
  signing in again.
- **The poster wall stops borrowing the Plex token as a signing key.** It gets
  its own generated secret, so signing in again no longer rotates it.

Deliberately excluded: no server discovery or server picker — `PLEX_SERVER_URL`
is still set through the environment, and the connection screen says so when it
is missing, because signing in cannot supply an address. Marquee's own username
and password login is untouched; signing in to Plex is something an
already-authenticated user does.

## Capabilities

### New Capabilities

None. This changes where the Plex credential comes from and what the
application requires before it will run; it does not introduce a new area of
behavior.

### Modified Capabilities

- `application-shell`: The Plex token comes from a persisted store rather than
  the environment. The recreatable-state invariant gains a carve-out for
  connection credentials. Adds the sign-in flow, the connection screen, and a
  gate that makes a Plex connection a precondition for using the application.

## Impact

**Code**

- `src/Config/PlexConfig.php` — token resolved from the store, not the environment
- `src/Plex/Connection/` — sign-in service, PIN client, token store, connection state
- `src/Plex/PlexException.php` — typed reasons; remedy copy moves to presentation
- `src/Auth/` — new middleware gating on the Plex connection
- `src/Controller/PlexConnectionController.php` — the `/connect` screen and its actions
- `src/Routes.php`, `src/bootstrap.php` — new routes, middleware, `StreamToken` secret
- `templates/connect.html.twig`, navigation — the connection screen and its entry

**Persistence**

- New token file under `/config`, mode `0600`. Kept out of `marquee.sqlite` so
  the database stays a pure, deletable cache.
- Connected server name cached in SQLite (recreatable from Plex).

**Docs**

- `README.md` — compose example, environment table, and a connection section
  covering signing in and the upgrade note

**Tests**

- The functional suite configures Plex through `PLEX_TOKEN` today. That default
  goes away; tests that need a connected Plex write the connection store, and
  every authenticated-route test must now satisfy the gate.

**External**

- New outbound dependency on `plex.tv`, used only while signing in.

**Not affected**

- Marquee's own authentication, importing, exporting, poster editing, orphan
  detection, and the scheduled auto-import all keep their current behavior. The
  poster wall stays publicly reachable and ungated.
