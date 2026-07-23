## ADDED Requirements

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
The system SHALL skip any Plex library whose name is listed in
`EXCLUDED_LIBRARIES` (case-insensitive).

#### Scenario: Excluded library is not imported
- **WHEN** a library's name is listed in `EXCLUDED_LIBRARIES`
- **THEN** auto-import imports nothing from that library

### Requirement: Auto-import no-ops safely
The system SHALL do nothing (beyond logging) when auto-import is disabled, when
Plex is not configured, or when no media types are enabled.

#### Scenario: Disabled
- **WHEN** auto-import is disabled
- **THEN** it imports nothing

#### Scenario: Nothing selected
- **WHEN** auto-import is enabled but no media types are enabled
- **THEN** it imports nothing
