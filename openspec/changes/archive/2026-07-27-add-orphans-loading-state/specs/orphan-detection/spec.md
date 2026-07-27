## ADDED Requirements

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
