## MODIFIED Requirements

### Requirement: Find posters for a media item
The system SHALL let a user search for candidate posters for a specific media
item through the configured poster source, using facts recorded for that item at
import time: its title, media type, release year when known, the TMDB identifier
recorded for it when known, and — for seasons — the Plex season number. The system
SHALL present every candidate the source returns, and SHALL NOT filter, re-rank,
or re-score them. The order the source returns candidates in SHALL be preserved
within each section the results are grouped into; the system SHALL NOT re-order
candidates for any other reason.

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
- **THEN** the system queries the poster source and shows every candidate poster
  it returns, grouped into sections, with the source's order kept inside each
  section

#### Scenario: No candidate is dropped
- **WHEN** a search returns candidates
- **THEN** the number of candidates shown across all sections equals the number
  the source returned

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

## ADDED Requirements

### Requirement: Find Posters candidates are grouped by the service that supplied them
The Find Posters tab SHALL present its candidates in labelled sections, one for
each service that supplied at least one of them: TMDB, TheTVDB, and fanart.tv.

The services are not interchangeable to the people choosing between them —
fanart.tv is where textless artwork is found, TMDB where language variants are,
TheTVDB where a show's own artwork is — so which service supplied a candidate is
a distinction a user acts on, not an implementation detail.

Sections SHALL appear in the same order for every item: TMDB, then TheTVDB, then
fanart.tv. A user who learns where a service's posters sit SHALL find them in the
same place on the next item. This is the order in which the poster provider
attribution credits those same three services, and the two SHALL NOT disagree;
the section order SHALL be defined in a way that records that it follows the
attribution.

Within a section, candidates SHALL remain in the order the poster source
returned them. The source ranks across all three services at once, so the leading
candidate overall is not necessarily the first one on screen; that is the accepted
cost of a section order that does not move.

Each section's heading SHALL name its service and SHALL show how many candidates
the section holds, in the same form as the Plex Posters tab's group headings — a
section label rather than a line of prose. The tab SHALL NOT show a total across
sections; the per-section counts are the whole of what is offered.

Each section SHALL be shown only when it has candidates, so a service that
returned nothing for an item leaves no empty heading behind.

While a section's candidates are on screen, its heading SHALL remain visible as
the user scrolls, and SHALL leave with its own section.

A candidate whose supplying service is not one of the three named, or which
reports no service at all, SHALL still be shown, in a section following all the
named ones. The system SHALL NOT discard such a candidate: dropping it would be
indistinguishable from that service having no artwork, and nothing would report
it.

#### Scenario: Candidates are split by supplying service
- **WHEN** a user views Find Posters results drawn from more than one service
- **THEN** the candidates appear in separate labelled sections, each holding only
  the candidates that service supplied

#### Scenario: Section order is the same for every item
- **WHEN** a user views Find Posters results for one item and then for another
- **THEN** the sections appear in the same order both times — TMDB, then
  TheTVDB, then fanart.tv — regardless of how many candidates each holds

#### Scenario: Section order matches the provider attribution
- **WHEN** the section order is compared with the order the poster provider
  attribution credits those services in
- **THEN** the two agree

#### Scenario: Each heading names its service and counts its candidates
- **WHEN** a user views a Find Posters result with sections present
- **THEN** each heading reads as a section label that names the service and shows
  how many candidates that section holds

#### Scenario: No total across sections is shown
- **WHEN** a user views a Find Posters result
- **THEN** no combined count of all candidates is shown alongside the sections

#### Scenario: Order within a section is the source's
- **WHEN** a user views the candidates inside one section
- **THEN** they appear in the order the poster source returned them, with none
  re-ranked or re-ordered within that section

#### Scenario: A service that supplied nothing is omitted
- **WHEN** one of the named services returns no candidates for an item
- **THEN** no heading for that service is shown, and no empty section is left in
  its place

#### Scenario: A heading stays visible while its section is scrolled
- **WHEN** a user scrolls through a long section of candidates
- **THEN** that section's heading remains visible, and the candidates pass behind
  it

#### Scenario: The visible heading is always the right one
- **WHEN** a user scrolls from one section into the next
- **THEN** the heading that is showing is the one belonging to the candidates on
  screen

#### Scenario: An unrecognised service is still shown
- **WHEN** the poster source returns a candidate attributed to a service the
  system does not recognise, or with no service given
- **THEN** that candidate is still shown and can still be applied, in a section
  after every named one

#### Scenario: Applying is unchanged by grouping
- **WHEN** a user activates a candidate in any section
- **THEN** it opens in the full-screen preview and is applied through the same
  confirmation as before, whichever section it came from
