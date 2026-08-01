## MODIFIED Requirements

### Requirement: Preview and apply a found poster
The system SHALL let a user open any candidate full screen to inspect it before
committing, and SHALL apply a candidate only through an explicit confirmation
taken from that preview, which replaces the poster in place and, when linked and
configured, pushes it to Plex and locks it. Where the source supplies a
reduced-size preview image for a candidate, the system SHALL use it for the
candidate grid, and SHALL use the full-resolution image when inspecting a
candidate full screen and when applying it.

Applying is a two-step commitment rather than a single action on the grid: a
candidate in the grid is activated to inspect it full screen, the preview offers
to use that candidate, and using it asks for a final confirmation before the
poster is changed. The user SHALL be able to abandon the change at either step
without the poster being altered.

This full-screen preview is the same one the change dialog's Upload and From URL
tabs use for their replacements, and it behaves identically for all three:
abandoning it SHALL leave the change dialog it was opened from standing, so that
a dismissal never discards what the user supplied on another tab.

#### Scenario: Preview then apply
- **WHEN** a user views the found-poster results
- **THEN** they can open a candidate full screen to inspect it, and apply it
  only from that full-screen preview

#### Scenario: Found-poster action label
- **WHEN** a user is previewing a candidate full screen
- **THEN** the action that applies it is labelled "Use this poster", and the
  confirmation it asks for is labelled "Change poster"

#### Scenario: Applying requires a confirmation
- **WHEN** a user chooses to use the candidate they are previewing
- **THEN** the system asks for a final confirmation, and the poster is changed
  only once that confirmation is given

#### Scenario: Abandoning the preview leaves the poster unchanged
- **WHEN** a user closes the preview, or declines the final confirmation
- **THEN** the poster is not changed and the user is returned to the results

#### Scenario: Escape closes only the preview
- **WHEN** a user presses Escape while previewing a candidate
- **THEN** the preview closes and the change dialog behind it stays open on the
  Find Posters results

#### Scenario: Apply a candidate
- **WHEN** a user confirms applying a candidate poster from the results
- **THEN** the system fetches that image, overwrites the poster's file, and (when
  linked) uploads it to Plex and locks it

#### Scenario: Grid uses reduced-size images where available
- **WHEN** the results contain candidates for which the source supplied a
  reduced-size image
- **THEN** the candidate grid loads those images rather than the full-resolution
  originals

#### Scenario: Applying uses the full-resolution image
- **WHEN** a user applies a candidate whose grid image was a reduced-size preview
- **THEN** the system fetches and stores the full-resolution image, not the
  preview
