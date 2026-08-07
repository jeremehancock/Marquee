## ADDED Requirements

### Requirement: Transient confirmations clear themselves
A flash message confirming something the user just did SHALL disappear on its
own after a few seconds. Messages reporting a failure or a caveat SHALL remain
until the page changes, because they carry a reason the user has to read and one
that vanishes mid-sentence is worse than none.

#### Scenario: A success message clears itself
- **WHEN** a success flash renders
- **THEN** it is removed from the page a few seconds later

#### Scenario: A failure message stays
- **WHEN** an error or warning flash renders
- **THEN** it remains until the user navigates away

### Requirement: The Plex connection and the login read as different things
The interface SHALL describe joining and leaving the Plex connection as
connecting and disconnecting, and reserve logging in and out for the
application's own authentication. Naming both "signing in" invites the reading
that they are one mechanism, which is the confusion this vocabulary exists to
prevent.

#### Scenario: Connection controls use connection words
- **WHEN** the connection screen offers to join or leave the Plex connection
- **THEN** the controls and confirmations say connect and disconnect rather than
  sign in and sign out

#### Scenario: The application's own session keeps its own words
- **WHEN** the interface offers to end the user's Marquee session
- **THEN** it says log out

## MODIFIED Requirements

### Requirement: Plex connection screen
The system SHALL provide a dedicated connection screen, reachable from the
application's navigation, that reports whether Plex is connected and offers to
connect or disconnect. It SHALL be the only place the Plex connection is managed;
no other page SHALL offer to change it.

When connected, the screen SHALL name the connected server using the friendly
name reported by the Plex server itself, and SHALL NOT display the Plex account
identifier, which is an email address. Where the name cannot be obtained the
screen SHALL still report that Plex is connected.

Because a Plex address cannot be supplied by signing in, the screen SHALL
distinguish a missing server address from a missing credential and say that the
address must be set in the environment.

When a `PLEX_TOKEN` variable is present in the environment, the screen SHALL
state that it is no longer used and that signing in replaces it, so that an
install disconnected by upgrading explains itself.

When authentication is bypassed, the screen SHALL warn that anyone who can reach
Marquee acts with the stored Plex connection — able to change and delete
posters, send artwork to the user's Plex library, and disconnect the install. It
SHALL NOT describe the risk as the ability to connect Marquee to Plex, which
only the server's owner can do. The distinction is the point: restricting who
may connect does not restrict what a visitor may do with a connection that
already exists, and the opposite reading is the one a user arrives at unaided.

#### Scenario: Connected
- **WHEN** a token is stored and the server address is set
- **THEN** the screen names the connected server and offers to sign out
- **AND** offers a way back to the gallery

#### Scenario: No way back while the gate is up
- **WHEN** the screen renders while Plex is not connected
- **THEN** it offers no link to the gallery, which the gate would refuse

#### Scenario: Not connected
- **WHEN** no token is stored
- **THEN** the screen reports that Plex is not connected and offers to sign in

#### Scenario: Server name unavailable
- **WHEN** the connected server's name cannot be read
- **THEN** the screen still reports that Plex is connected rather than failing

#### Scenario: Server address missing
- **WHEN** no Plex server address is configured
- **THEN** the screen says the address must be set in the environment
- **AND** does not present signing in as the remedy

#### Scenario: Obsolete environment token explained
- **WHEN** `PLEX_TOKEN` is set in the environment
- **THEN** the screen states that it is no longer used and that signing in
  replaces it

#### Scenario: Bypassed authentication is called out
- **WHEN** authentication is bypassed and the connection screen renders
- **THEN** the screen warns that anyone who can reach Marquee acts with the
  stored Plex connection

#### Scenario: The warning describes use, not connection
- **WHEN** the bypass warning renders
- **THEN** it names changing or deleting posters and altering the Plex library
- **AND** does not claim that a visitor could connect Marquee to Plex

#### Scenario: No warning when authentication is enforced
- **WHEN** authentication is enforced and the connection screen renders
- **THEN** no such warning appears
