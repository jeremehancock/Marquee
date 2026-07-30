## MODIFIED Requirements

### Requirement: Find posters for a media item
The system SHALL let a user search for candidate posters for a specific media
item through the configured poster source, using facts recorded for that item at
import time: its title, media type, release year when known, the TMDB identifier
recorded for it when known, and — for seasons — the Plex season number. The system
SHALL present the candidates the source returns in the order the source returns
them, and SHALL NOT filter, re-rank, or re-order them.

The recorded TMDB identifier identifies the work exactly, so a search that carries
one does not depend on the item's title matching the work's title upstream. The
title SHALL still be sent with every search, because it is what the source falls
back to when the identifier is not one it recognises. A season SHALL be identified
by its **show's** identifier together with its season number, because a season has
no identifier of its own. A collection SHALL be searched by title only.

The release year and the TMDB identifier are independent: the system SHALL send
each whenever it is known, and SHALL NOT withhold either because the other is
present. The year appears to do nothing while an identifier resolves, but that is
the only case in which it does nothing — when the identifier is not one the source
recognises, the search falls back to the title, and the year is what separates
works that share one. Withholding it would leave exactly the searches that depend
on the fallback unable to tell those works apart.

#### Scenario: Candidates returned
- **WHEN** a user opens Find Posters for a poster linked to a Plex item
- **THEN** the system queries the poster source and shows the candidate posters
  it returns, in the order returned

#### Scenario: No candidates or source unavailable
- **WHEN** a search produces no usable candidates, whether because nothing
  matched, the work has no artwork, or the source could not be reached
- **THEN** the system leaves the user's poster unchanged and reports which of
  those applied, rather than giving one message for every reason

#### Scenario: Season number sent explicitly
- **WHEN** a user searches for a TV season poster
- **THEN** the system sends the Plex season number recorded for that item rather
  than deriving it from the poster's display title

#### Scenario: Specials are searchable
- **WHEN** a user searches for posters for a Specials season
- **THEN** the system searches for season number zero and shows the candidates
  returned for it

#### Scenario: Release year used as a hint
- **WHEN** a user searches for a movie or show poster and a release year is
  recorded for that item
- **THEN** the system includes that year in the search to disambiguate
  similarly-titled works

#### Scenario: Release year is sent even when an identifier is sent
- **WHEN** a user searches for an item for which both a release year and a TMDB
  identifier are recorded
- **THEN** the system sends both, rather than treating the year as redundant
  because the identifier is expected to resolve

#### Scenario: Search is unaffected by host clock accuracy
- **WHEN** the Marquee host's clock differs from real time by minutes or hours
- **THEN** poster search still succeeds

#### Scenario: Recorded identifier sent for a movie or show
- **WHEN** a user searches for a movie or show poster and a TMDB identifier is
  recorded for that item
- **THEN** the system sends that identifier together with the title

#### Scenario: A title the source could not match is found by identifier
- **WHEN** a user searches for an item whose recorded title differs from the
  work's title upstream, such as a locally annotated title, and a TMDB identifier
  is recorded for it
- **THEN** the search identifies the work by that identifier and returns its
  artwork, where searching by that title alone returns no match

#### Scenario: A season is identified by its show
- **WHEN** a user searches for a season poster and a TMDB identifier is recorded
  for that season
- **THEN** the system sends that identifier — which is the show's — together with
  the season number, and not as a season-level identifier

#### Scenario: Collections are searched by title
- **WHEN** a user searches for a collection poster
- **THEN** the system sends no TMDB identifier, whatever is recorded for that
  item, and the search is resolved from the collection's title

#### Scenario: No recorded identifier
- **WHEN** a user searches for an item for which no TMDB identifier is recorded
- **THEN** the system searches by title exactly as it does for an item that has
  never had one, and the search is not treated as an error

#### Scenario: Unusable recorded identifier
- **WHEN** the identifier recorded for an item is not a positive whole number
- **THEN** the system searches by title rather than sending a value the source
  would reject

#### Scenario: Identified work has no artwork
- **WHEN** a search identified by a recorded identifier finds the work but the
  work has no artwork
- **THEN** the system reports that no posters are available for it and SHALL NOT
  repeat the search by title
