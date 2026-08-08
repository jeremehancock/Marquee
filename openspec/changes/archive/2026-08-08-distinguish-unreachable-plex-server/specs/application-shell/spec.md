## MODIFIED Requirements

### Requirement: Sign in to Plex from the application
The system SHALL let an authenticated user obtain a Plex token by signing in to
Plex from within the application, using Plex's PIN authorization flow. This is
the only way to supply a Plex credential.

The flow SHALL open Plex's own sign-in page in a separate browser window and
poll for completion, rather than relying on a redirect back to Marquee. Marquee
therefore never needs to know its own externally reachable URL, and the flow
works unchanged behind a reverse proxy.

The system SHALL identify itself to Plex with a client identifier that is
generated once and persists across restarts, so that repeated sign-ins do not
accumulate duplicate device entries in the user's Plex account.

The system SHALL accept only the Plex account that owns the configured server,
and SHALL refuse any other, storing nothing. Ownership SHALL be established
using the token being offered, because the check runs at the one moment no
token is stored — deciding it from stored configuration would refuse every
first connection. Plex prevents an unprivileged
account from altering the library, but not from deleting posters here — and a
poster that never reached Plex has no upstream copy to restore. Where ownership
cannot be established the sign-in SHALL be refused, because a check that passes
when it cannot run is not a check. The refusal SHALL NOT name the owner, who is
by definition not the person reading it.

A refusal SHALL identify which of the two things went wrong: reaching the Plex
server, or the account that was used. Both refuse and store nothing, so they are
identical in effect and opposite in remedy — one is fixed in the compose file,
the other by signing in as somebody else. Reporting an unreachable server as an
ownership verdict tells the owner their own account is not theirs, and sends
them to the one place that is working.

The distinction SHALL follow what the server did, not why Marquee wanted to ask.
A server that answers and refuses the token has given an ownership answer: the
account has no access to it. A server that does not answer at all has given
none, and SHALL be reported as unreachable, naming the server address and the
server itself as what to check.

Where the ownership check fails because plex.tv cannot be reached, the system
SHALL report Plex as unavailable rather than as an ownership verdict. Nothing
about the account has been learned, so nothing about the account may be claimed.

#### Scenario: Successful sign-in
- **WHEN** an authenticated user starts sign-in and approves Marquee in Plex
- **THEN** the system stores the returned token and reports Plex as connected

#### Scenario: The first sign-in succeeds with nothing stored
- **WHEN** the owner signs in on an install that has no token stored yet
- **THEN** the system establishes ownership from the token being offered, not
  from stored configuration, and accepts the sign-in

#### Scenario: An account that does not own the server is refused
- **WHEN** the approving Plex account does not own the configured server
- **THEN** the system stores no token and reports that the account does not own
  it
- **AND** any previously stored token is left untouched

#### Scenario: Ownership that cannot be established is refused
- **WHEN** the server does not report an owner, or the account behind the token
  cannot be identified
- **THEN** the system refuses the sign-in rather than treating it as permitted

#### Scenario: An unreachable server is not reported as an ownership failure
- **WHEN** the configured server address is wrong, the Plex server is not
  running, or the network between them refuses the connection
- **THEN** the system reports that it could not reach the Plex server
- **AND** it does not report that the account does not own the server
- **AND** the message names the server address setting and the server itself as
  what to check
- **AND** no token is stored

#### Scenario: A server that refuses the token is an ownership answer
- **WHEN** the configured server answers the ownership request by rejecting the
  token as unauthorised
- **THEN** the system reports that the account does not own the server, rather
  than reporting the server as unreachable

#### Scenario: plex.tv being unavailable is not an ownership verdict
- **WHEN** the account behind the token cannot be identified because plex.tv
  cannot be reached
- **THEN** the system reports that Plex is unavailable
- **AND** it does not report that the account does not own the server
- **AND** no token is stored

#### Scenario: The refusal does not identify the owner
- **WHEN** a sign-in is refused because the account does not own the server
- **THEN** the message does not disclose the owner's account

#### Scenario: Sign-in not completed
- **WHEN** the user closes the Plex window without approving, or the
  authorization request expires
- **THEN** the system stores no token and reports that sign-in did not complete
- **AND** any previously stored token is left untouched

#### Scenario: Scheduled import uses the stored token
- **WHEN** a token was obtained by signing in
- **THEN** the scheduled auto-import authenticates to Plex with the stored token

#### Scenario: Signing out
- **WHEN** an authenticated user signs out of Plex
- **THEN** the system removes the stored token and reports Plex as not connected
