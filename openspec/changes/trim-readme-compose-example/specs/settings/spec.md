## ADDED Requirements

### Requirement: The documented install is the app-configured install
The project's install instructions SHALL describe configuring Marquee in the
application, not in a compose file. Following them exactly SHALL produce an
install with nothing to clean up.

The Quick start compose example SHALL contain no environment variable that the
settings store owns — that is, no variable named by any `SettingKey`, whether
set or commented out. An example that offers a setting the store owns teaches
the reader to configure Marquee in the place the application will then tell them
to stop using.

The example SHALL contain only variables whose meaning cannot move into the
store: the container's own settings, the paths that locate the store, and
`PLEX_SERVER_URL`.

Seeding SHALL remain documented rather than implied to be removed, in a place
reached from the install instructions rather than in them. That documentation
SHALL state that it applies only to an install with no store yet, so a reader
cannot mistake it for a way to change a running install.

#### Scenario: The install instructions offer no relocated variable
- **WHEN** the Quick start compose example is read
- **THEN** no environment variable that the settings store owns appears in it,
  set or commented out

#### Scenario: Following the instructions produces no superseded notice
- **WHEN** an install is created from the Quick start compose example exactly as
  written
- **THEN** no variable it sets is reported as superseded

#### Scenario: The permanently-environmental variables are still documented
- **WHEN** a reader needs the container settings, the Plex server address, the
  session directory, or the `/config` path overrides
- **THEN** the install instructions document them, without sending the reader
  elsewhere

#### Scenario: Seeding a fresh install is documented elsewhere
- **WHEN** a reader wants to pre-configure a brand-new install from its compose
  file
- **THEN** the install instructions link to documentation that lists every
  variable the store is seeded from
- **AND** that documentation states seeding applies only on the first start with
  no store

#### Scenario: An existing install is told its variables are not a fault
- **WHEN** a reader whose compose file still sets relocated variables reads the
  install instructions
- **THEN** they are told those variables are ignored rather than broken, and
  that the settings screen names the ones to delete

### Requirement: Documented configuration matches the code that reads it
Where the project's documentation names a variable, a default, or the place a
setting is changed, that claim SHALL match the code and the specs that define
it.

Documentation SHALL NOT instruct the reader to edit a compose file and restart
for anything the settings screen owns. Such an instruction is worse than an
omission: it is a procedure that appears to work and quietly has no effect after
seeding.

Where the timing of a change matters, the documentation SHALL state it as the
system behaves — a saved setting takes effect for subsequent requests, and
auto-import applies from the next scheduled check rather than immediately.

#### Scenario: A relocated setting is documented as a screen, not a variable
- **WHEN** documentation describes changing a setting the store owns
- **THEN** it directs the reader to the settings screen
- **AND** it does not describe editing a variable and restarting as the way to
  change it

#### Scenario: The environment-only variables are listed completely
- **WHEN** documentation states which variables are still read from the
  environment
- **THEN** the list matches the variables the code actually reads from it, and
  does not present the store's own variables as exempt

#### Scenario: Auto-import timing is stated distinctly
- **WHEN** documentation describes when a saved setting takes effect
- **THEN** auto-import is called out as applying from the next scheduled check,
  separately from settings that apply to subsequent requests
