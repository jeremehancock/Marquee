## MODIFIED Requirements

### Requirement: Change a poster in place
The system SHALL let a user replace an existing poster from a local file or a
URL, overwriting that poster's file. When the poster is linked to a Plex item and
Plex is configured, the system SHALL also upload the new image to Plex and lock
it.

A change submitted from a local file or a URL SHALL require an explicit
confirmation before it is performed. Until that confirmation is given the system
SHALL NOT transmit the file, fetch the URL, overwrite the stored poster, or
contact Plex — including while the replacement is being previewed.

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
- **WHEN** a user submits the Upload or the From URL form and then abandons the
  preview it opens, at either of its two steps
- **THEN** the system makes no request, leaves the stored poster and Plex
  untouched, reports nothing, and leaves the change dialog open on the tab and
  input the user was on

#### Scenario: Previewing a replacement sends nothing
- **WHEN** a user picks a file or enters a URL and reaches the full-screen
  preview without confirming
- **THEN** the system has not received the file, has not fetched the URL, and
  has not written or uploaded anything

### Requirement: Changing a poster is confirmed before it runs
An Upload or a From URL submission SHALL NOT change anything by itself. It SHALL
open the chosen replacement full screen in the same preview the found-poster
candidates are inspected in, and the change SHALL be taken only from that
preview, through the same two-step commitment: an action offering to use the
previewed image, then a final confirmation. This SHALL be the presentation on
touch and pointer devices alike.

A picked file SHALL be rendered from the user's own device. A pasted URL SHALL be
loaded from the source the user named. A replacement the browser cannot display
SHALL NOT prevent the user from confirming it, because the system — not the
browser — determines whether a replacement is usable and already reports a
rejection.

The final confirmation SHALL name the poster being replaced using the same title
the change dialog heading shows, and SHALL offer its action under the
non-destructive emphasis reserved for overwrites rather than the destructive one
reserved for deletion. It SHALL NOT restate what happens to a Plex-linked poster
afterwards.

While the change runs the preview SHALL indicate progress without delay, and the
change SHALL NOT be startable a second time while it is in flight.

Dismissing the preview — with Escape, the close action, or the backdrop — SHALL
return the user to the change dialog, which SHALL remain open with the file or
URL the user provided still in place, and SHALL change nothing.

#### Scenario: Upload previews before it replaces
- **WHEN** a user picks an image file in the change dialog and submits
- **THEN** that image is shown full screen and nothing is transmitted until the
  user confirms from the preview

#### Scenario: From URL previews before it fetches
- **WHEN** a user enters an image URL in the change dialog and submits
- **THEN** the image at that URL is shown full screen and the system does not
  fetch or store it until the user confirms from the preview

#### Scenario: The preview asks before it changes
- **WHEN** a user chooses to use the image they are previewing
- **THEN** a final confirmation is asked, and the poster is changed only once
  that confirmation is given

#### Scenario: The confirmation names the poster
- **WHEN** the final confirmation is shown for an uploaded file or a pasted URL
- **THEN** it names the poster being replaced with the same title the change
  dialog heading shows

#### Scenario: The preview is full screen on touch
- **WHEN** a user submits either tab on a touch device, where the change dialog
  is itself presented as a tray
- **THEN** the replacement is previewed full screen over that tray, the same way
  a found-poster candidate is

#### Scenario: Dismissing the preview keeps the input
- **WHEN** a user dismisses the preview with Escape, the close control, or the
  backdrop
- **THEN** the preview closes, the change dialog is still open, the file or URL
  the user provided is still there, and no change is performed

#### Scenario: An undisplayable image can still be confirmed
- **WHEN** the browser cannot load the image at a URL the user entered
- **THEN** the preview resolves rather than waiting, and the user may still
  confirm the change, which the system accepts or rejects on its own terms

#### Scenario: Progress is shown while the change runs
- **WHEN** a user confirms a change from the preview
- **THEN** progress is indicated immediately and stays until the change succeeds
  or fails

#### Scenario: The change cannot be started twice
- **WHEN** a user confirms and then activates the confirmation again before it
  has finished
- **THEN** only one change is performed

#### Scenario: No separate text confirmation is raised
- **WHEN** a user submits the Upload or the From URL form
- **THEN** the text-only confirmation dialog used by Send to Plex, Fetch from
  Plex and Delete is not raised for that submission

### Requirement: The change dialog names its own action
The change-poster dialog's Upload and From URL submit controls SHALL name how
that tab supplies its image rather than claim to change the poster: "Upload
poster" on the Upload tab, which sends a file from the user's device, and "Fetch
poster" on the From URL tab, which has the system retrieve one. The action that
does change the poster SHALL be labelled "Change poster" wherever it appears —
the preview's final confirmation, for all three sources — matching the dialog's
heading, so one action has one name throughout. The URL field SHALL be labelled
"Image URL" with no parenthetical about accepted sources.

#### Scenario: Each submit control names how its image arrives
- **WHEN** a user opens the change-poster dialog on the Upload tab or the From
  URL tab
- **THEN** the Upload tab's submit control reads "Upload poster" and the From URL
  tab's reads "Fetch poster"

#### Scenario: The change itself is still named "Change poster"
- **WHEN** a user reaches the final confirmation in the preview, from any of the
  three tabs
- **THEN** the control that performs the change reads "Change poster"

#### Scenario: URL field label carries no source parenthetical
- **WHEN** a user opens the From URL tab
- **THEN** the field is labelled "Image URL", with no "(also supports Mediux
  URLs)" suffix

#### Scenario: Labels do not change what is accepted
- **WHEN** a user submits a URL that the system accepted before this labelling
  change
- **THEN** it is accepted exactly as before; the label change is presentational
  only
