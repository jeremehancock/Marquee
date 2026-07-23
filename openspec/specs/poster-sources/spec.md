# Poster Sources Specification

## Purpose

Finding a better poster than the one Plex has. Marquee queries the posteria.app
API — a hosted service that aggregates candidates from TMDB, TVDB, Fanart, and
Mediux — for a specific media item, presents the candidates, and applies the one
the user picks.

The poster source is an external dependency, not part of this repository. It is
best-effort: when it is unreachable or returns nothing, the user's poster is
left exactly as it was.

## Requirements

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

### Requirement: Preview and apply a found poster
The system SHALL let a user open any candidate full screen to inspect it before
committing, and separately apply a candidate — labelled "Select" — which
replaces the poster in place and, when linked and configured, pushes it to Plex
and locks it.

#### Scenario: Preview then apply
- **WHEN** a user views the found-poster results
- **THEN** they can open a candidate full screen to inspect it and separately
  choose to apply it

#### Scenario: Found-poster action label
- **WHEN** the found-poster results are shown
- **THEN** the apply action for each candidate is labelled "Select"

#### Scenario: Apply a candidate
- **WHEN** a user selects a candidate poster from the results
- **THEN** the system fetches that image, overwrites the poster's file, and (when
  linked) uploads it to Plex and locks it
