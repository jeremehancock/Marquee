## ADDED Requirements

### Requirement: Skip unchanged posters on import
An import SHALL avoid downloading a poster from Plex when the item's artwork has
not changed since the last import and the local poster file still exists,
reducing load on the Plex server.

#### Scenario: Unchanged poster is skipped
- **WHEN** an import processes an item whose Plex poster version matches the one
  stored from a previous import and whose local file is present
- **THEN** the system does not download the poster and counts it as skipped

#### Scenario: Changed poster is re-imported
- **WHEN** an import processes an item whose Plex poster version differs from the
  stored one
- **THEN** the system downloads the new poster and overwrites the local file

#### Scenario: Missing local file is re-imported
- **WHEN** an import processes an item whose Plex poster version is unchanged but
  whose local file is missing
- **THEN** the system downloads the poster again

### Requirement: Force a full re-import
The import screen SHALL let the user force re-downloading posters that would
otherwise be skipped.

#### Scenario: Forced re-import ignores the skip check
- **WHEN** the user starts an import with the re-download option enabled
- **THEN** the system downloads every selected poster regardless of whether it
  changed

### Requirement: Report skipped posters
The import summary SHALL report how many posters were skipped as unchanged.

#### Scenario: Summary includes the skipped count
- **WHEN** an import finishes having skipped one or more unchanged posters
- **THEN** the summary states how many were skipped
