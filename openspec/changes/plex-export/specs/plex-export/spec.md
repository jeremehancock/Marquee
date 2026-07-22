## ADDED Requirements

### Requirement: Send a poster to Plex
The system SHALL let an authenticated user send a library poster to its linked
Plex item, uploading the stored image so Plex uses it.

#### Scenario: Linked poster is uploaded
- **WHEN** a user sends a poster that is linked to a Plex item
- **THEN** the system uploads the stored image to that Plex item

#### Scenario: Unlinked poster cannot be sent
- **WHEN** a user sends a poster that has no Plex mapping
- **THEN** the system reports that the poster is not linked to Plex and uploads
  nothing

### Requirement: Lock the poster in Plex
After uploading, the system SHALL lock the item's poster field in Plex so a
later metadata refresh does not replace it.

#### Scenario: Poster is locked after upload
- **WHEN** a poster is successfully sent to Plex
- **THEN** the system locks that item's poster field

### Requirement: Optional Kometa overlay-label removal
When `PLEX_REMOVE_OVERLAY_LABEL` is enabled, the system SHALL remove the
"Overlay" label from the Plex item after updating its poster; when disabled, the
label SHALL be left unchanged.

#### Scenario: Label removed when enabled
- **WHEN** `PLEX_REMOVE_OVERLAY_LABEL` is true and a poster is sent to Plex
- **THEN** the system removes the "Overlay" label from that item

#### Scenario: Label untouched when disabled
- **WHEN** `PLEX_REMOVE_OVERLAY_LABEL` is false and a poster is sent to Plex
- **THEN** the system does not modify the item's labels

### Requirement: Send action only for linked posters
The system SHALL offer the Send-to-Plex action only for posters that are linked
to a Plex item, and only when Plex is configured.

#### Scenario: Action shown for linked posters
- **WHEN** Plex is configured and a poster is linked to a Plex item
- **THEN** the gallery offers a Send-to-Plex action for that poster

#### Scenario: Action hidden for unlinked posters
- **WHEN** a poster has no Plex mapping
- **THEN** the gallery does not offer a Send-to-Plex action for it
