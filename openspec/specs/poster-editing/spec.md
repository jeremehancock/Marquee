# Poster Editing Specification

## Purpose

Marquee's central verb: taking one poster that already exists in the library and
doing something to it. Replacing its image from a file or URL, re-sending
Marquee's copy to Plex after Plex has drifted, pulling Plex's current artwork
back down, downloading it, or copying its URL.

Every operation acts on a single poster in place — the poster keeps its
identity, its filename, its category, and its Plex mapping. There is no path
that adds a new poster to the library; posters arrive only through
`plex-import`, and every replacement image is validated before it is allowed to
overwrite one.

The Plex write mechanism these operations rely on is `plex-export`; picking a
replacement image from an online search is `poster-sources`.
## Requirements
### Requirement: Change a poster in place
The system SHALL let a user replace an existing poster from a local file or a
URL, overwriting that poster's file. When the poster is linked to a Plex item and
Plex is configured, the system SHALL also upload the new image to Plex and lock
it.

A change submitted from a local file or a URL SHALL require an explicit
confirmation before it is performed. Until that confirmation is given the system
SHALL NOT read the file, fetch the URL, overwrite the stored poster, or contact
Plex.

#### Scenario: Change from a file replaces and pushes to Plex
- **WHEN** a user changes a Plex-linked poster by uploading a file and confirms
- **THEN** the system overwrites that poster's file and uploads it to Plex, then
  locks it

#### Scenario: Change from a URL replaces and pushes to Plex
- **WHEN** a user changes a Plex-linked poster by providing an image URL and
  confirms
- **THEN** the system fetches the image, overwrites that poster's file, uploads
  it to Plex, and locks it

#### Scenario: Change an unlinked poster updates only locally
- **WHEN** a user changes a poster that is not linked to Plex and confirms
- **THEN** the system overwrites the file and does not attempt to push to Plex

#### Scenario: Declining the confirmation changes nothing
- **WHEN** a user submits the Upload or the From URL form and then declines the
  confirmation
- **THEN** the system makes no request, leaves the stored poster and Plex
  untouched, reports nothing, and leaves the change dialog open on the tab and
  input the user was on

### Requirement: Replacement images are validated
The system SHALL accept a replacement image only when it is a JPEG, PNG, or WebP
— determined by inspecting the image data rather than trusting its name or
declared type — and is no larger than `MAX_FILE_SIZE`. A rejected image SHALL
leave the existing poster untouched.

#### Scenario: Disallowed type is rejected
- **WHEN** a user supplies a replacement whose image data is not JPEG, PNG, or
  WebP
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Oversized image is rejected
- **WHEN** a user supplies a replacement larger than `MAX_FILE_SIZE`, by file or
  by URL
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Unusable URL is rejected
- **WHEN** a user supplies a URL that is not a valid `http`/`https` address, or
  that cannot be fetched, or that returns nothing
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

### Requirement: Re-send a stored poster to Plex
The system SHALL let a user push a linked poster's currently stored image to its
Plex item and lock it, without first changing the poster. This lets a user
re-apply Marquee's copy after Plex has drifted (for example, an agent refresh).

Because the operation overwrites whatever artwork the Plex item currently holds,
the system SHALL ask the user to confirm before making any request to Plex. The
confirmation SHALL name the poster with the same title the gallery caption shows
for it and SHALL state that the artwork on Plex will be replaced and locked. If
the user declines, the system SHALL make no Plex request and change nothing.

#### Scenario: Send the stored poster to Plex
- **WHEN** a user sends a linked poster to Plex and confirms
- **THEN** the system uploads the poster's currently stored image to Plex and
  locks it, leaving the local file unchanged

#### Scenario: Sending asks before it uploads
- **WHEN** a user chooses Send to Plex for a linked poster
- **THEN** the system asks for confirmation, naming that poster and stating that
  the artwork on Plex will be replaced, before any upload is attempted

#### Scenario: Declining a send changes nothing
- **WHEN** a user chooses Send to Plex and then dismisses or cancels the
  confirmation
- **THEN** no request is made to Plex, the Plex item keeps its current artwork,
  and no outcome is reported

### Requirement: Fetch a poster from Plex
The system SHALL let a user re-pull a linked poster's current image from Plex,
replacing the local file with what Plex currently has. The fetched image SHALL
be validated like any other replacement.

Because the operation overwrites the poster Marquee is holding — which may be a
custom image that exists nowhere else — the system SHALL ask the user to confirm
before making any request to Plex. The confirmation SHALL name the poster with
the same title the gallery caption shows for it and SHALL state that the stored
poster will be overwritten. If the user declines, the system SHALL make no Plex
request and leave the local file unchanged.

#### Scenario: Fetch replaces the local poster
- **WHEN** a user fetches a linked poster from Plex and confirms
- **THEN** the system downloads the item's current Plex poster and overwrites the
  local file

#### Scenario: Fetching asks before it overwrites
- **WHEN** a user chooses Fetch from Plex for a linked poster
- **THEN** the system asks for confirmation, naming that poster and stating that
  Marquee's stored poster will be overwritten, before any download is attempted

#### Scenario: Declining a fetch leaves the poster alone
- **WHEN** a user chooses Fetch from Plex and then dismisses or cancels the
  confirmation
- **THEN** no request is made to Plex, the local file is unchanged, and the
  poster's card keeps the image it was already showing

#### Scenario: The two confirmations are distinguishable
- **WHEN** a user is shown the confirmation for Send to Plex and, separately, the
  one for Fetch from Plex
- **THEN** each names its own action and states which copy of the poster is
  overwritten, so the two cannot be mistaken for one another

#### Scenario: Fetching an unlinked poster is refused
- **WHEN** a user fetches a poster that has no Plex mapping
- **THEN** the system reports that the poster is not linked to Plex and changes
  nothing

### Requirement: Download and copy a poster
The system SHALL let a user download a poster's image and copy the poster's URL.

#### Scenario: Download a poster
- **WHEN** a user chooses to download a poster
- **THEN** the system provides the image file for download

#### Scenario: Copy a poster URL
- **WHEN** a user chooses to copy a poster's URL
- **THEN** the poster's URL is placed on the clipboard

### Requirement: A changed poster is visible immediately
After any operation that replaces a poster's image, the system SHALL present the
new image on the next page render, without requiring the user to reload the page
or clear a cache. A success message SHALL NOT be shown alongside the previous
image.

Presenting the new image SHALL NOT disturb the gallery around it: the user's
scroll position, the extent of the grid they have already loaded, and every
other poster's card SHALL be exactly as they were before the operation. Only the
changed poster's own card may be re-rendered.

#### Scenario: Changed poster appears without a reload
- **WHEN** a user changes a poster and is returned to the gallery
- **THEN** the poster shown is the new image

#### Scenario: The image URL changes with the file
- **WHEN** a poster's file is replaced
- **THEN** the URL the system renders for that poster differs from the one it
  rendered before the replacement, so a cached copy of the previous image is
  not reused

#### Scenario: Unchanged posters keep their URL
- **WHEN** a gallery is rendered twice with no poster replaced in between
- **THEN** each poster's URL is identical in both renders, so cached images stay
  usable

#### Scenario: The gallery holds its position across a change
- **WHEN** a user scrolls into the gallery and changes a poster
- **THEN** the gallery is at the same scroll position when the operation
  finishes, with the changed poster's card where it was

#### Scenario: A change does not discard posters already loaded
- **WHEN** a user has extended the grid past its first page and then changes a
  poster
- **THEN** every poster already in the grid stays in it, and none is fetched or
  rendered again

#### Scenario: A change to a poster outside the current grid still shows
- **WHEN** an operation replaces the image of a poster whose card is not present
  in the gallery currently on screen
- **THEN** the system falls back to re-rendering the gallery, so the view is
  never left showing a stale image

### Requirement: A missing linked Plex item is reported distinctly
When a Re-send or Fetch operation targets a linked poster whose Plex item no
longer exists, the system SHALL report that the item is gone and that the poster
may be orphaned, guiding the user toward the Orphans page. The system SHALL NOT
report this case as a server-connection failure, and SHALL distinguish it from a
rejected Plex token and from a genuine transport failure (unreachable server,
timeout). No additional Plex request SHALL be made to determine this — the
classification comes from the status of the request the operation already made.

#### Scenario: Re-sending an orphaned poster reports it may be orphaned
- **WHEN** a user re-sends a linked poster whose Plex item no longer exists
- **THEN** the system reports that the item no longer exists in Plex and that the
  poster may be orphaned, directing the user to the Orphans page, and does not
  report a connection failure

#### Scenario: Fetching an orphaned poster reports it may be orphaned
- **WHEN** a user fetches a linked poster whose Plex item no longer exists
- **THEN** the system reports that the item no longer exists in Plex and that the
  poster may be orphaned, directing the user to the Orphans page, and leaves the
  local file unchanged

#### Scenario: A rejected token is reported as an authentication problem
- **WHEN** a Re-send or Fetch operation fails because Plex rejects the configured
  token
- **THEN** the system reports that the Plex token was rejected, distinct from
  both an orphaned item and a general connection failure

#### Scenario: A genuine connection failure still reports a connection problem
- **WHEN** a Re-send or Fetch operation fails because the Plex server cannot be
  reached (unreachable host, refused connection, or timeout)
- **THEN** the system reports that it could not connect to the Plex server, as
  before

### Requirement: The change-poster dialog names the poster it will replace
The change-poster dialog (a modal on pointer devices, a tray on touch) SHALL name
the poster being replaced using exactly the same title the gallery caption shows
for it — the source library token dropped, the release year in parentheses. It
SHALL NOT append the source library, so the heading carries one parenthetical
(the year) rather than two, and so the same title text serves the caption, the
action sheet, and this dialog.

#### Scenario: Dialog heading matches the caption
- **WHEN** a user opens the change-poster dialog for a poster whose caption shows
  "Louis and the Nazis (2003)"
- **THEN** the dialog names it "Louis and the Nazis (2003)", with no library
  appended

#### Scenario: Poster with no known year
- **WHEN** a user opens the change-poster dialog for a poster with no stored year
  (e.g. a collection)
- **THEN** the dialog shows its title with no parenthesised year and no library

#### Scenario: Dialog title does not affect the replacement
- **WHEN** the change-poster dialog submits an upload, a URL, or a Find Posters
  selection
- **THEN** the poster is identified by its filename and category as before, and
  the displayed title has no effect on which file is replaced

### Requirement: Operations that store no new image leave the gallery alone
An operation that does not replace the poster's stored image SHALL NOT re-render
the gallery. It SHALL report its outcome and leave the grid, its scroll
position, and every card untouched.

Whether a new image was stored SHALL be decided by what actually reached disk,
not by whether the operation as a whole succeeded. A change that replaced the
poster and then failed to push it to Plex has stored a new image, and SHALL
present it like any other change.

#### Scenario: Re-sending to Plex does not re-render the gallery
- **WHEN** a user sends a stored poster to Plex
- **THEN** the result is reported and no card, count, or scroll position in the
  gallery changes

#### Scenario: A failed change leaves the gallery untouched
- **WHEN** an operation that would have replaced a poster's image fails before
  storing anything — an image that is rejected, a URL that cannot be fetched
- **THEN** the failure is reported and the poster's card keeps the image it was
  already showing

### Requirement: A change that cannot reach Plex still shows the new poster
A change replaces the poster's file before it uploads to Plex, so a failure to
reach or update the Plex item leaves a new image already stored. The system SHALL
report that outcome as distinct from both a clean success and a change that
stored nothing: it SHALL state that the poster was updated, SHALL carry the
reason the upload did not happen, and SHALL NOT present it in the styling used
for an outright failure.

The new poster SHALL be presented immediately, on the same terms as any other
change — the changed card is re-rendered in place and the rest of the gallery is
left alone — without requiring the user to reload the page.

#### Scenario: Changing an orphaned poster
- **WHEN** a user changes the poster of an item that no longer exists in Plex
- **THEN** the new poster is stored and shown in the gallery straight away, and
  the message says the poster was updated but could not be sent to Plex, naming
  the likely orphan as the reason

#### Scenario: Changing a poster while Plex is unreachable
- **WHEN** a user changes a linked poster and Plex cannot be reached or rejects
  the token
- **THEN** the new poster is stored and shown, and the message reports that it
  could not be sent to Plex along with the underlying reason

#### Scenario: A rejected image is still an outright failure
- **WHEN** a change fails before anything is stored, because the image is not a
  supported type, is too large, or the URL cannot be fetched
- **THEN** it is reported as a failure and the poster's card keeps its previous
  image

### Requirement: Changing a poster is confirmed before it runs
The change-poster dialog SHALL confirm an Upload or a From URL submission through
the same confirmation used by the gallery's other overwriting actions — a
full-screen modal on pointer devices and a tray on touch — presented over the
change dialog, which SHALL remain open behind it.

The confirmation SHALL name the poster being replaced using the same title the
dialog heading shows, SHALL state which of the two sources the replacement comes
from, and SHALL offer its action under the non-destructive emphasis reserved for
overwrites rather than the destructive one reserved for deletion. It SHALL NOT
restate what happens to a Plex-linked poster afterwards.

Dismissing the confirmation SHALL return the user to the change dialog rather
than closing both.

#### Scenario: Upload asks before it replaces
- **WHEN** a user picks an image file in the change dialog and submits
- **THEN** a confirmation naming that poster is shown, and nothing is uploaded
  until the user confirms

#### Scenario: From URL asks before it fetches
- **WHEN** a user enters an image URL in the change dialog and submits
- **THEN** a confirmation naming that poster is shown, and the URL is not fetched
  until the user confirms

#### Scenario: Confirmation is a tray on touch
- **WHEN** the confirmation is shown on a touch device, where the change dialog
  is itself presented as a tray
- **THEN** it is presented as a tray in the same style as the Send to Plex
  confirmation, above the change dialog

#### Scenario: Dismissing returns to the change dialog
- **WHEN** a user dismisses the confirmation with Escape, the close control, or
  the backdrop
- **THEN** the confirmation closes, the change dialog stays open, and no change
  is performed

### Requirement: The change dialog opens with empty inputs
The change-poster dialog SHALL present an empty file field and an empty URL field
every time it is opened, and SHALL open on the Upload tab. Input left behind by a
previous opening SHALL NOT carry over, whichever way that opening ended —
confirmed, cancelled, dismissed, or failed — and regardless of whether the dialog
is reopened for the same poster or a different one.

Clearing the inputs SHALL NOT disturb the poster the dialog is bound to: the
filename and category it will submit are set from the poster that was opened.

#### Scenario: A dismissed selection does not come back
- **WHEN** a user picks a file or types a URL, dismisses the dialog without
  changing the poster, and opens it again
- **THEN** both fields are empty and the dialog is on the Upload tab

#### Scenario: Input does not follow the dialog to another poster
- **WHEN** a user enters a URL for one poster, dismisses the dialog, and opens it
  for a different poster
- **THEN** the URL field is empty and the dialog names the newly opened poster

#### Scenario: Reopening the same poster still clears
- **WHEN** a user dismisses the dialog and reopens it for the same poster
- **THEN** both fields are empty and the form still submits that poster's
  filename and category

### Requirement: The change dialog names its own action
The change-poster dialog's Upload and From URL submit controls SHALL be labelled
"Change poster", matching the dialog's heading and the label the Find Posters
confirmation already uses, so one action has one name throughout the dialog. The
URL field SHALL be labelled "Image URL" with no parenthetical about accepted
sources.

#### Scenario: Both submit controls read "Change poster"
- **WHEN** a user opens the change-poster dialog on the Upload tab or the From
  URL tab
- **THEN** that tab's submit control reads "Change poster"

#### Scenario: URL field label carries no source parenthetical
- **WHEN** a user opens the From URL tab
- **THEN** the field is labelled "Image URL", with no "(also supports Mediux
  URLs)" suffix

#### Scenario: Labels do not change what is accepted
- **WHEN** a user submits a URL that the system accepted before this labelling
  change
- **THEN** it is accepted exactly as before; the label change is presentational
  only

