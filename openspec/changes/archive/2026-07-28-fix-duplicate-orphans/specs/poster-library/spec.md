## MODIFIED Requirements

### Requirement: Delete a poster
The system SHALL allow an authenticated user to delete a poster from a category.
Deleting a poster SHALL remove both the image file and every Plex item mapping
that points at that poster, so a deleted poster leaves no residual mapping behind.

#### Scenario: Poster is deleted
- **WHEN** an authenticated user deletes an existing poster
- **THEN** the system removes the image file and it no longer appears in the
  gallery

#### Scenario: Mapping is cleared with the file
- **WHEN** an authenticated user deletes a poster that was imported from Plex
- **THEN** the system also removes every Plex item mapping row for that poster's
  category and filename
- **AND** no orphan entry can later be produced from that removed mapping
