# Plex Export Specification

## Purpose

The mechanism by which Marquee writes back to Plex: uploading a stored poster to
its linked Plex item, locking the artwork field so a later metadata refresh
cannot overwrite the user's choice, and optionally clearing the Kometa "Overlay"
label.

This capability owns the *write path and its guarantees*. The user-facing
gestures that trigger it — changing a poster, re-sending one, applying a found
poster — belong to `poster-editing` and `poster-sources`. Every export depends
on the item mapping recorded by `plex-import`.

## Requirements

### Requirement: Upload a poster to Plex
The system SHALL upload a stored poster's image to its linked Plex item, so that
Plex uses that image as the item's artwork.

#### Scenario: Linked poster is uploaded
- **WHEN** the system exports a poster that is linked to a Plex item
- **THEN** it uploads the stored image to that Plex item

#### Scenario: Local file is left unchanged
- **WHEN** a poster is exported to Plex
- **THEN** the poster's stored file is unchanged by the export

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

### Requirement: Export requires a linked poster
The system SHALL export only posters that are linked to a Plex item, and SHALL
offer export actions only when Plex is configured and the poster is linked.

#### Scenario: Unlinked poster cannot be exported
- **WHEN** an export is attempted for a poster that has no Plex mapping
- **THEN** the system reports that the poster is not linked to Plex and uploads
  nothing

#### Scenario: Action shown for linked posters
- **WHEN** Plex is configured and a poster is linked to a Plex item
- **THEN** the gallery offers a Send-to-Plex action for that poster

#### Scenario: Action hidden for unlinked posters
- **WHEN** a poster has no Plex mapping
- **THEN** the gallery does not offer a Send-to-Plex action for it
