## ADDED Requirements

### Requirement: Live search
The gallery SHALL filter posters as the user types in the search box, without
requiring the user to submit, and SHALL restore the full list when the box is
emptied.

#### Scenario: Filtering as you type
- **WHEN** a user types text into the gallery search box
- **THEN** the grid updates to matching posters shortly after the user stops
  typing, without a full page reload

#### Scenario: Clearing the search
- **WHEN** the search box becomes empty
- **THEN** the gallery shows the full, unfiltered list again

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

### Requirement: Remembered library section
When a user leaves the gallery for the Orphans or Import pages, the system SHALL
return them to the library section they were last viewing.

#### Scenario: Return to the last section
- **WHEN** a user viewing a non-default library section opens Orphans or Import
  and then follows the back-to-library link
- **THEN** they return to the section they were viewing, not the default one

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

### Requirement: Always-available Plex actions
For every poster linked to a Plex item, the gallery SHALL always offer Send to
Plex and Fetch from Plex, independent of whether the poster was recently changed.

#### Scenario: Linked poster always shows Plex actions
- **WHEN** the gallery renders a poster that is linked to a Plex item
- **THEN** both Send to Plex and Fetch from Plex are available for it

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

### Requirement: Preview found posters
When showing candidate posters from the poster source, the gallery SHALL let the
user open a candidate full screen before applying it.

#### Scenario: Preview then apply
- **WHEN** a user views the found-poster results
- **THEN** they can open a candidate full screen to inspect it and separately
  choose to apply it

### Requirement: Poster Wall in a new tab
The Poster Wall SHALL open in a separate browser tab so the gallery stays open.

#### Scenario: Open the wall
- **WHEN** a user opens the Poster Wall from the gallery
- **THEN** it opens in a new tab
