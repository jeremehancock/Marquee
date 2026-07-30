# Poster Sources Specification

## Purpose

Finding a better poster than the one Plex has. Marquee queries the posteria.app
API — a hosted service that aggregates candidates from TMDB, fanart.tv, and
TheTVDB — for a specific media item, presents the candidates, and applies the one
the user picks.

The poster source is an external dependency, not part of this repository. It is
best-effort: when it is unreachable or returns nothing, the user's poster is
left exactly as it was.
## Requirements
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

### Requirement: The poster search service is named to users
The product documentation SHALL identify posteria.app as the hosted poster
search service that backs Find Posters, as a general statement only — it SHALL
NOT document the service's endpoints, request or response formats, or internal
behavior, and it SHALL NOT elaborate on running or self-hosting the service.

#### Scenario: README names the service
- **WHEN** a reader reviews the README's description of Find Posters
- **THEN** posteria.app is named as the service the search runs against

#### Scenario: No implementation detail is published
- **WHEN** the documentation describes the poster search service
- **THEN** it states only what the service provides, not how it is called or
  how it produces its results

#### Scenario: No self-hosting guidance
- **WHEN** the documentation describes the poster search service
- **THEN** it does not invite the reader to run their own instance or explain
  how to point Marquee at one
- **AND** `POSTER_SOURCE_URL` remains documented in the configuration table as
  an ordinary setting, without such guidance attached

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

### Requirement: A stale recorded identifier is corrected by a search
When a search sends a recorded TMDB identifier and the poster source matches a
different work, the recorded identifier is stale — the source did not recognise it
and resolved the title instead. The system SHALL record the identifier the source
matched in place of the stale one, so the item is identified correctly on later
searches, and SHALL log the correction. The search result itself SHALL be
unaffected: the user sees the candidates the source returned.

The system SHALL detect this by comparing the identifier it **sent** against the
identifier the source matched. It SHALL NOT rely on the source reporting back the
identifier it was given, whether or not the source is documented as doing so.

The system SHALL NOT record an identifier for an item that had none. An item with
no recorded identifier is searched by its title every time, which is a path that
re-resolves on each search; recording one guess would pin the item to it
permanently, with no later mismatch to reveal the error.

#### Scenario: Stale identifier replaced
- **WHEN** a search sends a recorded TMDB identifier and the source matches a
  different identifier
- **THEN** the system records the matched identifier against that item and logs
  that it did so

#### Scenario: Detection survives a source that echoes what it resolved
- **WHEN** the source's response reports, as the identifier for the search, the
  identifier it resolved rather than the one it was sent — so that every
  identifier in the response agrees with every other
- **THEN** the system still detects the stale identifier and corrects it, because
  it compares against the identifier it sent rather than against any identifier
  the response reports

#### Scenario: Corrected item searches correctly afterwards
- **WHEN** a user searches again for an item whose identifier was corrected
- **THEN** the search sends the corrected identifier

#### Scenario: Matching identifier is left alone
- **WHEN** a search sends a recorded TMDB identifier and the source matches that
  same identifier
- **THEN** the stored record is not written to

#### Scenario: A missing identifier is not filled in
- **WHEN** a search sends no TMDB identifier because none is recorded, and the
  source reports the identifier of the work it matched by title
- **THEN** the system records no identifier for that item

#### Scenario: An identifier that was withheld is not a correction
- **WHEN** a search withholds a recorded TMDB identifier because the item is a
  collection, and the source reports the identifier of the work it matched by
  title
- **THEN** the system records no identifier for that item, because nothing was
  sent for the response to disagree with

#### Scenario: Correction does not change what the user sees
- **WHEN** a search corrects a stale identifier
- **THEN** the candidates shown are the ones the source returned, and no message
  about the correction is shown to the user

#### Scenario: Source reports no matched identifier
- **WHEN** the source's response carries no usable matched identifier
- **THEN** the system leaves the recorded identifier as it is and the search
  result is presented normally

