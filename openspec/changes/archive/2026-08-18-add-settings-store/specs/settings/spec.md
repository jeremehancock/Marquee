## ADDED Requirements

### Requirement: Configuration is persisted in a settings store
The system SHALL persist configuration to a settings store on the persistent
volume, held separately from the Plex connection store and from the SQLite
database.

The database SHALL NOT be used, because it is specified as a cache of Plex data
that is safe to delete; configuration kept there would make deleting it
destructive. The connection store SHALL NOT be used, because it holds
credentials with a different lifecycle — discarding preferences must not require
signing in to Plex again, and writing a preference must not round-trip a
credential.

Writes SHALL be atomic: the file is written under a temporary name and renamed
into place, so a concurrent reader observes either the previous contents or the
new contents and never a partial write. A write SHALL re-read the stored
contents first and change only the keys it owns, because the web process and the
scheduled import each hold their own store and either may have written since the
other last read.

#### Scenario: A setting survives a container restart
- **WHEN** a setting is written and the container is recreated
- **THEN** the stored value is in effect after the restart

#### Scenario: A write preserves keys it did not set
- **WHEN** one process writes a setting after another process has written a
  different setting
- **THEN** both settings are present in the store

#### Scenario: Configuration is not kept in the deletable cache
- **WHEN** the SQLite database is deleted
- **THEN** every stored setting is still in effect

### Requirement: Configuration resolves once at bootstrap
The system SHALL resolve every setting from the store exactly once per request,
at bootstrap, into the same immutable typed configuration objects that already
carry configuration. No code outside that resolution SHALL read the store.

Value coercion, flooring, and fallback SHALL remain the responsibility of the
configuration objects rather than the store. The store returns stored values;
the configuration decides that a session duration below its floor is a lockout
and that an unrecognized sort order falls back to the default. Keeping that
decision in one place is what guarantees a value corrected at bootstrap and a
value rejected at input cannot disagree.

Because resolution happens per request, a changed setting SHALL take effect on
the next request without restarting the container.

#### Scenario: A stored setting is in effect on the next request
- **WHEN** a setting is changed
- **THEN** the following request observes the new value
- **AND** no restart is required

#### Scenario: Floors and fallbacks are preserved
- **WHEN** a stored value falls below a documented floor or is not recognized
- **THEN** the configuration exposes the same corrected value it would have
  applied to an environment variable holding that value

### Requirement: The environment seeds the store exactly once
The system SHALL treat environment variables as a source that seeds the store,
never as a source consulted when a setting is read.

On the first bootstrap that finds no stored settings, the system SHALL populate
the store from the environment and SHALL record that seeding has happened. An
install upgrading from a version that had no store therefore keeps every value
its compose file set.

Seeding SHALL happen at most once. Thereafter the store is the only source:
environment variables the store owns SHALL NOT be obeyed, and SHALL be read only
in order to report that they no longer take effect.

Reads SHALL come from the store both before and after seeding, so that no
setting ever has two live sources.

#### Scenario: An upgrading install keeps its compose configuration
- **WHEN** an install with settings in its environment starts for the first time
  with no stored settings
- **THEN** those values are written to the store and are in effect

#### Scenario: A fresh install seeds its defaults
- **WHEN** an install with no settings in its environment starts for the first
  time
- **THEN** every setting resolves to its documented default

#### Scenario: The environment stops applying after seeding
- **WHEN** an environment variable the store owns is changed and the container is
  recreated, and the store has already been seeded
- **THEN** the stored value remains in effect
- **AND** the environment value is not applied

#### Scenario: Seeding does not repeat
- **WHEN** a seeded store is present and the container is recreated
- **THEN** the store is not seeded again
- **AND** no stored value is overwritten from the environment

### Requirement: Superseded environment variables are reported
The system SHALL report environment variables that are still set but no longer
take effect, so that an install is told why a value it configured is being
ignored rather than being left to discover it.

The report SHALL distinguish two kinds, because the remedy differs:

- **Retired** — `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, and
  `AUTH_BYPASS`. These name capabilities that no longer exist and never return.
- **Relocated** — settings the store now owns. These are managed in the
  application instead.

Both kinds SHALL be reported whenever the variable is present. Because seeding
happens on the first bootstrap, a relocated variable has no effect from that
point onward, so there is no state in which reporting it would be false.

The two kinds SHALL NOT be collapsed into one message. A user told that
`AUTH_PASSWORD` is "managed in the application" would look for a password field
that does not exist and never will.

#### Scenario: A retired variable is reported
- **WHEN** `PLEX_TOKEN` is set
- **THEN** it is reported as retired
- **AND** it is not used to authenticate to Plex

#### Scenario: A relocated variable is reported
- **WHEN** a setting the store owns is still set in the environment
- **THEN** that variable is reported as relocated
- **AND** the stored value, not the environment value, is in effect

#### Scenario: The two kinds are distinguishable
- **WHEN** both a retired and a relocated variable are set
- **THEN** the report identifies which kind each one is, so that each can be
  given its own remedy

#### Scenario: Nothing to report
- **WHEN** no superseded variable is set
- **THEN** the report is empty and no notice is shown

### Requirement: A damaged store degrades to defaults
The system SHALL treat a missing, unreadable, or malformed settings store as
"nothing stored" rather than as an error, and SHALL apply its documented
defaults.

A stored entry whose value is not usable SHALL be dropped individually rather
than costing the whole file, so that one bad value costs one setting and not the
install.

The store is read on every request. A parse failure that raised would make one
bad write unreachable to fix, which is the failure this requirement exists to
prevent.

#### Scenario: Absent store
- **WHEN** no settings file exists
- **THEN** every setting resolves to its documented default
- **AND** no error is raised

#### Scenario: Malformed store
- **WHEN** the settings file does not parse
- **THEN** every setting resolves to its documented default
- **AND** the application serves requests normally

#### Scenario: One unusable entry
- **WHEN** the settings file parses but one entry holds a value of the wrong
  shape
- **THEN** that setting resolves to its documented default
- **AND** every other stored setting is still in effect

### Requirement: Settings that locate the store stay in the environment
The system SHALL continue to read from the environment those settings that
cannot come from the store.

`DATA_DIR`, `POSTERS_DIR`, and `SESSION_DIR` SHALL remain environment-only: the
store lives inside `DATA_DIR`, so resolving it from the store is circular.
`DISPLAY_ERRORS` SHALL remain environment-only for a different reason — it is
the switch that makes a broken install diagnosable, and must not depend on
reading a file that may be what broke.

`UPDATE_REPO` and `POSTER_SOURCE_URL` SHALL remain environment-only because they
are development overrides rather than user settings; offering them as settings
would invite installs that point at the wrong service and cannot explain why.

#### Scenario: The data directory is read from the environment
- **WHEN** `DATA_DIR` is set in the environment
- **THEN** the store is located beneath that directory
- **AND** no attempt is made to resolve `DATA_DIR` from the store

#### Scenario: Error display survives an unreadable store
- **WHEN** the settings file cannot be read and `DISPLAY_ERRORS` is set
- **THEN** error display follows the environment value
