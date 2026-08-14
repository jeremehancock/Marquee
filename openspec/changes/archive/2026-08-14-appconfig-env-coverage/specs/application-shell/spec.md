## MODIFIED Requirements

### Requirement: Typed configuration from environment
The system SHALL read all configuration from environment variables exactly once
at bootstrap into immutable, typed configuration objects, applying documented
defaults when a variable is absent.

Every setting SHALL have a default that the system applies when the variable is
absent or empty, and each of those defaults SHALL be asserted by a test. A
default is a promise about where a self-hosted install keeps its data; one that
nothing asserts can be moved by a refactor with no test failing and no user
warned. This holds for every variable the configuration reads, not only the ones
documented for users — an undocumented setting is still a location something
depends on.

Settings that name a filesystem directory SHALL default to a path on the
persistent `/config` volume, and SHALL have any trailing separator removed before
the value is exposed. Paths are composed by appending, so an untrimmed trailing
slash produces a doubled separator in every path built from it. Trimming SHALL
apply uniformly to every directory setting, so that none of them behaves
differently from its siblings.

The Plex authentication token is the one exception: it SHALL come from the
persisted connection store written by signing in to Plex, and SHALL NOT be read
from the environment. A `PLEX_TOKEN` variable, if present, SHALL NOT be used to
authenticate to Plex — the system MAY read it only to tell the user it is no
longer used. Resolution SHALL still happen once at bootstrap into the same
immutable configuration object; no other setting gains a second source.

The Plex server address remains an environment variable. Signing in supplies a
credential, never an address.

#### Scenario: Default applied for missing variable
- **WHEN** an optional environment variable such as `SITE_TITLE` is not set
- **THEN** the corresponding configuration value uses its documented default
  ("Marquee")

#### Scenario: Directory settings default onto the persistent volume
- **WHEN** none of the directory variables is set
- **THEN** the poster directory, the data directory, and the session directory
  each resolve to their named default beneath `/config`

#### Scenario: A trailing separator is trimmed from a directory setting
- **WHEN** any directory variable is set to a path ending in `/`
- **THEN** the configuration exposes the path without the trailing separator
- **AND** the behaviour is the same for every directory setting

#### Scenario: Boolean and integer coercion
- **WHEN** a variable expected to be boolean is set to `"1"`, `"true"`, `"yes"`,
  or `"on"` in any casing
- **THEN** the configuration exposes it as boolean `true`
- **WHEN** it is set to any other non-empty value
- **THEN** the configuration exposes it as boolean `false`
- **WHEN** a variable expected to be an integer is set to a numeric string
- **THEN** the configuration exposes it as an integer

#### Scenario: Stored token is the credential
- **WHEN** a token has been stored by signing in to Plex
- **THEN** Plex requests are made with the stored token

#### Scenario: An environment token is not a credential
- **WHEN** `PLEX_TOKEN` is set in the environment and no token has been stored
- **THEN** the system treats Plex as not connected
- **AND** no Plex request is made with the value of `PLEX_TOKEN`

#### Scenario: No token at all
- **WHEN** no token has been stored
- **THEN** the system reports Plex as not connected

## ADDED Requirements

### Requirement: The documented configuration surface is chosen by audience
Documentation of an environment variable SHALL follow from who is expected to set
it, not from whether the code happens to read it. Reading a variable is not by
itself a reason to publish it.

Variables an install is expected to set SHALL be listed in the README's
configuration table with their default. Variables that exist only for local
development SHALL be documented in `docs/development-workflow.md` instead, where
the toolchain they serve is described, so the user-facing table stays a list of
decisions a user actually has to make.

The layout of the `/config` volume SHALL be presented as fixed. `DATA_DIR` and
`POSTERS_DIR` SHALL therefore remain absent from the README, even though the code
reads them: the README's promise is that backing up `/config` backs up
everything, and advertising the subpaths as movable invites installs that split
the volume and then discover the promise no longer holds for them. They remain
overridable for the operator who already knows they exist; they are not offered.

This split SHALL be asserted by a test, for the same reason each default is: a
decision recorded only in prose is one a later edit reverses without anything
failing. The test SHALL assert both directions — that the variables meant to be
documented are present where they belong, and that the ones deliberately withheld
are absent — so that neither an accidental removal nor an accidental addition
passes unnoticed.

#### Scenario: A developer-only setting is kept out of the user-facing table
- **WHEN** a reader looks up `DISPLAY_ERRORS`
- **THEN** it is described in `docs/development-workflow.md`
- **AND** it does not appear in the README's configuration table

#### Scenario: The volume layout reads as fixed
- **WHEN** a reader consults the README for what `/config` contains
- **THEN** the posters, data, and session directories are described as its
  contents
- **AND** no environment variable is offered for relocating them individually

#### Scenario: An undocumented setting is still pinned by a test
- **WHEN** a variable is deliberately left out of the user-facing documentation
- **THEN** its default is still asserted by a test, so that omitting it from the
  documentation does not also omit it from the guarantees

#### Scenario: Adding a withheld variable to the README fails a test
- **WHEN** an edit names `DATA_DIR` or `POSTERS_DIR` in the README
- **THEN** a test fails, requiring the decision to be overturned deliberately
  rather than drifting

#### Scenario: Removing a documented variable from the README fails a test
- **WHEN** an edit removes a variable an install is expected to set, such as
  `SESSION_DIR`, from the README
- **THEN** a test fails, so the absence assertions cannot be satisfied by
  documentation that has gone missing entirely
