## MODIFIED Requirements

### Requirement: Find posters for a media item
The system SHALL let a user search for candidate posters for a specific media
item through the configured poster source, using facts recorded for that item at
import time: its title, media type, release year when known, and — for seasons —
the Plex season number. The system SHALL present the candidates the source
returns in the order the source returns them, and SHALL NOT filter, re-rank, or
re-order them.

#### Scenario: Candidates returned
- **WHEN** a user opens Find Posters for a poster linked to a Plex item
- **THEN** the system queries the poster source and shows the candidate posters
  it returns, in the order returned

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

### Requirement: Preview and apply a found poster
The system SHALL let a user open any candidate full screen to inspect it before
committing, and separately apply a candidate — labelled "Select" — which
replaces the poster in place and, when linked and configured, pushes it to Plex
and locks it. Where the source supplies a reduced-size preview image for a
candidate, the system SHALL use it for the candidate grid, and SHALL use the
full-resolution image when inspecting a candidate full screen and when applying
it.

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

#### Scenario: Grid uses reduced-size images where available
- **WHEN** the results contain candidates for which the source supplied a
  reduced-size image
- **THEN** the candidate grid loads those images rather than the full-resolution
  originals

#### Scenario: Applying uses the full-resolution image
- **WHEN** a user applies a candidate whose grid image was a reduced-size preview
- **THEN** the system fetches and stores the full-resolution image, not the
  preview

## ADDED Requirements

### Requirement: Season searches return season artwork only
A season poster search SHALL present only artwork the poster source identifies as
belonging to that season. The system SHALL NOT substitute the parent show's
artwork when a season has no artwork of its own, and SHALL NOT retry a season
search as a show search.

#### Scenario: Season with artwork
- **WHEN** a user searches for posters for a season that has season artwork
- **THEN** only that season's artwork is shown

#### Scenario: Season with no artwork
- **WHEN** a user searches for posters for a season that has no season artwork
- **THEN** the system reports that no posters were found and shows no candidates,
  rather than showing the show's artwork

### Requirement: Search outcomes are distinguishable
The system SHALL distinguish between the reasons a poster search produced no
usable candidates, and SHALL report each to the user in terms that indicate
whether the situation is transient, actionable, or final. In every case the
user's existing poster SHALL be left unchanged.

#### Scenario: Title did not match anything
- **WHEN** the poster source reports that no work matched the search
- **THEN** the system tells the user there was no match for the title

#### Scenario: Work found but has no artwork
- **WHEN** the poster source matches the work but returns no candidates
- **THEN** the system tells the user that no posters are available for it, which
  is reported differently from a title that did not match

#### Scenario: Search is temporarily unavailable
- **WHEN** the poster source cannot be reached, or reports that its upstream
  providers are unavailable
- **THEN** the system tells the user the search is temporarily unavailable and
  that retrying may succeed

#### Scenario: Too many searches
- **WHEN** the poster source reports that the search was rate limited
- **THEN** the system tells the user to wait before searching again

#### Scenario: Partial results
- **WHEN** the poster source returns candidates but reports that one or more of
  its providers failed
- **THEN** the system shows the candidates it received and indicates the results
  may be incomplete

#### Scenario: Failure leaves the poster untouched
- **WHEN** a poster search fails for any reason
- **THEN** the user's existing poster is unchanged

### Requirement: Accurate source attribution
Where the product names the services that supply poster candidates, it SHALL name
only services that can actually contribute artwork. A service that cannot
contribute SHALL NOT be named as a source.

#### Scenario: Documented sources are accurate
- **WHEN** the product documentation or interface names the services behind Find
  Posters
- **THEN** it names only services the poster search can return artwork from

#### Scenario: A non-contributing service is not named
- **WHEN** a previously-named service can no longer contribute artwork
- **THEN** it is removed from the documented and displayed source lists

#### Scenario: Pasting an image URL is not source attribution
- **WHEN** a user changes a poster by pasting an image URL
- **THEN** that path continues to accept image URLs from any host, independently
  of which services back Find Posters
