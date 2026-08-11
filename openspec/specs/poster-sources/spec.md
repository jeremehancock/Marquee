# Poster Sources Specification

## Purpose

Offering a user candidate posters for a media item, and applying the one they
pick. Two sources, reached from their own tabs in the change-poster dialog:

- **Plex Posters** — everything the connected Plex server reports for the item.
  Addressed by the rating key recorded at import, so there is no matching step
  and nothing to get wrong. It is the only way to reach a poster the user
  applied in the past and no longer has, because Plex keeps every poster ever
  uploaded to an item and never prunes them.
- **Find Posters** — the posteria.app API, a hosted service that aggregates
  candidates from TMDB, fanart.tv, and TheTVDB. It resolves a *title* to a work,
  so it can fail at that step in ways a rating key cannot.

The poster search service is an external dependency, not part of this
repository. Both sources are best-effort: when either is unreachable or returns
nothing, the user's poster is left exactly as it was.
## Requirements
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

This full-screen preview is the same one the change dialog's Upload and From URL
tabs use for their replacements, and it behaves identically for all three:
abandoning it SHALL leave the change dialog it was opened from standing, so that
a dismissal never discards what the user supplied on another tab.

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

#### Scenario: Escape closes only the preview
- **WHEN** a user presses Escape while previewing a candidate
- **THEN** the preview closes and the change dialog behind it stays open on the
  Find Posters results

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

### Requirement: Plex Posters is a distinct source in the change dialog
The change-poster dialog SHALL offer a **Plex Posters** tab that lists the
posters the connected Plex server already holds for the poster's linked item.
It SHALL appear between **From URL** and **Find Posters**, so the dialog's tabs
read from the most local source to the most remote: the user's own device, an
address they supply, their own Plex server, then a hosted search.

Plex Posters SHALL be a source of its own rather than results folded into Find
Posters. The two answer different questions. Find Posters resolves a *title* to
a *work* and can fail at that step — no match, a rate limit, a stale identifier
it corrects. A Plex lookup is addressed by the item's rating key, which is
already recorded, so it has no matching step and none of those failures. Merging
them would put outcomes in front of the user that cannot arise, and would hide
the property that makes this source worth having: these posters are already on
their server.

Candidates from this tab SHALL NOT be attributed to TMDB, fanart.tv, or
TheTVDB. Plex reports where it obtained a poster, and that is not the same fact
as which provider a poster search matched.

#### Scenario: The tab is offered
- **WHEN** a user opens the change-poster dialog for a poster linked to a Plex
  item
- **THEN** a Plex Posters tab is available alongside Upload, From URL, and Find
  Posters

#### Scenario: Tab order
- **WHEN** the change-poster dialog is rendered
- **THEN** the tabs read Upload, From URL, Plex Posters, Find Posters, in that
  order

#### Scenario: Posters come from the linked item
- **WHEN** a user opens the Plex Posters tab for a poster
- **THEN** the candidates shown are the posters Plex holds for that poster's own
  linked item, and no title search is performed

#### Scenario: Find Posters is unaffected
- **WHEN** a user opens the Find Posters tab
- **THEN** it searches and reports exactly as it did before, with no Plex-held
  posters mixed into its results

### Requirement: Posters are distinguished by whether Plex holds them
Plex answers for an item's posters with two unlike things: images stored on the
server itself, given a server-relative path; and remote provider artwork the
server only knows about, given an absolute URL to another host. The system SHALL
list both and SHALL distinguish them, because that single property decides
everything downstream.

A held poster SHALL be shown through the application's image proxy and applied
by selecting it. An offered poster SHALL be loaded from its own source and
applied exactly as a pasted address is — there is nothing on the Plex server to
address and no Plex credential involved.

The same property SHALL decide both the classification and what the image proxy
will serve, so a candidate classified as held is by construction one the proxy
can fetch and an offered one is by construction one it would refuse. The two
cannot then drift apart.

An offered candidate SHALL be an ordinary web address. Anything else Plex
reports SHALL be dropped rather than placed in the page as an image source.

#### Scenario: Held posters are listed and proxied
- **WHEN** Plex reports posters for an item that are stored on the server
- **THEN** they are listed, and their images are served through the application
  rather than addressed directly

#### Scenario: Offered posters are listed and loaded directly
- **WHEN** Plex's answer includes artwork it has not downloaded, addressed as an
  absolute URL to a provider such as TMDB, fanart.tv, or TheTVDB
- **THEN** it is listed, and its image is loaded from that address rather than
  through the proxy

#### Scenario: An item that holds nothing but is offered artwork
- **WHEN** every poster Plex reports for an item is a remote provider URL
- **THEN** those are shown, rather than the tab reporting that Plex has no
  posters

#### Scenario: An offered address that is not a web address is dropped
- **WHEN** Plex reports a poster whose address uses neither `http` nor `https`
- **THEN** it is not listed, and nothing puts that address into the page

### Requirement: Plex poster candidates are grouped by whether the user put them there
The Plex Posters tab SHALL present its candidates in two labelled groups:
posters **uploaded** to the item, then everything else Plex reports for it. Plex
marks an uploaded poster distinctly, and the system SHALL use that marking
rather than inferring it from the image itself.

The uploaded group SHALL come first. Plex never removes a poster from an item,
so the list only grows, and this group is the user's own history — every poster
they ever applied to that item, including ones no longer stored in Marquee and
ones no poster search will surface again. That is the reason this tab exists, so
it must not be buried under the much larger second group.

Whether Plex already holds a poster or would have to fetch it SHALL NOT be
surfaced as a grouping. It decides how applying works and nothing else; a user is
choosing a poster, not a mechanism, and splitting the list on it asks them to
care about a distinction that changes nothing they are deciding. The two are
also not distinguishable by source — most of what Plex has downloaded for an
item came from the same providers it would otherwise offer — so a split would
suggest a difference in origin that does not exist.

Within the second group, candidates Plex already holds SHALL be ordered before
those it does not, so the posters that need no upload are reached first.

Each group's heading SHALL be presented as a section label rather than as
body text, and SHALL carry the number of candidates in it, so a user can see how
much history an item has accumulated without counting.

Groups SHALL be separated by enough space to read as distinct sections, and each
heading SHALL sit closer to the group it labels than to the one above it, so
proximity does the grouping rather than the label having to.

While a group's candidates are on screen, its heading SHALL remain visible as
the user scrolls, and SHALL leave with its own group. The offered group in
particular can run to dozens of candidates, and the heading is the only thing
saying which kind of poster is being looked at — scrolling past it would take
that answer away exactly when the list is long enough to need it.

#### Scenario: Groups are visibly separated
- **WHEN** a user views a result with more than one group
- **THEN** the space between two groups is clearly greater than the space
  between a heading and its own candidates

#### Scenario: A heading stays visible while its group is scrolled
- **WHEN** a user scrolls through a long group of candidates
- **THEN** that group's heading remains visible, and the candidates pass behind
  it rather than over it

#### Scenario: The visible heading is always the right one
- **WHEN** a user scrolls from one group into the next
- **THEN** the heading on screen is the one belonging to the candidates on
  screen

Each group SHALL be shown only when it has candidates, so an item with no
uploads does not display an empty heading.

#### Scenario: Uploaded posters come first
- **WHEN** the Plex Posters tab lists an item that has uploaded posters, other
  held posters, and offered artwork
- **THEN** the uploads appear first under their own heading, and the rest follow
  in a single group under one heading

#### Scenario: Held and offered artwork are not split apart
- **WHEN** a user views a result containing both posters Plex holds and artwork
  it only offers
- **THEN** they appear together in one group, with nothing marking which is
  which

#### Scenario: Held candidates lead the combined group
- **WHEN** the combined group contains both kinds
- **THEN** the ones Plex already holds are ordered first

#### Scenario: Groups are labelled
- **WHEN** a user views the Plex Posters results
- **THEN** each group names what it contains, so a poster the user applied
  themselves is distinguishable from one that arrived another way

#### Scenario: Headings read as structure
- **WHEN** a user views a Plex Posters result with both groups present
- **THEN** each heading is presented distinctly from the surrounding body text
  and shows how many candidates its group holds

#### Scenario: A group with no candidates is omitted
- **WHEN** an item has other server-held posters but nothing was ever uploaded
  to it
- **THEN** only the second group is shown, with no empty uploaded heading above
  it

#### Scenario: Recovering a poster that is no longer stored locally
- **WHEN** a user opens the Plex Posters tab for an item whose poster they
  previously changed
- **THEN** the poster they had before is listed among the uploaded group, and
  can be applied again

### Requirement: The poster Plex currently uses is marked
The Plex Posters tab SHALL mark the candidate that Plex has selected for the
item, using the selection Plex itself reports. Without it every candidate looks
equally current, and a user cannot tell which one they are about to move away
from — a list of near-identical posters is exactly where that matters most.

The marking SHALL be a visible indication on the candidate itself, and SHALL NOT
prevent that candidate from being previewed or applied.

#### Scenario: The selected candidate is indicated
- **WHEN** the Plex Posters tab lists candidates and Plex reports one of them as
  selected
- **THEN** that candidate is visibly marked as the one Plex is currently using

#### Scenario: The selected candidate remains usable
- **WHEN** a user activates the candidate marked as currently selected
- **THEN** it previews and applies like any other candidate

#### Scenario: Plex reports no selection
- **WHEN** Plex reports no selected poster for the item
- **THEN** the candidates are listed with none marked, rather than an arbitrary
  one being marked

### Requirement: Plex candidate images are served through the application
Plex image URLs carry the Plex token. The system SHALL NOT place a Plex image
URL in the page, and SHALL serve every Plex poster candidate — both the grid
image and the full-resolution image used for preview — through the application
itself, so the token stays on the server.

Each candidate SHALL be addressed by an opaque token that carries its Plex image
path signed by the application, and the proxy SHALL serve only paths whose
signature it can verify. An unsigned or tampered token SHALL be refused. Without
that check the proxy would fetch any path a caller supplied, turning it into an
open relay to the Plex server for anyone who can reach the application.

Proxied candidate images SHALL require an authenticated session, like every
other poster image the application serves.

#### Scenario: No Plex URL reaches the browser
- **WHEN** the Plex Posters tab renders its candidates
- **THEN** no image address in the page contains the Plex token or points
  directly at the Plex server

#### Scenario: A tampered token is refused
- **WHEN** a request for a proxied candidate image carries a token whose
  signature does not verify
- **THEN** the application refuses it rather than fetching the path it names

#### Scenario: An arbitrary path is refused
- **WHEN** a caller requests a proxied candidate image for a Plex path the
  application never signed
- **THEN** the request is refused, and no request is made to Plex on its behalf

#### Scenario: Proxied images require a session
- **WHEN** an unauthenticated caller requests a proxied candidate image
- **THEN** it is refused, as other poster images are

### Requirement: Apply a poster held by Plex
A user SHALL apply a Plex-held candidate through the same two-step commitment
the dialog's other tabs use: the candidate is activated to inspect it full
screen, the preview offers to use it, and using it asks for a final confirmation
before anything changes. Abandoning either step SHALL leave the poster
unchanged.

Applying SHALL replace the stored poster with the full-resolution image and,
when the poster is linked and Plex is configured, make that poster the item's
poster in Plex and lock it. A poster the user has deliberately chosen SHALL be
protected from a later metadata refresh regardless of which tab they chose it
from.

For a poster Plex **holds**, the system SHALL do this by **selecting** it, and
SHALL NOT upload it back. Plex never removes a poster from an item, so uploading
one it already has would leave a second, identical copy — and applying the
poster Plex has currently selected would duplicate it against itself. Locking is
a separate operation on the item and applies equally either way.

For a poster Plex only **offers**, the system SHALL fetch it and upload it, as
it does for any other address. Plex does not have that image, so there is
nothing to select; this is the difference between offering a poster and holding
one rather than an inconsistency between the groups.

The image SHALL be fetched from Plex by the application rather than by the
browser, so applying does not depend on the proxied grid image and never needs
the token client-side.

The item's posters SHALL be re-read when applying, and the chosen poster located
again, rather than the request being trusted to describe what Plex still holds.
A dialog may be left open indefinitely, and a poster removed from Plex in the
meantime SHALL fail with a message saying so, leaving the stored poster
unchanged.

#### Scenario: Preview then apply
- **WHEN** a user activates a candidate in the Plex Posters grid
- **THEN** it opens in the same full-screen preview the other tabs use, offering
  to use that candidate

#### Scenario: Applying requires a confirmation
- **WHEN** a user chooses to use the Plex candidate they are previewing
- **THEN** the system asks for a final confirmation, and the poster is changed
  only once that confirmation is given

#### Scenario: Abandoning leaves the poster unchanged
- **WHEN** a user closes the preview, or declines the final confirmation
- **THEN** the poster is not changed and the user is returned to the Plex
  Posters results

#### Scenario: Applied poster is stored and selected
- **WHEN** a user confirms applying a Plex-held candidate for a linked poster
- **THEN** the system stores that image as the poster, makes it the item's
  poster in Plex, and locks it

#### Scenario: Applying a held poster never adds a copy to Plex
- **WHEN** a user applies any Plex-held candidate, including the one Plex
  currently has selected
- **THEN** no poster is uploaded to the item, so its poster list is no longer
  than it was

#### Scenario: Applying offered artwork
- **WHEN** a user applies a candidate from the offered group
- **THEN** the system fetches that image, stores it, and uploads it to Plex,
  locking it — as it would for an address the user pasted

#### Scenario: The chosen poster is gone by the time it is applied
- **WHEN** a user applies a candidate that Plex no longer holds for the item,
  from a dialog opened before it was removed
- **THEN** the system reports that Plex no longer has that poster and the stored
  poster is unchanged

#### Scenario: Escape closes only the preview
- **WHEN** a user presses Escape while previewing a Plex candidate
- **THEN** the preview closes and the change dialog stays open on the Plex
  Posters results

### Requirement: Plex Posters outcomes are distinguishable
The system SHALL distinguish the reasons the Plex Posters tab produced no
candidates, and SHALL report each in terms that say whether the situation is
final or worth retrying. In every case the user's existing poster SHALL be left
unchanged.

There are two, not the five a title search can produce. A rating key either
resolves or the server cannot be reached; it cannot fail to match, cannot be
rate limited, and cannot correct a stale identifier. The tab SHALL NOT report
outcomes that cannot arise from it.

#### Scenario: Plex holds no posters for the item
- **WHEN** Plex returns no posters for the linked item
- **THEN** the user is told Plex has no posters for this item, in terms that do
  not suggest retrying will help

#### Scenario: Plex cannot be reached
- **WHEN** the request for the item's posters fails because the Plex server is
  unreachable or rejects the request
- **THEN** the user is told so, and that trying again shortly may work

#### Scenario: Failure leaves the poster untouched
- **WHEN** listing Plex posters fails for any reason
- **THEN** the user's existing poster is unchanged

#### Scenario: Loading is indicated
- **WHEN** the item's posters are being retrieved
- **THEN** the tab indicates that it is loading, as Find Posters does

### Requirement: The tab explains itself when a poster has no Plex item
A poster with no linked Plex item has no posters to list. The Plex Posters tab
SHALL still be shown for such a poster, disabled, with an explanation that it is
not linked to a Plex item — rather than being hidden.

Hiding it would make the tab strip change shape from one poster to the next, so
a user who learned where the tab sits would find it missing with no reason
given. A disabled tab that says why is steadier and answers the question the
absence would raise.

#### Scenario: Unlinked poster
- **WHEN** a user opens the change-poster dialog for a poster with no linked
  Plex item
- **THEN** the Plex Posters tab is present but disabled, and states that the
  poster is not linked to a Plex item

#### Scenario: The tab strip keeps its shape
- **WHEN** a user opens the dialog for a linked poster and then for an unlinked
  one
- **THEN** both show the same four tabs in the same positions

#### Scenario: A disabled tab cannot be opened
- **WHEN** a user activates the disabled Plex Posters tab
- **THEN** no request is made to Plex and the dialog stays on the tab it was on

### Requirement: Find Posters candidates are grouped by the service that supplied them
The Find Posters tab SHALL present its candidates in labelled sections, one for
each service that supplied at least one of them: TMDB, TVDB, and fanart.tv.

The services are not interchangeable to the people choosing between them —
fanart.tv is where textless artwork is found, TMDB where language variants are,
TVDB where a show's own artwork is — so which service supplied a candidate is
a distinction a user acts on, not an implementation detail.

The TVDB section is TheTVDB. Section headings are presented in upper case, and
the service's own camel-cased spelling does not survive that — "THETVDB" reads
as a run of letters. The shorter form is used in the heading for that reason
only. The provider attribution SHALL continue to credit the service by its own
name, which it does as a logo, so the two forms never meet as words on screen.

Sections SHALL appear in the same order for every item: TMDB, then TVDB, then
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
  TVDB, then fanart.tv — regardless of how many candidates each holds

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

