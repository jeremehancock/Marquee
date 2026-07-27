## MODIFIED Requirements

### Requirement: List Plex libraries
The system SHALL list the libraries (sections) available on the configured Plex
server, identifying each as a movie or show library. In the library picker each
library's type SHALL be presented parenthetically after its name (e.g.
`My Movies (Movies)`, `Shows (TV)`) rather than as a bare trailing word.

#### Scenario: Libraries are listed
- **WHEN** a user opens the Plex page with a reachable server
- **THEN** the system shows each library's name and type

#### Scenario: Library type shown in parentheses
- **WHEN** the library picker renders a library
- **THEN** its type appears in parentheses after the library name

#### Scenario: Server unreachable
- **WHEN** the Plex server cannot be reached
- **THEN** the system shows a connection error and does not crash

## ADDED Requirements

### Requirement: Import content-type controls presentation
On the import screen the Step 1 content-type controls (the pill-style
selectors) SHALL present their label text horizontally centered within each pill.

#### Scenario: Pill label is centered
- **WHEN** the import screen renders the Step 1 content-type pills
- **THEN** each pill's label text is horizontally centered within the pill
