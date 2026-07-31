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

### Requirement: Preview and apply a found poster
The system SHALL let a user open any candidate full screen to inspect it before
committing, and SHALL apply a candidate only through an explicit confirmation
taken from that preview, which replaces the poster in place and, when linked and
configured, pushes it to Plex and locks it. Where the source supplies a
reduced-size preview image for a candidate, the system SHALL use it for the
candidate grid, and SHALL use the full-resolution image when inspecting a
candidate full screen and when applying it.

Applying is a two-step commitment rather than a single action on the grid: a
candidate in the grid is activated to inspect it full screen, the preview offers
to use that candidate, and using it asks for a final confirmation before the
poster is changed. The user SHALL be able to abandon the change at either step
without the poster being altered.

#### Scenario: Preview then apply
- **WHEN** a user views the found-poster results
- **THEN** they can open a candidate full screen to inspect it, and apply it
  only from that full-screen preview

#### Scenario: Found-poster action label
- **WHEN** a user is previewing a candidate full screen
- **THEN** the action that applies it is labelled "Use this poster", and the
  confirmation it asks for is labelled "Change poster"

#### Scenario: Applying requires a confirmation
- **WHEN** a user chooses to use the candidate they are previewing
- **THEN** the system asks for a final confirmation, and the poster is changed
  only once that confirmation is given

#### Scenario: Abandoning the preview leaves the poster unchanged
- **WHEN** a user closes the preview, or declines the final confirmation
- **THEN** the poster is not changed and the user is returned to the results

#### Scenario: Apply a candidate
- **WHEN** a user confirms applying a candidate poster from the results
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

The base URL of the poster search service SHALL remain configurable through the
`POSTER_SOURCE_URL` environment variable, with the same default and behavior as
before, but SHALL NOT be presented to users as a setting: it SHALL NOT appear in
the documented configuration table or in any example deployment configuration.
Find Posters is a hosted service users are not expected to repoint, so offering
the variable as a knob invites configuration that is not supported.

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

#### Scenario: The service URL is not offered as a setting
- **WHEN** a reader reviews the documented configuration table and the example
  deployment configuration
- **THEN** `POSTER_SOURCE_URL` is not listed among them

#### Scenario: The service URL still works when set
- **WHEN** `POSTER_SOURCE_URL` is set in the environment
- **THEN** poster searches are directed at that base URL, exactly as before, and
  when it is unset the default hosted service is used

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
- **THEN** the system tells the user that the title was found but no posters are
  available, which is reported differently from a title that did not match

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

The system SHALL NOT record a correction produced by a search that carried nothing
to tell works sharing a title apart. Replacing a stale identifier is safe only
because the replacement is well-founded: a known-bad identifier cannot get worse.
When the search could not distinguish between candidate works, the identifier it
matched is a guess rather than a finding, and the two outcomes are not
symmetrical — a stale identifier fails to resolve on every search, so it stays
visible and repairable, while a wrong-but-valid identifier resolves cleanly
forever and no later mismatch can reveal it. The system SHALL therefore keep the
stale identifier, which costs one wrong search result the item was already
getting, rather than record a guess, which costs the ability to ever detect the
error.

Whether a search could disambiguate SHALL be determined from what the system
itself sent, not from the source reporting that it fell back to the title. A
correction only ever arises when the identifier sent was not recognised — which is
that fallback — so treating the fallback as disqualifying would suppress every
correction rather than only the unfounded ones. A search that sends no release
year has nothing to separate works that share a title, and is the case this rule
excludes.

#### Scenario: Stale identifier replaced
- **WHEN** a search sends a recorded TMDB identifier and a release year, and the
  source matches a different identifier
- **THEN** the system records the matched identifier against that item and logs
  that it did so

#### Scenario: A correction from a search that could not disambiguate is refused
- **WHEN** a search sends a recorded TMDB identifier but no release year, and the
  source matches a different identifier
- **THEN** the system leaves the recorded identifier as it is, because the matched
  identifier could have come from any work sharing the title, and records that it
  declined

#### Scenario: A refused correction leaves the item detectable
- **WHEN** a user searches again for an item whose correction was refused
- **THEN** the search sends the same recorded identifier as before and the
  mismatch is detected again, so the stale identifier remains repairable

#### Scenario: A refused correction does not change what the user sees
- **WHEN** a search's correction is refused
- **THEN** the candidates shown are the ones the source returned, and no message
  about the refusal is shown to the user

#### Scenario: Detection survives a source that echoes what it resolved
- **WHEN** the source's response reports, as the identifier for the search, the
  identifier it resolved rather than the one it was sent — so that every
  identifier in the response agrees with every other
- **THEN** the system still detects the stale identifier and corrects it, because
  it compares against the identifier it sent rather than against any identifier
  the response reports

#### Scenario: Falling back to the title is not on its own disqualifying
- **WHEN** a search that sent both a recorded TMDB identifier and a release year
  is resolved by the source through its title fallback, because the identifier was
  not one the source recognised
- **THEN** the system still records the correction, because the fallback is the
  condition every correction arises from rather than a sign that the match was
  unfounded

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

### Requirement: What Plex knows about an item is explained to users
Search accuracy depends on whether Plex identified the work behind an item, which
is decided by the metadata agent the user's Plex library was built with and is
not visible from inside Marquee. The product documentation SHALL explain this to
users, and SHALL do so as optional troubleshooting for poor Find Posters results
— never as a setup step, a prerequisite, or a recommended configuration.

The explanation SHALL state that title-only matching is expected and correct for
items that have no upstream work to identify, so that a user whose only affected
items are of that kind is told to change nothing. It SHALL scope the suggestion
to switch a library's metadata agent to a whole library performing badly, and
SHALL state the cost of switching together with the suggestion rather than after
it.

That cost SHALL be stated in terms of which posters are protected and which are
not, and SHALL NOT be reduced to a general warning that artwork may change. A
poster the system has uploaded to Plex is locked and survives a metadata
refresh; a poster the system only imported was never uploaded, so it was never
locked, and Plex may replace it. A user who has read that the system locks
posters will otherwise conclude the whole library is protected, which is false
for every item they have not applied a poster to.

The explanation SHALL also give the recovery path — the stored poster can be
sent to Plex again — together with its ordering constraint: that this must
happen before the next import, because importing replaces the system's stored
poster with the artwork Plex holds at that moment. Import is what a user reaches
for when posters look wrong, so omitting the ordering turns a recoverable loss
into a permanent one.

The explanation SHALL describe the metadata agent setting in general terms only.
It SHALL NOT name Plex's menus, screens, or setting labels, or give a click path,
because those change without anything here failing. It SHALL NOT describe how
Marquee obtains a work's identifier from Plex, in what form Plex reports it, or
which agents report it — that is implementation detail the reader cannot act on.

#### Scenario: README explains poor results
- **WHEN** a reader whose Find Posters results are thin or wrong consults the
  README
- **THEN** they find that accuracy depends on Plex having identified the item,
  and what they can do about it

#### Scenario: Framed as optional
- **WHEN** a reader follows the README's setup or usage instructions
- **THEN** nothing there asks them to inspect or change a Plex metadata agent,
  and the explanation is reachable only as troubleshooting

#### Scenario: A satisfied user is told to do nothing
- **WHEN** a reader whose Find Posters results are already good reads the
  explanation
- **THEN** it tells them there is nothing they need to do

#### Scenario: Expected cases are separated from fixable ones
- **WHEN** the documentation describes why a search fell back to the title
- **THEN** it states that collections, personal media, and items Plex has not
  matched are expected to search by title and are not a fault to fix

#### Scenario: The cost of switching agents is stated with the suggestion
- **WHEN** the documentation suggests switching a library's metadata agent
- **THEN** the re-scan it triggers, and what that re-scan can change, are stated
  in the same place as the suggestion

#### Scenario: Locked and unlocked posters are distinguished
- **WHEN** the documentation warns that a metadata refresh can change artwork
- **THEN** it states that posters applied through the system are locked in Plex
  and stay, and that a poster the system only imported is not locked and can be
  replaced
- **AND** it does not describe the risk only in general terms that leave a user
  who has read about poster locking believing every item is protected

#### Scenario: The recovery path and its ordering are given
- **WHEN** the documentation warns that a poster may be replaced in Plex
- **THEN** it states that the system's stored poster can be sent to Plex again
- **AND** that this must be done before the next import, because importing
  replaces the stored poster with the artwork Plex holds at that moment

#### Scenario: No Plex navigation is published
- **WHEN** the documentation refers to the metadata agent setting
- **THEN** it identifies the setting in general terms without naming Plex's
  menus or setting labels and without giving a click path

#### Scenario: No implementation detail is published
- **WHEN** the documentation explains why some items search by title
- **THEN** it does not describe how Marquee obtains an identifier from Plex, the
  form Plex reports it in, or which agents report it

### Requirement: Candidate images load progressively
The candidate grid SHALL present each candidate's image the way the poster
gallery presents a poster card: the cell SHALL reserve the candidate's space at
poster proportions before the image arrives, SHALL show the same subtle
placeholder animation while it has not resolved, and SHALL fade the image in once
it does. The placeholder SHALL stop whether the image loads or fails.

Candidate images SHALL be deferred until they are at or near the visible part of
the results, so opening Find Posters does not fetch every candidate's image at
once. Deferral SHALL be relative to the region the results actually scroll in,
not only the page as a whole, because the results scroll within their own
container.

The full-screen candidate preview SHALL show the placeholder while the
full-resolution image loads and fade the image in once it resolves. Nothing in the
preview SHALL move while the image is loading: neither the action bar, so the
controls do not shift when the poster appears, nor the placeholder itself, which
SHALL hold its position for the whole wait rather than being displaced by the
image loading beside it. As with the library's full-screen view, one
preview is reused for every candidate, so previewing a different candidate SHALL
return the preview to its unresolved state rather than showing the previously
previewed candidate.

#### Scenario: Candidate grid before images arrive
- **WHEN** the found-poster results are shown and their images have not yet
  resolved
- **THEN** each candidate's cell holds its space at poster proportions and shows
  the placeholder animation, and each image fades in as it resolves

#### Scenario: Candidate image fails to load
- **WHEN** a candidate's grid image fails to load
- **THEN** that cell's placeholder animation stops rather than continuing to
  suggest the image is still loading

#### Scenario: Off-screen candidates are not fetched up front
- **WHEN** the results contain more candidates than fit in the visible results
  area
- **THEN** the images for candidates well outside that area are not fetched until
  scrolling brings them near it

#### Scenario: Preview before the full-resolution image arrives
- **WHEN** a user opens a candidate full screen and its full-resolution image has
  not yet resolved
- **THEN** the placeholder animation is shown where the poster will appear and
  stays there for the whole wait, the action bar stays in the position it will
  hold once the poster is shown, and the poster fades in once it resolves

#### Scenario: Previewing a second candidate
- **WHEN** a user previews one candidate, closes the preview, and previews a
  different candidate
- **THEN** the preview starts unresolved again and never shows the previously
  previewed candidate

### Requirement: Applying a found poster indicates progress and runs once
Applying a found poster is never fast: the system fetches the full-resolution
image from a third-party source and, when the poster is linked and Plex is
configured, uploads it to Plex and locks it. From the moment the user confirms
the change until it succeeds or fails, the system SHALL indicate that the change
is running, and SHALL prevent a second change from being started from the same
confirmation.

The indication SHALL be shown immediately on confirmation, without the grace
period that defers the gallery's loading indication for in-place view changes.
That deferral exists so a view change which may resolve from cache does not
produce a visible flicker; applying a found poster has no comparable fast path,
so deferring would only prolong the silence this requirement exists to remove.

The indication SHALL cover the full-screen candidate preview, because that is
what the user is looking at when they confirm and it sits above the
change-poster dialog.

The confirmation control SHALL be disabled while the change is in flight, and
a repeated activation SHALL be ignored even if it is registered before the
indication is displayed, so that the disabled state communicates and does not
have to be relied upon to enforce.

The indication SHALL be cleared when the change fails as well as when it
succeeds, leaving the preview usable rather than stranded, and a failure SHALL
be reported to the user.

#### Scenario: Progress is shown while the change runs
- **WHEN** a user confirms applying a found poster and the change has not yet
  completed
- **THEN** the system indicates that the change is running, over the full-screen
  candidate preview

#### Scenario: Progress is shown without delay
- **WHEN** a user confirms applying a found poster
- **THEN** the indication appears immediately rather than after a grace period

#### Scenario: The change cannot be started twice
- **WHEN** a user activates the confirmation control a second time while a
  change is already in flight
- **THEN** no second change is started, and the poster is fetched and uploaded
  to Plex only once

#### Scenario: Progress clears when the change succeeds
- **WHEN** an applied change completes successfully
- **THEN** the indication is cleared, the preview and the change-poster dialog
  are closed, the result is reported, and the gallery is refreshed

#### Scenario: Progress clears when the change fails
- **WHEN** an applied change fails
- **THEN** the indication is cleared, the failure is reported to the user, and
  the preview remains usable so the user can retry or choose another candidate

#### Scenario: A failed response is reported as a failure
- **WHEN** the request to apply a candidate returns an unsuccessful response
- **THEN** the system reports the change as having failed rather than treating
  the response as a successful change

