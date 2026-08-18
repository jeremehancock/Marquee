## MODIFIED Requirements

### Requirement: A Plex connection is required to use the application
The system SHALL require a connected Plex server before any route that depends
on one may be used, redirecting to the connection screen until Plex is
connected.

Signing in is what satisfies both this gate and authentication, so a new
installation asks for one thing rather than two in sequence. Where a visitor has
neither a session nor a connection, both gates send them to the same screen, and
one sign-in clears both.

Both paths to the connection screen, the routes that start and poll a sign-in,
the logout route, the health endpoint, the web app manifest, static assets, and
the Poster Wall SHALL remain reachable while Plex is not connected. The wall is
exempt because it is specified to run unattended without anyone signing in; a
gate in front of it would break that.

An **unclaimed** install is a stricter case than an unconnected one. Until it is
claimed, every route above except the health endpoint, the manifest, static
assets, and the Poster Wall SHALL lead to the claim step — including the sign-in
path, which is otherwise exempt from this gate. Signing in before an install is
claimed would let a stranger establish a session against a server they named
themselves, which is the whole thing the claim prevents.

The wall stays exempt on an unclaimed install without weakening it: posters
arrive only through an import, which requires a connection, so an unclaimed
install has none to show.

#### Scenario: Gallery is unreachable until Plex is connected
- **WHEN** an authenticated user requests the gallery while Plex is not
  connected
- **THEN** the system redirects to the connection screen

#### Scenario: Connecting releases the gate
- **WHEN** a visitor with no session signs in to Plex on an install with no
  stored token
- **THEN** the previously gated routes are served normally
- **AND** the user is taken to the gallery with a confirmation, rather than left
  on the connection screen

#### Scenario: Authentication comes first
- **WHEN** an unauthenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system sends them to the sign-in path rather than the connection
  path

#### Scenario: Each gate uses the path that names what is missing
- **WHEN** an unauthenticated visitor requests a gated route, connected or not
- **THEN** the system sends them to the sign-in path

#### Scenario: A signed-in visitor with no connection is sent to the connection path
- **WHEN** an authenticated visitor requests a gated route while Plex is not
  connected
- **THEN** the system sends them to the connection path

#### Scenario: The wall runs without a Plex connection
- **WHEN** the poster wall is requested while Plex is not connected
- **THEN** the system serves it rather than redirecting

#### Scenario: Health stays reachable
- **WHEN** the health endpoint is requested while Plex is not connected
- **THEN** the system serves it

#### Scenario: An unclaimed install sends every visitor to the claim step
- **WHEN** any gated route, or the sign-in path, is requested on an unclaimed
  install
- **THEN** the system leads the visitor to the claim step

#### Scenario: Health and the wall stay reachable on an unclaimed install
- **WHEN** the health endpoint or the poster wall is requested on an unclaimed
  install
- **THEN** the system serves it rather than redirecting

### Requirement: Starting a sign-in is rate limited by the web server
The image SHALL ship a web server configuration that limits how often the routes
that start a sign-in and that claim the install may be requested. The limit SHALL
be enforced before the request reaches the application, because what it protects
— the worker pool and the store of sessions — is consumed by the request arriving
at all.

The limit SHALL be coarse. It SHALL NOT attempt to identify individual clients,
and no configuration for trusting forwarded client addresses SHALL be introduced.
Behind a reverse proxy the limit degrades to one shared allowance, which is
acceptable because a 30-day sliding session makes the login route one a
legitimate user reaches roughly never; being refused there costs an established
session nothing.

The limit SHALL be generous enough that a person signing in, failing, and trying
again is never refused.

For the claim route this limit is defence in depth and nothing more. Because it
cannot identify individual clients, it SHALL NOT be the control that makes
guessing a claim code impractical; the code's own entropy is (see
`authentication`). The application applies its own bound on claim attempts in
addition to this one.

#### Scenario: Ordinary sign-in is never refused
- **WHEN** a user starts a sign-in, abandons it, and starts another
- **THEN** neither request is refused by the limit

#### Scenario: The limit is enforced ahead of the application
- **WHEN** requests to start a sign-in exceed the configured rate
- **THEN** the excess is refused without reaching the application

#### Scenario: The claim route is limited too
- **WHEN** requests to claim the install exceed the configured rate
- **THEN** the excess is refused without reaching the application

#### Scenario: The web server limit is not the anti-guessing control
- **WHEN** a claim code is chosen
- **THEN** its entropy alone makes guessing impractical, without relying on this
  limit

