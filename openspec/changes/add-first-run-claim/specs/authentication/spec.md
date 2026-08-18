## ADDED Requirements

### Requirement: An install is claimed before it can be signed in to
The system SHALL require an install to be claimed before any Plex sign-in is
accepted, and SHALL make claiming require access to the host filesystem or the
container's logs.

This replaces a property that was previously provided by configuration. While the
Plex server address came from the environment, it was an assertion only someone
with host access could make, and verifying ownership against *that* server is
what stopped the first stranger who reached an unconfigured install from becoming
its owner. Once the address is entered in a browser it verifies nothing, because
the party entering it chose the server it names. The claim restores the property
in a form a browser cannot reach.

On first start with nothing claimed, the system SHALL generate a claim code, hold
it in a file readable only by the owning user, and record it in the application
log. Claiming SHALL require presenting that code.

The code SHALL carry enough entropy that guessing it is not a practical attack,
independently of any rate limit. A rate limit SHALL also be applied, but SHALL
NOT be the control the install depends on.

On a successful claim the system SHALL delete the code file, and SHALL NOT issue
or accept a further code. Reclaiming SHALL require removing the stored claim from
the filesystem.

#### Scenario: An unclaimed install refuses to sign anyone in
- **WHEN** a visitor reaches an unclaimed install and attempts to sign in to Plex
- **THEN** no session is created
- **AND** the visitor is directed to claim the install first

#### Scenario: The code admits the person who has it
- **WHEN** a visitor presents the correct claim code
- **THEN** the install is claimed and the visitor may continue to sign in

#### Scenario: A wrong code claims nothing
- **WHEN** a visitor presents an incorrect claim code
- **THEN** the install remains unclaimed
- **AND** the attempt is recorded in the log

#### Scenario: The code is retrievable only with host access
- **WHEN** an install has been started and not yet claimed
- **THEN** the code is present in a file readable only by the owning user, and in
  the application log
- **AND** no response from the application discloses it

#### Scenario: The gate does not reopen
- **WHEN** an install has been claimed and the code file has been deleted
- **THEN** no further claim is accepted
- **AND** no new code is generated

#### Scenario: Guessing is bounded
- **WHEN** claim attempts are made repeatedly with incorrect codes
- **THEN** the endpoint stops accepting attempts for a cooling-off period
- **AND** the attempts are logged

### Requirement: The claim survives disconnecting from Plex
The system SHALL preserve the record that an install is claimed when the Plex
connection is discarded.

Disconnecting deliberately forgets the stored token and the owner, so that
ownership is proven against the server again on the next sign-in. It SHALL NOT
forget the claim. If it did, disconnecting a publicly reachable install would
reopen it to the first stranger to load it, and the claim would protect nothing
beyond the first few minutes of an install's life.

The claim SHALL be kept alongside the values already preserved across
disconnecting for the same class of reason — the client identifier and the
signing secret — and SHALL NOT be stored anywhere a user can edit or a reset can
clear as a side effect. In particular it SHALL NOT live in the settings store,
which the settings screen writes, nor in the database, which is specified as a
deletable cache.

#### Scenario: Disconnecting leaves the install claimed
- **WHEN** a signed-in owner disconnects Marquee from Plex
- **THEN** the install is still claimed
- **AND** the next sign-in requires no claim code

#### Scenario: Deleting the database leaves the install claimed
- **WHEN** the SQLite database is deleted and the container restarted
- **THEN** the install is still claimed

#### Scenario: The claim is not a setting
- **WHEN** the settings screen is submitted with any combination of values
- **THEN** the claim is unaffected

### Requirement: A claim is recorded so that an unexpected one is visible
The system SHALL log the owner and the server address when an install is first
claimed.

An install claimed by someone the operator did not expect is the failure this
whole mechanism exists to prevent. If it happens anyway, it SHALL be discoverable
rather than mysterious.

The log entry SHALL NOT contain the claim code.

#### Scenario: A claim is logged
- **WHEN** an install is claimed and its first sign-in completes
- **THEN** the log records the owning account and the server address

#### Scenario: The code is not written to the log twice
- **WHEN** an install has been claimed
- **THEN** the claim code does not appear in the log again

## MODIFIED Requirements

### Requirement: Signing in to Plex is the login
The system SHALL establish an authenticated server-side session only by a
successful Plex sign-in, and SHALL accept no other credential. There is no
username, no password, and no way to authenticate without Plex.

The system SHALL grant a session only to the Plex account that owns the
configured server, and SHALL refuse every other account. This is the same rule
that governs connecting Marquee to Plex; it now governs access as well, so that
the app's access rule and its authority are the same thing.

Ownership is verified against the configured server, so that rule is only as
strong as the answer to "who chose the address". While the address came from the
environment, choosing it required host access. Now that it is entered in the
browser, the claim above is what carries that requirement, and a sign-in SHALL
therefore be accepted only on a claimed install.

plex.tv SHALL be consulted only while logging in. Once a session exists, no
request SHALL depend on plex.tv being reachable, because nothing Marquee does
after login involves it — the Plex server address is configured, and every
operation goes to that server directly.

#### Scenario: The owner signs in
- **WHEN** a visitor completes a Plex sign-in and the approving account owns the
  configured server
- **THEN** the system creates an authenticated session and takes the visitor
  into the application

#### Scenario: An account that does not own the server is refused
- **WHEN** a visitor completes a Plex sign-in and the approving account does not
  own the configured server
- **THEN** the system creates no session
- **AND** any previously stored Plex token is left untouched

#### Scenario: No credential authenticates
- **WHEN** a request supplies a username and password by any means
- **THEN** the system creates no session

#### Scenario: A session does not depend on plex.tv
- **WHEN** an authenticated session makes a request while plex.tv cannot be
  reached
- **THEN** the request is served normally

#### Scenario: Signing in requires a claimed install
- **WHEN** a sign-in is attempted on an install that has not been claimed
- **THEN** no session is created, whatever the approving account owns
