## MODIFIED Requirements

### Requirement: Plex item mapping
The system SHALL record, for each stored poster, the Plex item it came from (by
rating key) together with the Plex library section that item belongs to and the
item's Plex "added at" timestamp, so later operations can address the item for
artwork, locking, and label edits, and so the gallery can order posters by when
their media was added to Plex. On re-import the system SHALL overwrite the same
poster file rather than creating a duplicate.

#### Scenario: Re-import overwrites, not duplicates
- **WHEN** a user imports a library and later imports it again
- **THEN** each item's poster is updated in place and no duplicate poster is
  created

#### Scenario: Section recorded on import
- **WHEN** an item is imported from a Plex library
- **THEN** the system stores that library's section identifier with the item's
  poster mapping

#### Scenario: Added-at recorded on import
- **WHEN** an item that reports a Plex "added at" timestamp is imported
- **THEN** the system stores that timestamp with the item's poster mapping so it
  can order the gallery by date added

#### Scenario: Missing added-at does not fail import
- **WHEN** an item does not report a Plex "added at" timestamp
- **THEN** the import still records the mapping and the poster remains browsable
