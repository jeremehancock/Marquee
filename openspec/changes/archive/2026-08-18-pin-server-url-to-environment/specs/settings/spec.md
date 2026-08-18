## ADDED Requirements

### Requirement: The Plex server address stays in the environment
The system SHALL read the Plex server address from `PLEX_SERVER_URL` in the
environment on every bootstrap. It SHALL NOT be a setting the store owns: it is
neither seeded into the store nor resolved from it.

This is a security control, not a convenience. Marquee admits the Plex account
that owns the configured server, so the address decides who is let in. While it
comes from the environment, choosing it requires access to the host — which is
the assertion that stops the first stranger who reaches a publicly reachable
install from becoming its owner. Move the address anywhere a browser can set it
and it proves nothing, because whoever typed it chose the server it names.

Being environment-only is therefore load-bearing and SHALL NOT be treated as an
unfinished migration. Nothing SHALL later relocate this variable into the store
"for consistency" with the settings around it.

Because the variable is live rather than superseded, the system SHALL NOT report
`PLEX_SERVER_URL` as relocated, and SHALL NOT show it beside a settings field as
a value now managed in the application. Both would be false.

An install with no address configured SHALL be inert rather than claimable: the
settings screen sits behind a session, a session requires a Plex sign-in, and a
sign-in requires an address to verify ownership against.

#### Scenario: The address takes effect from the environment
- **WHEN** `PLEX_SERVER_URL` is set and the container starts
- **THEN** the configured server address is the environment value
- **AND** the settings store holds no Plex server address

#### Scenario: Changing the variable changes the address
- **WHEN** `PLEX_SERVER_URL` is changed and the container is recreated, and the
  settings store has already been seeded
- **THEN** the new environment value is in effect
- **AND** the previous value is not retained by the store

#### Scenario: The address is not reported as superseded
- **WHEN** `PLEX_SERVER_URL` is set
- **THEN** it is not reported as a relocated variable
- **AND** it is not reported as a retired variable

#### Scenario: An install without an address is inert
- **WHEN** no `PLEX_SERVER_URL` is set and no session exists
- **THEN** no screen offers to configure the address
- **AND** no visitor can take ownership of the install

## MODIFIED Requirements

### Requirement: The Plex server address is withheld from the screen
The system SHALL NOT offer the Plex server address on the settings screen.

The address is not merely a setting: it is an assertion only someone with host
access can make, and it is what stops the first stranger who reaches an
unconfigured install from claiming it as their own. Ownership is verified against
*that* address, so an address chosen in the browser verifies nothing.

It SHALL remain outside the screen permanently. This is not a gap awaiting a
replacement property — the environment variable *is* the property, so there is
nothing to replace and no condition under which the field should appear.

#### Scenario: The server address is not editable on the settings screen
- **WHEN** a user opens the settings screen
- **THEN** no field offers the Plex server address

#### Scenario: A submitted server address is not stored
- **WHEN** a settings submission includes a Plex server address field
- **THEN** no server address is written to the store
- **AND** the configured address remains the environment value
