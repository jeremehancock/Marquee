## ADDED Requirements

### Requirement: The wall shows works

The wall's random rotation SHALL draw only from the poster categories that
represent a work in the library: Movies and TV Shows. TV Seasons and Collections
SHALL NOT appear on the wall — a season is a subdivision of a work and a
collection is a grouping of works, and the wall exists to show the works
themselves.

The pool SHALL be defined as the set of categories that are works, rather than
as the full set of categories minus those excluded, so that a poster category
introduced later does not appear on the wall until it is deliberately named a
work.

This narrowing applies to the random rotation only. Seasons and collections
remain fully available everywhere else in the library, and the now-playing
takeover is unaffected — it obtains its posters from Plex rather than from these
categories.

#### Scenario: Seasons are not shown

- **WHEN** the wall requests posters and the library contains TV Season posters
- **THEN** no TV Season poster is returned

#### Scenario: Collections are not shown

- **WHEN** the wall requests posters and the library contains Collection posters
- **THEN** no Collection poster is returned

#### Scenario: Movies and shows are shown

- **WHEN** the wall requests posters and the library contains Movie and TV Show
  posters
- **THEN** posters from both categories are returned

#### Scenario: Only excluded categories are imported

- **WHEN** the library contains TV Season and Collection posters but no Movie or
  TV Show posters
- **THEN** the wall returns no posters and shows its message that there is
  nothing to display yet

#### Scenario: Seasons and collections remain in the library

- **WHEN** TV Season and Collection posters are excluded from the wall
- **THEN** they remain listed, searchable, and editable in the gallery as before

## MODIFIED Requirements

### Requirement: Full-screen rotating wall
The system SHALL provide a full-screen page that continuously displays posters
drawn at random from the library's works, transitioning between them
automatically. The wall SHALL open in a separate browser tab so the gallery stays
open behind it. The wall is intended for unattended display on a monitor, so it
SHALL present the posters without on-screen navigational chrome such as an exit
control; a viewer leaves by closing the tab.

#### Scenario: Wall displays posters
- **WHEN** a visitor opens the wall and the library has posters for works
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

### Requirement: Random poster batches
The system SHALL expose an endpoint that returns a fresh batch of random poster
references so the wall can keep refreshing without a full reload.

#### Scenario: Batch of random posters
- **WHEN** the wall requests more posters
- **THEN** the system returns a batch of poster references selected at random
  from the library's works
