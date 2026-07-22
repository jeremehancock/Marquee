## ADDED Requirements

### Requirement: Find posters for a media item
The system SHALL let a user search for candidate posters for a specific media
item through the configured poster source (the posteria.app API), using the
item's title, media type, and — for seasons — season number.

#### Scenario: Candidates returned
- **WHEN** a user opens Find Posters for a poster linked to a Plex item
- **THEN** the system queries the poster source and shows the candidate posters
  it returns

#### Scenario: No candidates or source unavailable
- **WHEN** the poster source returns no results or cannot be reached
- **THEN** the system reports that no posters were found and changes nothing

### Requirement: Apply a found poster
The system SHALL let a user apply a candidate poster from the search results,
replacing the poster in place and — when linked and configured — pushing it to
Plex and locking it.

#### Scenario: Apply a candidate
- **WHEN** a user selects a candidate poster from the results
- **THEN** the system fetches that image, overwrites the poster's file, and (when
  linked) uploads it to Plex and locks it
