## Why

Someone who signs in with the correct Plex account, on an install whose Plex
server simply cannot be reached, is told that their account does not own the
server. It does. They are sent to audit their Plex account while the real
problem — a typo in `PLEX_SERVER_URL`, a stopped Plex, a firewall between the
two containers — sits somewhere they have been given no reason to look.

This is the most misleading message in the application. The one moment a user is
most likely to have their server address wrong is the first connection, which is
exactly when this fires.

## What Changes

- Signing in tells the user **where the problem is**: with the Plex server, or
  with the account they used. Today both arrive as the second.
- A server that cannot be reached during sign-in produces its own outcome and
  its own message, naming `PLEX_SERVER_URL` and the server being down as the
  things to check.
- A server that answers and refuses the token keeps reporting that the account
  does not own it — a rejected token means the account has no access, which is
  an ownership answer, not a connectivity failure.
- plex.tv failing to identify the account behind a token stops being reported as
  an ownership verdict, and is reported as Plex being unavailable, which is what
  it is.
- **No change to what is permitted.** Every one of these outcomes still refuses
  the sign-in and stores nothing. Ownership stays fail-closed; only the
  explanation changes.

Explicitly out of scope: server discovery. `PLEX_SERVER_URL` remains an
environment variable set in the compose file. This change makes the existing
arrangement honest rather than replacing it.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the Plex sign-in requirement currently specifies a single
  refusal for "ownership cannot be established", which collapses an unreachable
  server into an ownership verdict. It gains a requirement that the refusal
  distinguish an unreachable server from an account that does not own a server
  that answered.

## Impact

- `src/Plex/Connection/PlexServerOwner.php` — must report *why* it could not
  name an owner, rather than returning `null` for every failure.
- `src/Plex/Connection/PlexSignInStatus.php` — a new outcome.
- `src/Plex/Connection/PlexSignInService.php` — maps the reason onto the outcome.
- `src/Plex/Connection/PlexPinClient.php` — stops swallowing a plex.tv failure
  into a null account.
- `public/assets/gallery.js` — a branch for the new outcome.
- Tests: `tests/Unit/Plex/PlexSignInServiceTest.php` and
  `tests/Functional/PlexConnectionTest.php`.
- No configuration, database, or Docker change. No user action required on
  upgrade.
