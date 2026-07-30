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

## ADDED Requirements

### Requirement: A stale recorded identifier is corrected by a search
When a search sends a recorded TMDB identifier and the poster source reports that
it matched a different work, the recorded identifier is stale — the source did not
recognise it and resolved the title instead. The system SHALL record the
identifier the source matched in place of the stale one, so the item is identified
correctly on later searches, and SHALL log the correction. The search result
itself SHALL be unaffected: the user sees the candidates the source returned.

The system SHALL NOT record an identifier for an item that had none. An item with
no recorded identifier is searched by its title every time, which is a path that
re-resolves on each search; recording one guess would pin the item to it
permanently, with no later mismatch to reveal the error.

#### Scenario: Stale identifier replaced
- **WHEN** a search sends a recorded TMDB identifier and the source reports it
  matched a different identifier
- **THEN** the system records the matched identifier against that item and logs
  that it did so

#### Scenario: Corrected item searches correctly afterwards
- **WHEN** a user searches again for an item whose identifier was corrected
- **THEN** the search sends the corrected identifier

#### Scenario: Matching identifier is left alone
- **WHEN** a search sends a recorded TMDB identifier and the source reports it
  matched that same identifier
- **THEN** the stored record is not written to

#### Scenario: A missing identifier is not filled in
- **WHEN** a search sends no TMDB identifier because none is recorded, and the
  source reports the identifier of the work it matched by title
- **THEN** the system records no identifier for that item

#### Scenario: Correction does not change what the user sees
- **WHEN** a search corrects a stale identifier
- **THEN** the candidates shown are the ones the source returned, and no message
  about the correction is shown to the user

#### Scenario: Source reports no matched identifier
- **WHEN** the source's response carries no usable matched identifier
- **THEN** the system leaves the recorded identifier as it is and the search
  result is presented normally
