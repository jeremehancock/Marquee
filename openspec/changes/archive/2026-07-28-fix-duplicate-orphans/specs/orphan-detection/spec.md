## MODIFIED Requirements

### Requirement: Detect orphaned posters
The system SHALL identify orphaned posters: posters whose mapped Plex rating key
is no longer present on the Plex server. When a mapping's poster file no longer
exists on disk, the system SHALL prune that mapping during detection rather than
retaining or listing it, so that stale mappings cannot resurface as duplicate
orphans and no two orphan entries are ever backed by the same poster file.

#### Scenario: Removed Plex item yields an orphan
- **WHEN** a poster was imported for a Plex item that no longer exists in Plex
- **THEN** the system lists that poster as an orphan

#### Scenario: Present Plex item is not an orphan
- **WHEN** a poster's Plex item still exists in Plex
- **THEN** the system does not list that poster as an orphan

#### Scenario: Mapping with a missing file is pruned
- **WHEN** detection encounters a mapping whose poster file is no longer on disk
- **THEN** the system removes that mapping and does not list it as an orphan

#### Scenario: No duplicate orphans for the same file
- **WHEN** more than one mapping would otherwise resolve to the same poster file
- **THEN** the system lists at most one orphan entry for that file
