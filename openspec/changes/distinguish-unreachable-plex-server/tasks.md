## 1. Report why the owner lookup failed

- [x] 1.1 Add a `PlexOwnerLookup` readonly value object in
      `src/Plex/Connection/` with the named constructors `named(string $owner)`,
      `anonymous()`, and `unreachable()`, exposing the owner and which case it
      is
- [x] 1.2 Change `PlexServerOwner::forToken()` to return `PlexOwnerLookup`
      instead of `?string`, classifying per the design table: no HTTP response
      and any non-401/403 error status and an unparseable body are
      `unreachable`; 401 and 403 and a parsed body with no `myPlexUsername` are
      `anonymous`; a parsed `myPlexUsername` is `named`
- [x] 1.3 Keep the empty-`serverUrl` and empty-token guard returning
      `unreachable` — an install with no address configured cannot reach a
      server, and that is the advice it needs

## 2. Carry the reason through to the user

- [x] 2.1 Add `Unreachable = 'unreachable'` to `PlexSignInStatus`, documenting
      that nothing is stored, as with every other refusal
- [x] 2.2 Rework `PlexSignInService::owns()` and its call site in `poll()` so an
      `unreachable` lookup returns `PlexSignInStatus::Unreachable` and every
      other failing lookup still returns `NotOwner`, with the store untouched in
      both cases
- [x] 2.3 Stop `PlexPinClient::account()` swallowing `PlexSignInException`, so a
      plex.tv failure propagates out of `poll()` to the controller's existing
      502 path rather than being reported as an ownership verdict
- [x] 2.4 Add an `unreachable` branch to the sign-in poll loop in
      `public/assets/gallery.js`, naming `PLEX_SERVER_URL` and the Plex server
      as what to check, matching the wording `PlexFailureMessage` already uses

## 3. Tests

- [x] 3.1 Unit-test the classification in `PlexServerOwner`: a connection
      failure, a 404, a 502, and an unparseable 200 are all `unreachable`; a
      401 and a parsed body with no owner are both `anonymous`; a parsed
      `myPlexUsername` is `named`
- [x] 3.2 Unit-test in `PlexSignInServiceTest` that an unreachable server yields
      `Unreachable`, stores nothing, and leaves an already-stored token intact
- [x] 3.3 Update `testAnUnknownAccountIsRefusedRatherThanAssumedToBeTheOwner` —
      a 500 from plex.tv now throws `PlexSignInException` rather than returning
      `NotOwner`; assert nothing is stored
- [x] 3.4 Confirm `testAServerThatWillNotNameItsOwnerRefuses` and
      `testAnAccountThatDoesNotOwnTheServerIsRefused` still pass unchanged, and
      add one asserting a 401 from the server yields `NotOwner` rather than
      `Unreachable`
- [x] 3.5 Extend the functional coverage in `tests/Functional/PlexConnectionTest.php`
      so the poll route reports `unreachable` for an unreachable server

## 4. Documentation

- [x] 4.1 Check whether `README.md` and `docs/` describe the sign-in refusals,
      and correct them or record explicitly that they do not need changing

## 5. Verification

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs`
- [x] 5.2 Run `openspec validate distinguish-unreachable-plex-server --strict`
- [ ] 5.3 In a built image, point `PLEX_SERVER_URL` at an address with nothing
      listening and confirm signing in reports the server as unreachable rather
      than as an ownership failure
