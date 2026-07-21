## ADDED Requirements

### Requirement: Plex connection configuration
The system SHALL read the Plex server URL and token from the environment and
report whether Plex integration is configured. When it is not configured, the
system SHALL show guidance rather than attempting to connect.

#### Scenario: Plex not configured
- **WHEN** no Plex server URL or token is set and a user opens the Plex page
- **THEN** the system explains that Plex must be configured and offers no import

#### Scenario: Plex configured
- **WHEN** a Plex server URL and token are set
- **THEN** the system treats Plex as configured and lists its libraries

### Requirement: List Plex libraries
The system SHALL list the libraries (sections) available on the configured Plex
server, identifying each as a movie or show library.

#### Scenario: Libraries are listed
- **WHEN** a user opens the Plex page with a reachable server
- **THEN** the system shows each library's name and type

#### Scenario: Server unreachable
- **WHEN** the Plex server cannot be reached
- **THEN** the system shows a connection error and does not crash

### Requirement: Import posters from Plex
The system SHALL import the current Plex poster for each item in the selected
libraries and media types (movies, TV shows, TV seasons, collections), storing
each into its matching category.

#### Scenario: Movies imported into the Movies category
- **WHEN** a user imports a selected movie library
- **THEN** the system stores each movie's Plex poster in the Movies category

#### Scenario: Only selected media types are imported
- **WHEN** a user selects a show library but only the "TV Shows" media type
- **THEN** the system imports show posters and does not import season posters

#### Scenario: A failed item does not abort the import
- **WHEN** one item's poster cannot be downloaded during an import
- **THEN** the system skips that item, continues, and reports it as failed while
  still importing the others

### Requirement: Idempotent re-import
The system SHALL record which Plex item (by rating key) each stored poster came
from, and on re-import SHALL overwrite the same poster file rather than creating
a duplicate.

#### Scenario: Re-import overwrites, not duplicates
- **WHEN** a user imports a library and later imports it again
- **THEN** each item's poster is updated in place and no duplicate poster is
  created

### Requirement: Library tracking
The system SHALL record the Plex libraries seen during import so later features
can reconcile the local library against Plex.

#### Scenario: Libraries recorded on import
- **WHEN** an import runs
- **THEN** the system stores the name and type of each library it imported from
