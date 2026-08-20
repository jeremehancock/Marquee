## MODIFIED Requirements

### Requirement: Find Posters candidates are grouped by the service that supplied them
The Find Posters tab SHALL present its candidates in labelled sections, one for
each service that supplied at least one of them: TMDB, TVDB, fanart.tv, and
TVmaze.

The services are not interchangeable to the people choosing between them —
fanart.tv is where textless artwork is found, TMDB where language variants are,
TVDB where a show's own artwork is, TVmaze where a season's own artwork is — so
which service supplied a candidate is a distinction a user acts on, not an
implementation detail.

The TVDB section is TheTVDB. Section headings are presented in upper case, and
the service's own camel-cased spelling does not survive that — "THETVDB" reads
as a run of letters. The shorter form is used in the heading for that reason
only. The provider attribution SHALL continue to credit the service by its own
name, which it does as a logo, so the two forms never meet as words on screen.
TVmaze needs no such shortening: upper-cased it is one word, still legible as
the service's name.

Sections SHALL appear in the same order for every item: TMDB, then TVDB, then
fanart.tv, then TVmaze. A user who learns where a service's posters sit SHALL
find them in the same place on the next item. This is the order in which the
poster provider attribution credits those same four services, and the two SHALL
NOT disagree; the section order SHALL be defined in a way that records that it
follows the attribution.

Within a section, candidates SHALL remain in the order the poster source
returned them. The source ranks across all four services at once, so the leading
candidate overall is not necessarily the first one on screen; that is the accepted
cost of a section order that does not move.

Each section's heading SHALL name its service and SHALL show how many candidates
the section holds, in the same form as the Plex Posters tab's group headings — a
section label rather than a line of prose. The tab SHALL NOT show a total across
sections; the per-section counts are the whole of what is offered.

Each section SHALL be shown only when it has candidates, so a service that
returned nothing for an item leaves no empty heading behind. TVmaze covers
television only, so on a movie or a collection its section is simply absent,
which is the ordinary case of this rule rather than a special one.

While a section's candidates are on screen, its heading SHALL remain visible as
the user scrolls, and SHALL leave with its own section.

A candidate whose supplying service is not one of the four named, or which
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
  TVDB, then fanart.tv, then TVmaze — regardless of how many candidates each
  holds

#### Scenario: Section order matches the provider attribution
- **WHEN** the section order is compared with the order the poster provider
  attribution credits those services in
- **THEN** the two agree

#### Scenario: TVmaze supplies a section for a show
- **WHEN** a user views Find Posters results for a show or a season and TVmaze
  supplied candidates for it
- **THEN** those candidates appear under their own TVmaze heading, after every
  other named section

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

## ADDED Requirements

### Requirement: A candidate marked as requiring attribution is always visibly credited
The poster source marks the candidates whose supplying service's licence requires
a link back to that service. Wherever such a candidate is displayed, Marquee
SHALL offer a visible control that opens that candidate's source page in a new
browsing context.

This is an obligation, not a product decision. Some of the artwork the source
returns is licensed on terms satisfied only by linking back to the service from
where the image is shown. A credit a user cannot see or cannot activate — a
tooltip, or a mention on some other screen — does not discharge it for the image
in front of them. If the link cannot be offered for a marked candidate, its
artwork SHALL NOT be shown at all.

The obligation attaches to **the marking**, and SHALL NOT be conditioned on
anything else. In particular it SHALL NOT be conditioned on the candidate merely
carrying a source address — most candidates do, and most are unmarked — nor on
which service supplied it. The poster source decides which of its services are
licensed this way; a service added later under the same licence SHALL be credited
with no change to Marquee.

The control for a marked candidate SHALL be distinguishable in the system from
any link offered for other reasons, so that changing how Marquee presents
optional links cannot remove a required one.

The link SHALL NOT take over the candidate's own action. Activating the candidate
SHALL still open the full-screen preview exactly as it does for an unmarked one,
and the link SHALL be reachable by keyboard.

#### Scenario: A marked candidate is credited in the results grid
- **WHEN** a user views Find Posters results containing a candidate the poster
  source marked as requiring attribution
- **THEN** that candidate shows a control that opens its source page in a new
  browsing context, leaving Marquee's page in place

#### Scenario: A marked candidate is credited in the full-screen preview too
- **WHEN** a user opens the full-screen preview of a marked candidate
- **THEN** the preview offers a control that opens that candidate's source page
  in a new browsing context

#### Scenario: Carrying a source address does not by itself require a credit
- **WHEN** a user views a candidate that carries a source address but was not
  marked as requiring attribution
- **THEN** no required-attribution control is shown for it, and no disabled or
  empty control is left in its place

#### Scenario: The credit never blocks choosing the poster
- **WHEN** a user activates a marked candidate
- **THEN** the full-screen preview opens as it does for any other candidate, and
  the link opens only when the link itself is activated

#### Scenario: Crediting does not depend on the service's name
- **WHEN** the poster source returns a marked candidate from a service Marquee
  does not recognise
- **THEN** the candidate is still credited, because the marking alone determines
  that a credit is owed

### Requirement: A candidate's source page may be offered as provenance
The poster source reports, for most candidates, the address of the supplying
service's own page for the work. Marquee MAY offer a control that opens that
address, for candidates whether marked as requiring attribution or not, wherever
doing so helps a user judge where a poster came from.

Where such a control is offered for an **unmarked** candidate, its wording SHALL
read as plain provenance — naming the service and what the link opens — and SHALL
NOT state or imply that a licence requires it. Marquee SHALL NOT assert a licence
condition about artwork whose licence makes none.

Offering this control SHALL NOT be relied on to satisfy the attribution
obligation for a marked candidate. That a marked candidate also carries a source
address is a property of the data and not a guarantee of the system; removing or
relocating the provenance control SHALL leave the required credit in place.

A candidate carrying no source address SHALL show no such control, and SHALL
leave no empty space or inert control where one would be. A candidate without an
address is valid: a service with no resolvable page for a work omits it rather
than guessing.

#### Scenario: An unmarked candidate can still show where it came from
- **WHEN** a user opens the full-screen preview of a candidate that carries a
  source address but is not marked as requiring attribution
- **THEN** a control is offered that opens that address in a new browsing context

#### Scenario: Provenance wording makes no licence claim
- **WHEN** a provenance control is shown for an unmarked candidate
- **THEN** its wording names the service and what the link opens, and states
  nothing about attribution, licensing, or a requirement

#### Scenario: A candidate with no source address shows no control
- **WHEN** the poster source returns a candidate with no source address
- **THEN** no link control is shown for it, and no disabled or empty control is
  left in its place

#### Scenario: A season's link is the page the source gave for that candidate
- **WHEN** a user views a candidate for a season, and one for that season's show
- **THEN** each link opens the page the poster source gave for that candidate,
  rather than one address standing in for both

#### Scenario: Two candidates for one work may link to different pages
- **WHEN** a season's results include candidates from a service that publishes a
  season page and from one that publishes only a series page
- **THEN** each candidate links to the page its own service gave, and the
  difference is presented as-is rather than reconciled, suppressed, or reported
  as an error

### Requirement: A service that does not cover a media type is not reported as a failure
The poster source names, on every result, the state of each service it consulted.
A service reporting that it holds no data for the searched work SHALL NOT be
presented to the user as an error or as a warning, and SHALL NOT cause the result
to be treated as incomplete.

Some services cover only part of the library. TVmaze is television only and
reports no data for every movie and every collection, which is the expected
answer for that search rather than a fault in the service or in the request. A
user searching a movie SHALL see the same clean result they saw before that
service existed.

This SHALL hold without Marquee enumerating the services. The result is treated
as incomplete only when the poster source itself says so, so a service added
later that does not cover a media type needs no change here.

#### Scenario: A television-only service on a movie search is silent
- **WHEN** a user searches Find Posters for a movie or a collection and a
  television-only service reports that it holds no data
- **THEN** the results are shown with no error and no warning about that service

#### Scenario: The source's own report of an incomplete result still shows
- **WHEN** the poster source reports that a result is incomplete because a
  service it consulted failed
- **THEN** the user is still told that some candidates may be missing, alongside
  the candidates that were found

#### Scenario: An unfamiliar service state is not an error
- **WHEN** the poster source reports a state for a service that Marquee has no
  specific handling for
- **THEN** the result is presented on what the source said about the result as a
  whole, not on that state
