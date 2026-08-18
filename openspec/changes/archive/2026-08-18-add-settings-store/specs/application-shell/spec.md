## MODIFIED Requirements

### Requirement: Typed configuration from environment
The system SHALL resolve all configuration exactly once at bootstrap into
immutable, typed configuration objects, applying documented defaults when a
value is absent.

Where each setting comes from is defined by `settings`. The directories that
locate the settings store itself, and the error-display switch, are read from
the environment; every other setting is resolved from the settings store, which
the environment seeds once. This requirement governs the bootstrap contract —
read once, immutable, typed, defaulted — not the source.

Every setting SHALL have a default that the system applies when the value is
absent or empty, and each of those defaults SHALL be asserted by a test. A
default is a promise about where a self-hosted install keeps its data; one that
nothing asserts can be moved by a refactor with no test failing and no user
warned. This holds for every setting the configuration reads, not only the ones
documented for users — an undocumented setting is still a location something
depends on.

Settings that name a filesystem directory SHALL default to a path on the
persistent `/config` volume, and SHALL have any trailing separator removed before
the value is exposed. Paths are composed by appending, so an untrimmed trailing
slash produces a doubled separator in every path built from it. Trimming SHALL
apply uniformly to every directory setting, so that none of them behaves
differently from its siblings.

The Plex authentication token SHALL come from the persisted connection store
written by signing in to Plex, and SHALL NOT be read from the environment, nor
from the settings store. A `PLEX_TOKEN` variable, if present, SHALL NOT be used
to authenticate to Plex — the system MAY read it only to tell the user it is no
longer used. Resolution SHALL still happen once at bootstrap into the same
immutable configuration object.

The Plex server address is resolved from the settings store like any other
setting, seeded from `PLEX_SERVER_URL`. Signing in supplies a credential, never
an address, and the two SHALL remain separately sourced: a credential is
obtained by an authorization the user performs, while an address is configuration.

#### Scenario: Default applied for missing variable
- **WHEN** an optional setting such as the site title is not set in the store or
  the environment
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
- **WHEN** a value expected to be boolean is set to `"1"`, `"true"`, `"yes"`,
  or `"on"` in any casing
- **THEN** the configuration exposes it as boolean `true`
- **WHEN** it is set to any other non-empty value
- **THEN** the configuration exposes it as boolean `false`
- **WHEN** a value expected to be an integer is set to a numeric string
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

#### Scenario: Configuration is resolved once per request
- **WHEN** a request is served
- **THEN** the settings store is read during bootstrap
- **AND** no later code path reads it again to answer that request
