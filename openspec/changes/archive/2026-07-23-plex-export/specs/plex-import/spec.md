## ADDED Requirements

### Requirement: Record the Plex section for each item
The system SHALL record, alongside each imported item's mapping, the Plex
library section it belongs to, so later features can address the item for
locking and label edits.

#### Scenario: Section recorded on import
- **WHEN** an item is imported from a Plex library
- **THEN** the system stores that library's section identifier with the item's
  poster mapping
