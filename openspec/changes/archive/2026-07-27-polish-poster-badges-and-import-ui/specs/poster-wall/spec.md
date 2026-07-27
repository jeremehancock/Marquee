## MODIFIED Requirements

### Requirement: Full-screen rotating wall
The system SHALL provide a full-screen page that continuously displays posters
drawn at random from the library, transitioning between them automatically. The
wall SHALL open in a separate browser tab so the gallery stays open behind it.
The wall is intended for unattended display on a monitor, so it SHALL present the
posters without on-screen navigational chrome such as an exit control; a viewer
leaves by closing the tab.

#### Scenario: Wall displays posters
- **WHEN** an authenticated user opens the wall and the library has posters
- **THEN** the system presents a full-screen display that rotates through random
  posters

#### Scenario: Open the wall
- **WHEN** a user opens the Poster Wall from the gallery
- **THEN** it opens in a new tab

#### Scenario: No on-screen exit control
- **WHEN** the wall is displayed
- **THEN** it shows no exit or navigation control overlaid on the posters

#### Scenario: Empty library
- **WHEN** the library has no posters
- **THEN** the wall shows a message that there is nothing to display yet
