## ADDED Requirements

### Requirement: A surface that behaves as a dialog declares itself one

Every overlay that covers the page, takes a backdrop, and holds its own controls
SHALL declare `role="dialog"`, `aria-modal="true"`, and an accessible name.

Focus management SHALL be driven by that declaration rather than by a list of
overlays held somewhere else, so an overlay added later is managed by being
marked up correctly rather than by being registered. This includes an overlay
injected into the page at runtime, such as one arriving inside a fetched
fragment.

An overlay holding no focusable content SHALL NOT declare itself a dialog, and
SHALL therefore not be managed: there is nothing to move focus to, and keeping
focus inside it would keep it nowhere.

#### Scenario: A newly added overlay is managed without being listed

- **WHEN** a new modal overlay is added to the application and declares itself a
  dialog
- **THEN** it takes, keeps, and returns focus without any overlay-specific
  wiring being written for it

#### Scenario: An overlay arriving at runtime is managed

- **WHEN** an overlay is injected into the page as part of a fetched fragment and
  then opened
- **THEN** it is managed on the same terms as one present when the page loaded

#### Scenario: An overlay with its own controls is announced as a dialog

- **WHEN** an overlay that offers the user controls is opened
- **THEN** assistive technology reports it as a modal dialog with a name, rather
  than as unlabelled content over the page

### Requirement: An overlay takes focus when it opens

When an overlay opens, the application SHALL move focus into it rather than
leaving focus on the page behind it.

Focus SHALL land on the overlay's own panel, so that the overlay's accessible
name is announced and the user moves forward through its contents in order.
Focus SHALL NOT be placed on the first control the overlay happens to contain: an
overlay whose contents are fetched after it opens has no such control at the
moment focus must move, and an overlay offering a destructive action must not
open with that action under the user's hands.

Moving focus SHALL NOT scroll the page or the overlay.

#### Scenario: An overlay opens

- **WHEN** a user opens an overlay
- **THEN** focus moves into the overlay and its name is reported, rather than
  remaining on the page behind the backdrop

#### Scenario: An overlay whose contents arrive later

- **WHEN** an overlay opens and its contents are still being fetched
- **THEN** focus still moves into the overlay, and the contents become reachable
  by moving forward from there once they arrive

#### Scenario: Taking focus does not move the page

- **WHEN** an overlay opens over a page scrolled away from the top
- **THEN** neither the page nor the overlay scrolls as focus moves in

### Requirement: Focus stays inside the overlay that holds it

While an overlay holds focus, moving forward past its last control SHALL return
to its first, and moving backward past its first SHALL return to its last. Focus
SHALL NOT reach the page behind the overlay.

Focus arriving outside the open overlay by any other route SHALL be returned to
it. This is not a theoretical case: hiding an element that currently has focus
hands that focus to the document, and the application hides focused controls as a
matter of course — a control that switches itself off, or an action row replaced
by the confirmation that follows it. Left alone, the user's next keystroke would
resume at the top of the document, which is the failure this requirement exists
to prevent.

#### Scenario: Moving forward past the last control

- **WHEN** a user moves focus forward from the last control in an open overlay
- **THEN** focus returns to the start of that overlay rather than entering the
  page behind it

#### Scenario: Moving backward past the first control

- **WHEN** a user moves focus backward from the first control in an open overlay
- **THEN** focus moves to the last control in that overlay

#### Scenario: Focus falls out from under the user

- **WHEN** the element holding focus inside an open overlay is hidden or removed
  as a result of the user operating it
- **THEN** focus is returned to that overlay rather than being left on the
  document

### Requirement: Focus returns where it came from when an overlay closes

When an overlay closes, the application SHALL return focus to whatever held it
when that overlay opened.

This SHALL hold for every way an overlay closes — dismissed by keyboard, by its
close control, by its backdrop, by a swipe, by completing the action it offered,
or by the page navigating away — because a user who is returned to their place on
some dismissals and stranded on others is worse served than one who learns the
behaviour is not there at all.

Returning focus SHALL take effect as the overlay begins to close, not when its
exit animation finishes, consistent with dismissal never being delayed by its own
animation.

Where the element that held focus is no longer in the document — the poster card
whose deletion the overlay confirmed, the row the action removed — focus SHALL be
returned to the nearest surviving part of the page that contained it, so the user
resumes near where they were rather than at the top of the document.

#### Scenario: An overlay is dismissed

- **WHEN** a user dismisses an overlay by any means
- **THEN** focus returns to the control that opened it

#### Scenario: An overlay completes its action

- **WHEN** an overlay closes because the action it offered has completed
- **THEN** focus returns to the page rather than being left on the dismissed
  overlay

#### Scenario: The origin of focus no longer exists

- **WHEN** an overlay closes and the element that held focus when it opened has
  been removed from the page
- **THEN** focus is returned to the nearest surviving region that contained it,
  rather than to the start of the document

#### Scenario: The return is not delayed by the exit animation

- **WHEN** a user dismisses an overlay and immediately continues by keyboard
- **THEN** that keystroke acts from the returned position, rather than being
  applied while focus is still inside the closing overlay

### Requirement: Overlays that stack hand focus back in turn

An overlay opened from within another overlay SHALL take focus from it, and SHALL
return focus into the overlay beneath it when it closes, leaving that one still
open and still holding focus.

The order SHALL be the order the overlays were opened in. It SHALL NOT be derived
from their order in the markup or from their drawn stacking order, neither of
which describes which overlay the user reached last.

#### Scenario: An overlay raised from another takes focus

- **WHEN** an overlay is opened from a control inside an overlay that is already
  open
- **THEN** focus moves into the newly opened overlay, and moving through controls
  reaches only that overlay's

#### Scenario: A stacked overlay closes

- **WHEN** the topmost of two open overlays is dismissed
- **THEN** focus returns into the overlay beneath it, which stays open

#### Scenario: Markup order does not decide which overlay holds focus

- **WHEN** an overlay raises another that appears earlier in the page's markup
- **THEN** the overlay the user opened last is the one that holds focus
