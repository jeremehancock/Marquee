# Orphan Detection Specification

## Purpose

Reconciling the library against Plex to find posters whose media no longer
exists — a show you deleted, a movie you replaced — and letting the user clear
them out.

Every poster in the library originates from a Plex import and carries a mapping
to its Plex item, so detection is simply a matter of asking which of those
mappings Plex no longer recognizes.

One safety rule defines this capability: detection runs only against a
configured, reachable Plex server, so an outage can never cause a real library
to be flagged for deletion.

## Requirements

### Requirement: Detect orphaned posters
The system SHALL identify orphaned posters: posters whose mapped Plex rating key
is no longer present on the Plex server.

#### Scenario: Removed Plex item yields an orphan
- **WHEN** a poster was imported for a Plex item that no longer exists in Plex
- **THEN** the system lists that poster as an orphan

#### Scenario: Present Plex item is not an orphan
- **WHEN** a poster's Plex item still exists in Plex
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
- **THEN** posters whose Plex items still exist remain
