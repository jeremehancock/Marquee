# Poster Library Specification

## Purpose

The gallery: how stored posters are organized, listed, presented, and acted on.
Four fixed categories backed by directories on disk, paginated and sorted
listings, protected image serving, and the interaction model that exposes each
poster's actions on both pointer and touch devices.

This capability owns *browsing and presenting* posters. Posters enter the
library through `plex-import`; operating on one poster is `poster-editing`;
filtering the listing is `search`.
## Requirements
### Requirement: Poster categories
The system SHALL organize posters into four fixed categories — Movies, TV Shows,
TV Seasons, and Collections — each backed by its own directory within the
posters storage location.

#### Scenario: Known category is browsable
- **WHEN** a user opens a category by its slug (`movies`, `tv-shows`,
  `tv-seasons`, `collections`)
- **THEN** the system shows the gallery for that category

#### Scenario: Unknown category is rejected
- **WHEN** a user requests a category slug that is not one of the four
- **THEN** the system responds with HTTP 404

### Requirement: Gallery listing with pagination
The system SHALL list the posters in a category as a gallery, ordered by a
stable sort, and split into pages of a configurable size (`IMAGES_PER_PAGE`).

#### Scenario: Posters are paginated
- **WHEN** a category contains more posters than the page size
- **THEN** the system shows only one page of posters and provides navigation to
  the other pages
- **AND** reports how many posters are shown out of the total

#### Scenario: Out-of-range page is clamped
- **WHEN** a user requests a page number beyond the last page
- **THEN** the system shows the last available page rather than an error

### Requirement: Article-aware ordering
The system SHALL sort posters by title, ignoring a leading article ("a", "an",
"the") when `IGNORE_ARTICLES_IN_SORT` is enabled.

#### Scenario: Leading article ignored in sort
- **WHEN** `IGNORE_ARTICLES_IN_SORT` is true and a poster titled "The Matrix"
  is sorted among others
- **THEN** it is ordered as if titled "Matrix"

### Requirement: Auth-protected image serving
The system SHALL serve poster image files only to authenticated users, with
caching headers, and SHALL never resolve a request outside the posters
directory. The rendered URL for a poster SHALL carry a version marker derived
from the file's modification time, so that replacing the file yields a different
URL. The system SHALL identify the requested image from the path alone and
SHALL ignore the version marker when serving.

#### Scenario: Authenticated image request succeeds
- **WHEN** an authenticated user requests an existing poster image
- **THEN** the system responds with the image bytes and an image content type

#### Scenario: Version marker is ignored when serving
- **WHEN** a poster image is requested with a version marker that is absent,
  outdated, or unrecognized
- **THEN** the system serves the poster currently on disk rather than failing or
  serving an earlier image

#### Scenario: Path traversal is refused
- **WHEN** a request for a poster image contains path separators or traversal
  sequences in the filename
- **THEN** the system responds with HTTP 404 and serves no file outside the
  posters directory

### Requirement: Poster presentation
The gallery SHALL show each poster's title in a caption beneath the poster, size
posters large enough for the overlay action stack to fit, and lazy-load images
with a subtle placeholder animation that resolves when the image loads.

#### Scenario: Title beneath the poster
- **WHEN** the gallery renders a poster
- **THEN** its title appears in a caption below the image rather than inside the
  hover overlay

#### Scenario: Lazy-load animation
- **WHEN** a poster image has not yet loaded
- **THEN** a subtle placeholder animation is shown and the image fades in once
  loaded

### Requirement: Poster actions on pointer devices
On pointer (hover-capable) devices the gallery SHALL reveal a poster's action
overlay on hover, without the actions being clipped or hidden off-card, and
clicking the poster itself SHALL open it full screen.

#### Scenario: Hover reveals actions on desktop
- **WHEN** a user hovers a poster on a pointer device
- **THEN** the action overlay is shown

#### Scenario: Clicking opens full screen on desktop
- **WHEN** a user clicks a poster (not one of its action buttons) on a pointer
  device
- **THEN** the poster opens full screen

### Requirement: Poster actions on touch devices
On touch devices the poster actions SHALL be presented in a bottom action sheet
opened by tapping the poster, rather than overlaid on the poster itself, so every
action is shown at full size and none can be triggered by accident.

#### Scenario: Tapping a poster opens the action sheet
- **WHEN** a user taps a poster on a touch device
- **THEN** a sheet opens listing that poster's actions (change, send/fetch to
  Plex when linked, download, copy URL, full screen, delete) with its title

#### Scenario: No tap-through
- **WHEN** a user taps a poster on a touch device
- **THEN** the tap opens the sheet only and does not trigger any poster action

#### Scenario: Dismissing the sheet
- **WHEN** the user taps outside the sheet, presses Escape, or runs one of its
  actions
- **THEN** the sheet closes

### Requirement: Always-available Plex actions
For every poster linked to a Plex item, the gallery SHALL always offer Send to
Plex and Fetch from Plex, independent of whether the poster was recently changed.

#### Scenario: Linked poster always shows Plex actions
- **WHEN** the gallery renders a poster that is linked to a Plex item
- **THEN** both Send to Plex and Fetch from Plex are available for it

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
category tabs scroll or wrap rather than overflow, toolbar controls wrap to their
own rows, and posters are sized so at least two fit per row on a phone.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the category tabs are laid out without overflowing the screen width

### Requirement: Fullscreen poster view
The system SHALL let a user view any gallery poster full screen.

#### Scenario: Open a poster full screen
- **WHEN** a user activates a poster in the gallery
- **THEN** the system displays that poster in a full-screen view

### Requirement: Delete a poster
The system SHALL allow an authenticated user to delete a poster from a category.

#### Scenario: Poster is deleted
- **WHEN** an authenticated user deletes an existing poster
- **THEN** the system removes the image file and it no longer appears in the
  gallery

### Requirement: Background poster updates
The gallery SHALL apply poster actions — change, send to Plex, fetch from Plex,
and delete — without reloading the whole page, refreshing only the grid and
reporting the outcome.

#### Scenario: Updating a poster refreshes only the grid
- **WHEN** a user changes, sends, fetches, or deletes a poster
- **THEN** the grid reflects the result and a short confirmation is shown, and
  the surrounding page is not reloaded

#### Scenario: Works without JavaScript
- **WHEN** a user performs a poster action with JavaScript disabled
- **THEN** the action still completes via a normal form submission

### Requirement: Modal confirmations
Destructive actions SHALL ask for confirmation through an in-app modal rather
than a native browser dialog.

#### Scenario: Confirm deleting a poster
- **WHEN** a user chooses to delete a poster
- **THEN** a confirmation modal appears and the poster is deleted only if the
  user confirms

#### Scenario: Confirm deleting all orphans
- **WHEN** a user chooses to delete all orphaned posters
- **THEN** a confirmation modal appears and the orphans are deleted only if the
  user confirms

### Requirement: Remembered library section
When a user leaves the gallery for the Orphans or Import pages, the system SHALL
return them to the library section they were last viewing.

#### Scenario: Return to the last section
- **WHEN** a user viewing a non-default library section opens Orphans or Import
  and then follows the back-to-library link
- **THEN** they return to the section they were viewing, not the default one

