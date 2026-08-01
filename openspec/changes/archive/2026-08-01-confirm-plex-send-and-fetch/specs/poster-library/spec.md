## MODIFIED Requirements

### Requirement: Modal confirmations
Destructive actions SHALL ask for confirmation through an in-app modal rather
than a native browser dialog. An action counts as destructive when it overwrites
or removes something the user cannot get back by undoing — this includes
overwriting the artwork on a Plex item and overwriting a stored poster, not only
deleting a file.

The confirmation SHALL present the confirming action's own heading, its own
action label, and its own button emphasis, so the dialog states which action is
about to run rather than a single generic wording. The emphasis SHALL reserve the
destructive (red) treatment for actions that remove a poster, so an overwrite is
not offered under a delete-coloured button.

On a small screen a confirmation SHALL be presented as a tray, and SHALL meet the
same dismissal guarantees as every other tray: a working drag-to-dismiss whose
drag region is a real element distinct from any scrolling content, and a
tappable backdrop. Its own Cancel action SHALL remain, but SHALL NOT be the only
way to decline. Dismissing a confirmation by any route without choosing its
confirming action SHALL leave the destructive action untaken.

A card action that requires confirmation SHALL do so identically whether it was
started from the card's hover overlay or from the mobile action tray.

#### Scenario: Confirm deleting a poster
- **WHEN** a user chooses to delete a poster
- **THEN** a confirmation modal appears and the poster is deleted only if the
  user confirms

#### Scenario: Confirm deleting all orphans
- **WHEN** a user chooses to delete all orphaned posters
- **THEN** a confirmation modal appears and the orphans are deleted only if the
  user confirms

#### Scenario: Confirm overwriting through a Plex action
- **WHEN** a user chooses Send to Plex or Fetch from Plex for a linked poster
- **THEN** a confirmation modal appears naming that action, and the operation
  runs only if the user confirms

#### Scenario: The dialog names the action being confirmed
- **WHEN** a confirmation is shown for an action other than deleting
- **THEN** its heading and its confirming button describe that action rather than
  deletion, and the button does not use the destructive treatment

#### Scenario: Confirming an action started from the mobile tray
- **WHEN** a user taps a poster on a small screen and chooses a confirmed action
  from the action tray
- **THEN** the same confirmation is shown, and the action runs only if the user
  confirms

#### Scenario: Dismissing a confirmation tray by dragging it down
- **WHEN** a user drags a confirmation tray downward by its handle on a small
  screen
- **THEN** the confirmation is dismissed and the destructive action is not taken
