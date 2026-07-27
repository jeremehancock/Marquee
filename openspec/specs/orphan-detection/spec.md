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

### Requirement: The orphans page explains what deletion does
The orphans page SHALL describe what an orphan is and what deleting one
removes, and SHALL NOT claim that any poster in the library is exempt from
orphan detection.

#### Scenario: Page explains the criterion and the consequence
- **WHEN** a user opens the orphans page
- **THEN** it states that orphans are posters imported from Plex whose media no
  longer exists there
- **AND** it states that deleting an orphan removes the stored poster file and
  its Plex mapping

#### Scenario: No exemption is claimed
- **WHEN** a user opens the orphans page
- **THEN** it does not claim that manually uploaded posters, or any other class
  of poster, are excluded from orphan detection

### Requirement: The orphans page shows a loading state while detection runs
The orphans page SHALL render its shell immediately and display a visible
loading indicator while orphan detection runs, so that the reconciliation
against Plex never leaves the user facing an unresponsive page. Detection
results SHALL be delivered to the page after the shell has rendered, rather
than blocking the initial page response on the scan.

#### Scenario: Loading indicator while detection runs
- **WHEN** a user with Plex configured opens the orphans page
- **THEN** the page renders immediately with a visible loading indicator
- **AND** the orphan detection runs without blocking that initial render

#### Scenario: Results replace the loading indicator
- **WHEN** orphan detection finishes after the page has rendered
- **THEN** the loading indicator is removed
- **AND** the page shows the detected orphans, or an in-sync message when there
  are none, or the Plex error when detection could not complete

#### Scenario: No loading state when Plex is not configured
- **WHEN** a user opens the orphans page and Plex is not configured
- **THEN** the page renders immediately with the Plex-required message
- **AND** no loading indicator is shown and no detection is attempted

