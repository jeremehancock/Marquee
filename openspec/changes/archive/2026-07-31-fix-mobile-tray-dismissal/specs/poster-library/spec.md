## ADDED Requirements

### Requirement: Consistent tray dismissal on small screens
Every overlay presented as a bottom tray on a small screen — the poster action
sheet, the app menu, sort, import, orphans, Change Poster, and confirmation
dialogs — SHALL be dismissible in the same two ways: by dragging the tray
downward, and by tapping the backdrop outside it. Trays SHALL NOT carry a close
button on a small screen; the grab handle and the backdrop are the whole
dismissal story, matching the native-app idiom the mobile experience follows.

Because there is no close button to fall back on, both of those routes SHALL be
reliable.

The region that accepts the dismissal drag SHALL be a real, hit-testable element
— a grab handle and the tray's heading — and SHALL be a distinct element from
the tray's scrolling region, so that dragging to dismiss and scrolling the
tray's contents can never be the same gesture on the same element. A tray whose
grab handle is drawn decoratively but cannot be targeted by touch does not
satisfy this requirement.

A downward drag that begins on a tray's handle or heading SHALL be claimed by
the tray for the duration of that gesture. It SHALL NOT scroll the page behind
the tray, and SHALL NOT trigger the browser's pull-to-refresh. A gesture that
begins on the drag region SHALL either dismiss the tray or return it to rest;
it SHALL NOT be abandoned partway by the browser taking the gesture over.

A tray SHALL leave a usable expanse of backdrop above it. No tray SHALL be
taller on a small screen than the established tray height, so the backdrop
remains a tap target of the same size on every tray rather than shrinking to a
sliver on the tallest ones.

Every tray SHALL also remain dismissible by pressing Escape, for users on a
device with a keyboard.

#### Scenario: Change Poster closes by dragging down
- **WHEN** a user opens Change Poster from the poster action tray on a small
  screen and drags its grab handle downward
- **THEN** the tray follows the drag and is dismissed, in the same way the poster
  action tray is

#### Scenario: A tall tray still leaves backdrop to tap
- **WHEN** a tray is open whose contents would otherwise fill the screen, such as
  Change Poster showing Find Posters results
- **THEN** the tray is no taller than any other tray, and the backdrop above it
  can be tapped to dismiss it

#### Scenario: Dismissal gesture is not stolen by the page
- **WHEN** a user drags downward on a tray's grab handle or heading while the
  page behind it is scrolled to the top
- **THEN** the tray moves with the drag, the page behind does not scroll, and the
  browser does not begin a pull-to-refresh

#### Scenario: Drag region is separate from the scrolling region
- **WHEN** a tray's contents are long enough to scroll
- **THEN** dragging the handle or heading dismisses the tray, and scrolling the
  tray's body scrolls its contents, with neither gesture performing the other

### Requirement: Scrolling within a tray stays within the tray
A scrolling region inside an open tray SHALL contain its scrolling. When such a
region reaches the end of its content, the gesture SHALL stop there rather than
continuing into whatever scrolls behind it. This SHALL hold for a scrolling
region nested inside another scrolling region — such as the Find Posters results
grid inside the Change Poster tray — at every level, so that no flick started
inside a tray can end up scrolling the gallery behind it.

#### Scenario: Reaching the end of a tray's contents
- **WHEN** a user scrolls a tray's contents to the bottom and continues the flick
- **THEN** scrolling stops at the end of the tray's contents and the page behind
  the tray does not scroll

#### Scenario: Nested scrolling region in Change Poster
- **WHEN** a user scrolls the Find Posters results grid to its end and continues
  the flick
- **THEN** neither the surrounding Change Poster tray nor the gallery behind it
  is scrolled by that gesture

### Requirement: Dialogs raised from inside a tray appear above it
A confirmation dialog opened by an action taken inside a tray SHALL be displayed
above that tray, not behind it, so the user can see and answer the question they
were just asked.

#### Scenario: Confirming a deletion started in the orphans tray
- **WHEN** a user chooses to delete an orphan, or all orphans, from within the
  orphans tray on a small screen
- **THEN** the confirmation is shown above the orphans tray and can be read and
  answered without dismissing that tray first

### Requirement: The page behind an open overlay does not scroll
While any tray, confirmation dialog, or the fullscreen poster viewer is open, the
page behind it SHALL NOT scroll. This SHALL hold for a gesture that begins on the
overlay's backdrop as well as one that begins on the overlay itself.

When the overlay is dismissed, the page SHALL be restored to exactly the scroll
position it held when the overlay opened. An exact restoration is required rather
than an approximate one because the gallery loads further posters in response to
approaching the end of the page; a restored position that differs from the
original would cause the gallery to append posters the user never scrolled to.

This requirement SHALL apply on iOS Safari as well as on other mobile browsers.

#### Scenario: Page does not scroll behind an open tray
- **WHEN** a user drags on the backdrop beside or above an open tray
- **THEN** the page behind the tray does not scroll and no pull-to-refresh begins

#### Scenario: Scroll position survives a tray
- **WHEN** a user scrolls partway down the gallery, opens a tray, and closes it
- **THEN** the gallery is at the same scroll position it was before the tray
  opened, and no additional posters have been appended as a result

#### Scenario: Typing in a tray does not displace the page
- **WHEN** a user opens Change Poster, focuses its URL field so the on-screen
  keyboard appears, then dismisses the keyboard and closes the tray
- **THEN** the gallery is left at the scroll position it held before the tray
  opened, not offset by the keyboard's appearance

## MODIFIED Requirements

### Requirement: Modal confirmations
Destructive actions SHALL ask for confirmation through an in-app modal rather
than a native browser dialog.

On a small screen a confirmation SHALL be presented as a tray, and SHALL meet the
same dismissal guarantees as every other tray: a working drag-to-dismiss whose
drag region is a real element distinct from any scrolling content, and a
tappable backdrop. Its own Cancel action SHALL remain, but SHALL NOT be the only
way to decline. Dismissing a confirmation by any route without choosing its
confirming action SHALL leave the destructive action untaken.

#### Scenario: Confirm deleting a poster
- **WHEN** a user chooses to delete a poster
- **THEN** a confirmation modal appears and the poster is deleted only if the
  user confirms

#### Scenario: Confirm deleting all orphans
- **WHEN** a user chooses to delete all orphaned posters
- **THEN** a confirmation modal appears and the orphans are deleted only if the
  user confirms

#### Scenario: Dismissing a confirmation tray by dragging it down
- **WHEN** a user drags a confirmation tray downward by its handle on a small
  screen
- **THEN** the confirmation is dismissed and the destructive action is not taken
