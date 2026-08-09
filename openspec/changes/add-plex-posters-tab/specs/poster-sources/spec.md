## ADDED Requirements

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

### Requirement: Only posters held on the Plex server are listed
Plex answers for an item's posters with two unlike things: images stored on the
server itself, and remote provider URLs the server is merely offering. The tab
SHALL list only the first kind.

A poster is held on the server when Plex gives it a server-relative path; a
remote offering is given an absolute URL to another host. That single property
SHALL decide inclusion, because it is also what the image proxy can serve — a
candidate this tab shows is by construction one the application can fetch with
the server's own credentials, and a candidate it excludes is one the proxy would
have refused anyway.

Excluding the remote offerings is what keeps this tab distinct. They are TMDB,
fanart.tv, and TheTVDB artwork — the same providers Find Posters already
aggregates and ranks — so listing them here would duplicate the neighbouring tab
while burying the posters that exist nowhere else. What only this tab can show
is what is already on the user's server.

#### Scenario: Server-held posters are listed
- **WHEN** Plex reports posters for an item that are stored on the server
- **THEN** they are listed as candidates

#### Scenario: Remote provider URLs are excluded
- **WHEN** Plex's answer includes posters addressed as absolute URLs to
  providers such as TMDB, fanart.tv, or TheTVDB
- **THEN** those are not listed, and Find Posters remains the way to reach
  provider artwork

#### Scenario: An item offering only remote artwork
- **WHEN** every poster Plex reports for an item is a remote provider URL
- **THEN** the tab reports that Plex has no posters for this item, rather than
  showing an empty grid

### Requirement: Plex poster candidates are grouped by where they came from
The Plex Posters tab SHALL present its candidates in two labelled groups:
posters that were **uploaded** to the item, first, then the other posters held
on the server. Plex marks an uploaded poster distinctly, and the system SHALL
use that marking rather than inferring it from the image itself.

The order is not cosmetic. Plex never removes a poster from an item, so the
list only grows. The uploaded group is the user's own history — every poster
they ever applied to that item, including ones no longer stored in Marquee and
ones no poster search will surface again. That group is the reason this tab
exists, so it SHALL come first.

The second group SHALL NOT be described as coming from a metadata agent. It
holds posters of several kinds — artwork an agent downloaded, a poster file
found alongside the media, an image embedded in the media itself — and naming
one of them would misdescribe the others.

Each group SHALL be shown only when it has candidates, so an item with no
uploads does not display an empty heading.

#### Scenario: Uploaded posters come first
- **WHEN** the Plex Posters tab lists an item that has both uploaded posters and
  other server-held posters
- **THEN** the uploaded posters appear first, under their own heading, and the
  rest follow under their own heading

#### Scenario: Groups are labelled
- **WHEN** a user views the Plex Posters results
- **THEN** each group names what it contains, so a poster the user applied
  themselves is distinguishable from one that arrived another way

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

The system SHALL do this by **selecting** the poster Plex already holds, and
SHALL NOT upload it back. Plex never removes a poster from an item, so uploading
one it already has would leave a second, identical copy — and applying the
poster Plex has currently selected would duplicate it against itself. Locking is
a separate operation on the item and applies equally either way.

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

#### Scenario: Applying never adds a copy to Plex
- **WHEN** a user applies any Plex-held candidate, including the one Plex
  currently has selected
- **THEN** no poster is uploaded to the item, so its poster list is no longer
  than it was

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
