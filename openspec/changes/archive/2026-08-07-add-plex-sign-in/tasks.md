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

## 2. One credential source

- [x] 2.1 Resolve the Plex token from the store in `PlexConfig`
- [x] 2.2 Wire the resolver into `buildContainer()`, keeping resolution to
      exactly once at bootstrap
- [x] 2.3 Confirm `bin/auto-import.php` resolves a stored token through the same
      container definition with no session present
- [x] 2.4 Stop reading `PLEX_TOKEN` as a credential — remove the precedence
      branch so the store is the only source
- [x] 2.5 Collapse `PlexTokenSource` now that there is one source: keep only
      what distinguishes connected from not connected, and delete the rest
- [x] 2.6 Expose whether a `PLEX_TOKEN` is present in the environment, used
      solely to drive the obsolete-variable notice — never as a credential
- [x] 2.7 Update the resolution unit tests: the stored token is used, an
      environment token is ignored, neither means not connected

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
      `src/Database/Database.php`, refreshed when the connection screen renders
- [x] 5.3 Never read or surface `myPlexUsername`
- [x] 5.4 Unit-test parsing a real-shaped `MediaContainer` response, a response
      without the attribute, and a failed request

## 6. The connection screen

- [x] 6.1 Add a connection controller with start, poll, and sign-out actions
- [x] 6.2 Register the routes behind the existing `AuthMiddleware`, adding no
      public routes
- [x] 6.3 Return the connection state as JSON containing no token
- [x] 6.4 Move the screen to its own `GET /connect` route with its own template,
      and give it an entry in the desktop navigation and the mobile actions menu
- [x] 6.5 Remove the connection panel from `templates/plex.html.twig` so the
      import page is only about importing
- [x] 6.6 Collapse the four panel states to two — connected, and not connected
- [x] 6.7 Show the connected server's name, falling back to reporting the
      connection when the name cannot be read
- [x] 6.8 When no `PLEX_SERVER_URL` is set, say the address must be set in the
      environment and do not present signing in as the remedy
- [x] 6.9 When a `PLEX_TOKEN` is present in the environment, state that it is no
      longer used and that signing in replaces it
- [x] 6.10 Drop the "What's the difference?" link — there is one way to connect
- [x] 6.11 Point the orphans page's not-connected notice at `/connect`
- [x] 6.12 Functional-test the screen: connected, not connected, missing address,
      obsolete variable present, and that no response carries a token
- [x] 6.13 Warn on the connection screen when `AUTH_BYPASS` is enabled: bypass
      now exposes a credential that can write to the user's Plex library

## 7. The connection gate

- [x] 7.1 Add middleware that redirects to `/connect` when no Plex token is
      stored
- [x] 7.2 Run it after authentication, so an unauthenticated visitor is sent to
      login rather than to the connection screen
- [x] 7.3 Exempt `/connect` and its actions, login, logout, `/health`, the
      manifest, `/assets/`, and the Poster Wall and its endpoints
- [x] 7.4 Register it in `createApp()` in the correct order relative to the
      existing middleware stack
- [x] 7.5 Functional-test the gate: the gallery redirects while disconnected,
      connecting releases it, the wall and health stay reachable, and an
      unauthenticated request goes to login first

## 8. Browser flow

- [x] 8.1 Open the Plex window synchronously inside the click handler and set its
      location once the authorization request returns, so popup blockers do not
      fire
- [x] 8.5 Open it as a sized, centred popup rather than a full tab, reusing one
      named window, and close it when sign-in completes
- [x] 8.2 Offer a visible link as a fallback when the window cannot be opened
- [x] 8.3 Short-poll the status route on a fixed interval; no long-polling, no SSE
- [x] 8.4 Stop polling on success, on expiry, and on a bounded overall timeout,
      reporting the outcome in the screen

## 9. Remove the app-wide status

- [x] 9.1 Remove the `plex_connection()` Twig function and the
      `PlexConnectionStatus` dependency from the Twig factory in `bootstrap.php`
- [x] 9.2 Remove the status markup from `templates/layout.html.twig` and
      `templates/partials/_menu.html.twig`
- [x] 9.3 Remove the now-unused summary helper from the connection state
- [x] 9.4 Delete the app-wide status functional tests

## 10. Plex failure messages

- [x] 10.1 Give `PlexException` a typed reason and stop embedding remedies in its
      messages
- [x] 10.2 Render the remedy in the presentation layer from the reason
- [x] 10.3 Update every call site that surfaces a `PlexException` — import,
      export, poster editing, orphan detection, and the auto-import CLI
- [x] 10.4 Simplify the remedies now there is one source: a rejected credential
      always advises signing in to Plex again
- [x] 10.5 Update the unit tests so no message names `PLEX_TOKEN`

## 11. Test suite

- [x] 11.1 Remove `PLEX_TOKEN` from the `AppTestCase` environment defaults
- [x] 11.2 Give `AppTestCase` a way to start an app with Plex connected, writing
      the connection store the way `PlexConnectionTest` already does
- [x] 11.3 Update every functional test that relied on `PLEX_TOKEN` to configure
      Plex, and every authenticated-route test that must now pass the gate
- [x] 11.4 Confirm the whole suite passes with no test depending on an
      environment token

## 12. Documentation

- [x] 12.1 Delete `docs/plex-connection.md` — it compares two options and there
      is one
- [x] 12.2 Rewrite the README's Plex connection section: sign in from the app,
      what the gate means for a new install, and that the token lives in
      `/config` at `0600` and so enters backups of it
- [x] 12.3 Add an upgrade note to the README: `PLEX_TOKEN` is no longer read,
      existing installs are disconnected once and must sign in, and the variable
      can then be deleted from the compose file
- [x] 12.4 Remove `PLEX_TOKEN` from the README environment table and the compose
      example, and drop the "Finding your Plex token" walkthrough
- [x] 12.5 Update the Features list and Security considerations for one
      connection method
- [x] 12.9 Harden the `AUTH_BYPASS` wording in the compose example, the
      environment table, and Security considerations — it now hands over a
      stored Plex credential, not just a poster gallery
- [x] 12.6 Remove the `docs/plex-connection.md` reference from the repo layout in
      `docs/development-workflow.md`, and its `PLEX_TOKEN` from the dev compose
      sample
- [x] 12.7 Reconcile `CLAUDE.md` and `openspec/config.yaml`: the Plex token now
      comes only from the store, not from the environment
- [x] 12.8 Update `docs/testing.md` where it tells the reader to set `PLEX_TOKEN`
      on the container

## 14. Owner-only sign-in

- [x] 14.1 Read the account behind a token from `plex.tv/api/v2/user`
- [x] 14.2 Read the server's owner from its root response, alongside the name
- [x] 14.3 Refuse a sign-in whose account does not own the configured server,
      storing nothing and leaving any existing connection untouched
- [x] 14.4 Fail closed when ownership cannot be established — an unidentifiable
      account or a server that names no owner is a refusal, not a pass
- [x] 14.5 Match the owner on username as well as email; the server reports one
      field and does not say which kind it holds
- [x] 14.6 Say the account does not own the server without naming the owner
- [x] 14.7 Unit- and functional-test the refusal, both failure-to-verify paths,
      and that no refusal discloses the owner
- [x] 14.8 Establish ownership from the token being offered rather than from
      stored configuration, which is empty on a first connection and would
      refuse the owner along with everyone else

## 15. Container environment

- [x] 15.1 Delete the `docker-env.sh` mechanism: its grep silently dropped every
      `PLEX_*` and `AUTO_IMPORT_*` variable, and the file it wrote was never
      needed because crond inherits the container environment
- [x] 15.2 Correct the comments that claimed cron runs without the container
      environment
- [x] 15.3 Verify in a built image that a scheduled import still reads its
      settings and the stored token

## 13. Verification

- [x] 13.1 Confirm the upgrade path by hand: start with `PLEX_TOKEN` set and no
      stored token, and check the gate redirects and the screen explains itself
- [x] 13.2 Build the image locally and smoke-test `/health` per `docs/docker.md`
- [x] 13.3 Verify the stored token file is `0600` and owned by `abc` in the
      running container, and that cron reads it
- [x] 13.4 Run `composer test`, `composer stan`, and `composer cs` and fix every
      failure
- [x] 13.5 Run `openspec validate add-plex-sign-in --strict`
