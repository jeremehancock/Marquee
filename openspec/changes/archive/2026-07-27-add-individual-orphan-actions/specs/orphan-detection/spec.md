## MODIFIED Requirements

### Requirement: Delete orphaned posters
The system SHALL let the user delete the detected orphans — all at once or one
at a time — removing each deleted orphan's poster file and its Plex mapping,
while preserving posters whose Plex items still exist.

#### Scenario: Delete all orphans
- **WHEN** the user chooses to delete all orphans
- **THEN** the system removes each orphan's file and mapping and reports how many
  were removed

#### Scenario: Delete a single orphan
- **WHEN** the user chooses to delete one specific orphan from the list
- **THEN** the system removes only that orphan's poster file and its Plex mapping
- **AND** the other detected orphans are left in place

#### Scenario: Deleting one orphan does not touch non-orphans
- **WHEN** the user deletes a single orphan
- **THEN** posters whose Plex items still exist remain, even if the request
  named a poster that is not actually an orphan

#### Scenario: Non-orphans are preserved
- **WHEN** the user deletes all orphans
- **THEN** posters whose Plex items still exist remain

## ADDED Requirements

### Requirement: The orphans page offers per-orphan actions
The orphans page SHALL present, for each detected orphan, a Download action that
saves that orphan's poster file and a Delete action that removes only that
orphan. These per-orphan controls SHALL be presented through the same
interaction pattern the poster library uses: a hover overlay on the card for
pointer input, and a tap-to-open action tray for touch input.

#### Scenario: Per-orphan controls on a pointer device
- **WHEN** a user on a pointer device views an orphan card
- **THEN** the card exposes a Download control and a Delete control for that
  orphan through the card's hover overlay

#### Scenario: Per-orphan controls on a touch device
- **WHEN** a user on a touch device taps an orphan card
- **THEN** an action tray opens presenting that orphan's Download and Delete
  controls

#### Scenario: Deleting a single orphan updates the page in place
- **WHEN** the user confirms deletion of one orphan
- **THEN** that orphan is removed from the list without a full page reload
- **AND** the reported orphan count reflects the removal

#### Scenario: Single-orphan deletion is confirmed before it runs
- **WHEN** the user chooses Delete for one orphan
- **THEN** the page asks the user to confirm before the orphan is deleted
