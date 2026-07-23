## ADDED Requirements

### Requirement: Detect orphaned posters
The system SHALL identify orphaned posters: posters imported from Plex whose
Plex rating key is no longer present on the Plex server. A poster that has no
Plex mapping (a manual upload) SHALL NOT be treated as an orphan.

#### Scenario: Removed Plex item yields an orphan
- **WHEN** a poster was imported for a Plex item that no longer exists in Plex
- **THEN** the system lists that poster as an orphan

#### Scenario: Present Plex item is not an orphan
- **WHEN** a poster's Plex item still exists in Plex
- **THEN** the system does not list that poster as an orphan

#### Scenario: Manual upload is not an orphan
- **WHEN** a poster was uploaded manually and has no Plex mapping
- **THEN** the system does not list that poster as an orphan

### Requirement: Detection requires a reachable Plex server
The system SHALL only compute orphans against a configured, reachable Plex
server, so that an outage cannot cause real posters to be flagged.

#### Scenario: Plex unavailable
- **WHEN** Plex is not configured or cannot be reached and the user opens the
  orphans page
- **THEN** the system explains that Plex is required and lists no orphans

### Requirement: Delete orphaned posters
The system SHALL let the user delete the detected orphans, removing each
orphan's poster file and its Plex mapping.

#### Scenario: Delete all orphans
- **WHEN** the user chooses to delete all orphans
- **THEN** the system removes each orphan's file and mapping and reports how many
  were removed

#### Scenario: Non-orphans are preserved
- **WHEN** the user deletes all orphans
- **THEN** posters that are still linked to Plex and manual uploads remain
