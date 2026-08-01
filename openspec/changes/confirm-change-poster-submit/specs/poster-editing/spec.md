## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Changing a poster is confirmed before it runs
The change-poster dialog SHALL confirm an Upload or a From URL submission through
the same confirmation used by the gallery's other overwriting actions — a
full-screen modal on pointer devices and a tray on touch — presented over the
change dialog, which SHALL remain open behind it.

The confirmation SHALL name the poster being replaced using the same title the
dialog heading shows, SHALL say that the poster will be replaced and that a
Plex-linked poster is also uploaded and locked, and SHALL offer its action under
the non-destructive emphasis reserved for overwrites rather than the destructive
one reserved for deletion.

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
