# Auto Import Specification

## Purpose

Running the standard Plex import on a schedule so the library keeps up with Plex
without anyone opening the app. Auto-import reuses `plex-import` wholesale —
including its skip-unchanged behavior, which is what makes a recurring run cheap
for the Plex server — and adds only scheduling, per-type toggles, and library
exclusions.

It is off by default and fails quietly: a misconfigured or disabled auto-import
does nothing rather than erroring.
## Requirements
### Requirement: Scheduled import of configured media types
The system SHALL provide an auto-import that, when enabled, imports the
configured media types (movies, TV shows, TV seasons, collections) from Plex on
a schedule, reusing the standard import behavior.

#### Scenario: Enabled types are imported
- **WHEN** auto-import runs with movies and TV shows enabled
- **THEN** it imports movie and show posters and does not import seasons or
  collections

#### Scenario: Runs across libraries
- **WHEN** auto-import runs
- **THEN** it imports from every Plex library that is not excluded

### Requirement: Excluded libraries are skipped
The system SHALL skip any Plex library that is excluded by the excluded-libraries
setting. A library is excluded when its **name** — the library title as it
appears in Plex — matches an entry in that setting, compared case-insensitively
and ignoring leading and trailing whitespace on both sides. Libraries SHALL NOT
be matched by section key or any other identifier, so an entry that is not a
library name excludes nothing.

The setting is chosen in the application, from the libraries the connected server
reports (see `settings`), so an entry that matches no library is now a leftover
rather than something a user can newly type. The matching rule still holds for
one, because a stored entry outlives the library it named.

The exclusion is app-wide rather than specific to auto-import: an excluded
library is invisible to the whole application, including the manual import
screen and orphan detection (see `plex-import` and `orphan-detection`).

#### Scenario: Excluded library is not imported
- **WHEN** a library's name is listed in the excluded-libraries setting
- **THEN** auto-import imports nothing from that library

#### Scenario: Name matching ignores case and surrounding whitespace
- **WHEN** an entry in the excluded-libraries setting differs from a library's
  name only in letter case or surrounding whitespace
- **THEN** that library is excluded

#### Scenario: A non-name entry excludes nothing
- **WHEN** an entry in the excluded-libraries setting matches no library name —
  for example a section key
- **THEN** no library is excluded on account of that entry

### Requirement: Auto-import no-ops safely
The system SHALL do nothing (beyond logging) when auto-import is disabled, when
Plex is not configured, or when no media types are enabled.

#### Scenario: Disabled
- **WHEN** auto-import is disabled
- **THEN** it imports nothing

#### Scenario: Nothing selected
- **WHEN** auto-import is enabled but no media types are enabled
- **THEN** it imports nothing

