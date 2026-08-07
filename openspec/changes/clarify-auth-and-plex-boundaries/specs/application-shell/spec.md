## MODIFIED Requirements

### Requirement: Plex connection screen
The system SHALL provide a dedicated connection screen, reachable from the
application's navigation, that reports whether Plex is connected and offers to
sign in or sign out. It SHALL be the only place the Plex connection is managed;
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
