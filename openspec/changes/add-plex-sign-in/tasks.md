## 1. Connection store

- [x] 1.1 Add a `PlexConnectionStore` that reads and writes a JSON file under
      `AppConfig->dataDir` (outside `marquee.sqlite`), holding the Plex token,
      the generated client identifier, and the wall signing secret
- [x] 1.2 Create the file with mode `0600` and never widen it on rewrite;
      create the data directory if absent, matching existing behaviour
- [x] 1.3 Generate and persist the client identifier on first use, returning the
      same value on every later call
- [x] 1.4 Generate and persist the wall signing secret on first use, independent
      of whether a Plex token is present
- [x] 1.5 Treat a missing, unreadable, or malformed file as "nothing stored"
      rather than an error, so a deleted `/config` returns to first-run state
- [x] 1.6 Unit-test the store: first-use generation, round-trip, `0600` mode,
      malformed-file tolerance, and that clearing the token leaves the client
      identifier and signing secret intact

## 2. Configuration resolution

- [x] 2.1 Replace `PlexConfig::fromEnv()` with a resolver that takes the
      environment and the store, with `PLEX_TOKEN` winning when both supply one
- [x] 2.2 Expose the connection source on `PlexConfig` (environment, stored, or
      none) so presentation can distinguish them without re-reading the store
- [x] 2.3 Wire the resolver into `buildContainer()` in `src/bootstrap.php`,
      keeping resolution to exactly once at bootstrap
- [x] 2.4 Confirm `bin/auto-import.php` resolves a stored token through the same
      container definition with no session present
- [x] 2.5 Unit-test resolution: environment wins over stored, stored used when
      the variable is unset or empty, neither means not configured, and the
      reported source matches in each case

## 3. Poster wall signing secret

- [x] 3.1 Build `StreamToken` from the store's signing secret instead of
      `$plex->token` in `src/bootstrap.php`
- [x] 3.2 Unit-test that tokens minted before a Plex token changes still verify
      afterwards, and that the secret is never empty

## 4. Plex sign-in flow

- [x] 4.1 Add a `plex.tv` PIN client using the injected Guzzle client: create an
      authorization request, and read one back by id
- [x] 4.2 Send `X-Plex-Client-Identifier` from the store plus product, version,
      and device headers so the entry in the user's Plex account is named
- [x] 4.3 Build the `app.plex.tv` authorization URL for the browser to open;
      do not use a forward URL
- [x] 4.4 Add a sign-in service that starts a request, records its id against
      the current session, completes it by storing the returned token, and
      signs out by clearing the stored token only
- [x] 4.5 Reject a completion attempt whose recorded session does not match the
      requesting session, storing nothing
- [x] 4.6 Stop polling once the authorization request can no longer succeed, and
      report that sign-in did not complete without disturbing a stored token
- [x] 4.7 Ensure no code path logs the authorization response body or the token
- [x] 4.8 Unit-test the flow against a mocked Guzzle handler: successful
      completion, not-yet-authorized, expiry, session mismatch, and that a
      failed attempt leaves an existing stored token untouched

## 5. Server identity

- [x] 5.1 Add a `PlexClient` method reading the server's `friendlyName` from
      `GET /`, returning null when it cannot be obtained
- [x] 5.2 Cache the friendly name in SQLite via a migration in
      `src/Database/Database.php`, refreshed when the connection panel renders
- [x] 5.3 Never read or surface `myPlexUsername`
- [x] 5.4 Unit-test parsing a real-shaped `MediaContainer` response, a response
      without the attribute, and a failed request

## 6. Routes and controller

- [x] 6.1 Add a connection controller with start, poll, and sign-out actions
- [x] 6.2 Register the routes in `src/Routes.php` behind the existing
      `AuthMiddleware`, adding no public routes
- [x] 6.3 Return the connection state — source, server name, sign-in progress —
      as JSON containing no token
- [x] 6.4 Functional-test that the routes require authentication, that the
      responses never contain a token, and that sign-out clears only the token

## 7. Connection panel

- [x] 7.1 Replace the "Plex is not configured yet" panel in
      `templates/plex.html.twig` with the connection panel
- [x] 7.2 Render all four states: stored token in use; `PLEX_TOKEN` in use;
      stored but overridden by `PLEX_TOKEN`; not connected
- [x] 7.3 State plainly in the overridden case that the sign-in is not in use and
      that removing the variable and restarting activates it
- [x] 7.4 Fall back to reporting the source alone when the server name is absent
- [x] 7.5 Link to `docs/plex-connection.md` as "What's the difference?"
- [x] 7.6 Turn the orphans page's "Plex must be configured" notice into a link to
      the connection panel
- [x] 7.7 Functional-test each of the four states end to end

## 8. Browser flow

- [x] 8.1 Open the Plex window synchronously inside the click handler and set its
      location once the authorization request returns, so popup blockers do not
      fire
- [x] 8.2 Offer a visible link as a fallback when the window cannot be opened
- [x] 8.3 Short-poll the status route on a fixed interval, following the pattern
      in `public/assets/wall.js`; no long-polling and no SSE
- [x] 8.4 Stop polling on success, on expiry, and on a bounded overall timeout,
      reporting the outcome in the panel

## 9. App-wide connection status

- [x] 9.1 Render the connected server name and connection source with the
      application's other status information in the shared layout, covering both
      the desktop footer and the mobile actions tray
- [x] 9.2 Source it from cached data only, contacting Plex on no page render
- [x] 9.3 Keep it text — no coloured indicator and no claim of reachability
- [x] 9.4 Exclude it from the poster wall template
- [x] 9.5 Functional-test that the status appears on an authenticated page, is
      absent from the wall, and that an unreachable Plex does not delay a render

## 10. Plex failure messages

- [x] 10.1 Give `PlexException` a typed reason and stop embedding remedies in its
      messages
- [x] 10.2 Render the remedy in the presentation layer from the reason and the
      active connection source
- [x] 10.3 Advise signing in again on a rejected credential from a stored token,
      and checking `PLEX_TOKEN` on a rejected environment token
- [x] 10.4 Update every call site that surfaces a `PlexException` — import,
      export, poster editing, orphan detection
- [x] 10.5 Unit-test that each reason and source pair produces the matching
      remedy, and that no message names `PLEX_TOKEN` while a stored token is in
      use

## 11. Documentation

- [x] 11.1 Write `docs/plex-connection.md` comparing the two connection sources:
      where the token is stored, who manages it, how to change it, whether it
      survives losing `/config`, whether it appears in `docker inspect`, which
      suits automated deployment, and that `PLEX_TOKEN` always wins
- [x] 11.2 Document the zero-downtime opt-in order: sign in, remove the variable,
      restart
- [x] 11.3 Note that the token now lives in `/config` and so enters backups of it
- [x] 11.4 Note that `AUTH_BYPASS=true` lets anyone reaching Marquee sign in or
      out, consistent with its trusted-network contract
- [x] 11.5 Remove `PLEX_TOKEN` from the `docker-compose.yml` example in
      `README.md`, keep `PLEX_SERVER_URL`, and point new users at signing in
- [x] 11.6 Reword `PLEX_TOKEN` in the README environment table as optional,
      overriding in-app sign-in, and suited to automated deployment — not
      deprecated
- [x] 11.7 Check whether `CLAUDE.md` or other `docs/` pages describe Plex
      configuration as environment-only and correct them, or record explicitly
      that none needed changing

## 12. Verification

- [x] 12.1 Check whether `public/sw.js` would serve a stale connection panel and
      exclude it from caching if so — verified it cannot: the fetch handler
      returns early for anything outside `/assets/`, so pages and the connection
      JSON are never cached. No change needed.
- [x] 12.2 Confirm an existing deployment with `PLEX_TOKEN` set behaves exactly
      as before, including a scheduled auto-import run
- [x] 12.3 Build the image locally and smoke-test `/health` per `docs/docker.md`
- [x] 12.4 Verify the stored token file is `0600` and owned by `abc` in the
      running container, and that cron reads it
- [x] 12.5 Run `composer test`, `composer stan`, and `composer cs` and fix every
      failure
- [x] 12.6 Run `openspec validate add-plex-sign-in --strict`
