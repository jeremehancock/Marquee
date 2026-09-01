## ADDED Requirements

### Requirement: A horizontal drag on the gallery moves between adjacent categories

On a touch device, a horizontal drag on the gallery SHALL move between adjacent
category tabs. The outgoing category SHALL track the touch one-to-one, and the
incoming category SHALL enter from the opposite edge at the same rate, one
viewport apart, both updating for as long as the touch continues.

The two categories SHALL NOT overlap at any point in the gesture. One leaves as
the other arrives; neither is ever drawn over the other.

On release the gesture SHALL resolve to exactly one of three outcomes: it
commits and the categories complete their travel, it is abandoned and they
return to rest with the active category unchanged, or it was never claimed and
nothing moved.

The motion is the confirmation. A gesture evaluated only when the finger leaves
the glass produces the same first frame whether it is going to work or not, so
the viewer cannot see that it was recognised, cannot see how far is left, and
cannot change their mind. All three of those have to be true while the thumb is
still down.

Overlapping the two is specifically excluded. An incoming panel entering at a
fraction of the outgoing panel's speed — the familiar platform parallax — sits
on top of it for the whole gesture, and which of the two is drawn above the
other is then decided by their order in the document rather than by the
direction of travel, which reads as one direction winning every time.

#### Scenario: The grids follow the finger

- **WHEN** a horizontal drag is in progress
- **THEN** the outgoing category's horizontal offset SHALL correspond to the
  distance the touch has travelled, updating as the touch moves

#### Scenario: The incoming category arrives as the outgoing one leaves

- **WHEN** a horizontal drag is in progress
- **THEN** the incoming category SHALL be visible entering from the opposite
  edge at the same rate the outgoing one leaves, and SHALL arrive at rest at the
  same moment the outgoing one completes its travel

#### Scenario: Neither category is ever drawn over the other

- **WHEN** a horizontal drag is in progress, in either direction
- **THEN** the two SHALL remain one viewport apart and SHALL NOT overlap, so
  which is visible depends only on how far the drag has travelled

#### Scenario: Reversing the drag reverses the grids

- **WHEN** the touch reverses direction mid-drag
- **THEN** the grids SHALL follow it back, and a drag that returns to its origin
  SHALL leave them at rest

#### Scenario: A committed drag completes the travel

- **WHEN** a drag is released having travelled at least a third of the viewport
  width, or at a speed above the flick threshold in the direction of the
  incoming category
- **THEN** the outgoing category SHALL continue off the screen in the direction
  the finger travelled, the incoming one SHALL complete its entry, and the
  category change SHALL take effect

#### Scenario: An abandoned drag restores the previous category

- **WHEN** a drag is released having travelled less than the commit distance and
  below the flick threshold
- **THEN** both SHALL return to rest, the active category SHALL be unchanged,
  and the viewer SHALL be returned to the scroll position they were at when the
  drag began

#### Scenario: A drag past the threshold can still be abandoned

- **WHEN** a drag travels past the commit distance and is then dragged back
  below it before release
- **THEN** the gesture SHALL be abandoned rather than committed

#### Scenario: The settle is timed from the distance still to travel

- **WHEN** a drag is released
- **THEN** the grids SHALL complete their movement over a duration proportional
  to the distance remaining, bounded below by a floor and above by the standard
  transition duration

#### Scenario: A pointer device is unaffected

- **WHEN** the gallery is used on a device that reports a hover-capable pointer
- **THEN** no drag gesture SHALL be bound, and changing category by clicking a
  tab SHALL remain an instant cut

### Requirement: The drag's axis is decided once, early, and held

A touch on the gallery SHALL be assigned to exactly one axis within the first
few pixels of travel, and that assignment SHALL hold for the remainder of the
touch.

A touch assigned to the vertical axis SHALL be left entirely to the browser's
scrolling for the rest of its life. A touch assigned to the horizontal axis
SHALL have the browser's default handling suppressed from the move that claimed
it onward.

The listener that claims the gesture SHALL be registered non-passively from the
outset. A passive listener cannot suppress the default at all, and a touch
sequence whose early moves went uncancelled has on some platforms already been
given to the scroller, where later attempts to cancel it are ignored silently —
producing a gesture that works everywhere except the platform it was written
for.

A gesture that re-arbitrates its axis mid-drag can hand a moving page back to
the scroller halfway through, so it SHALL NOT.

#### Scenario: A vertical drag scrolls and is never claimed

- **WHEN** a touch's initial travel is predominantly vertical
- **THEN** the page SHALL scroll normally, the grids SHALL NOT move, and the
  touch SHALL NOT be claimed later in its life however it subsequently moves

#### Scenario: A horizontal drag is claimed before the page can scroll

- **WHEN** a touch's initial travel is predominantly horizontal
- **THEN** the gesture SHALL be claimed within the first few pixels of travel,
  and the page SHALL NOT scroll for the remainder of that touch

#### Scenario: A tap is neither

- **WHEN** a touch begins and ends without exceeding the axis-lock distance
- **THEN** no drag SHALL have begun, and the touch SHALL behave exactly as it
  does today — opening the poster's action tray when it lands on a card

#### Scenario: A two-finger gesture is not a drag

- **WHEN** a touch begins with more than one contact point, or a second contact
  arrives during a touch
- **THEN** no drag SHALL be claimed, so a pinch or zoom reaches the browser

### Requirement: A drag with nowhere to go resists

A horizontal drag toward a category that does not exist SHALL move the current
category by a damped fraction of the touch's travel and SHALL return it to rest
on release, without changing the active category.

The traversal order SHALL be the order the tabs are rendered in — All, Movies,
Shows, Seasons, Collections — so the gesture agrees with what the bottom bar
shows. All is the first and Collections is the last; the other three commit in
both directions.

A drag off either end that did nothing at all would be indistinguishable from a
gesture the application failed to recognise. Resistance says there is nothing
there, which is the fact the viewer is missing.

#### Scenario: Dragging past the first category resists

- **WHEN** the viewer drags rightward while All is active
- **THEN** All SHALL move by a damped fraction of the travel, no incoming
  category SHALL appear, and it SHALL return to rest on release

#### Scenario: Dragging past the last category resists

- **WHEN** the viewer drags leftward while Collections is active
- **THEN** Collections SHALL move by a damped fraction of the travel, no
  incoming category SHALL appear, and it SHALL return to rest on release

#### Scenario: A resisted drag never commits

- **WHEN** a resisted drag is released at any distance or speed
- **THEN** the active category SHALL be unchanged

#### Scenario: An interior category commits in both directions

- **WHEN** the viewer drags in either direction while Movies, Shows or Seasons
  is active
- **THEN** the adjacent category in that direction SHALL be the incoming one and
  the drag SHALL be committable

### Requirement: The adjacent categories are fetched before the gesture needs them

The application SHALL fetch and hold the rendered results of both categories
adjacent to the active one, so that a drag has real content to move from its
first frame rather than a placeholder.

The fetch SHALL happen after the active category has settled and SHALL NOT
delay it. A held copy SHALL be discarded rather than shown whenever anything
determining what that category displays has changed since it was fetched — the
search term, the sort order, or any mutation of the library such as a delete, a
poster change, or a completed import.

The comparison SHALL err toward discarding. Any input to what a category
displays that is not covered by it SHALL be treated as a change.

The asymmetry is the point. Wrongly discarding a good copy costs one fetch that
nobody needed. Wrongly trusting a stale one shows the viewer a grid that does
not match their search or sort — a wrong library that looks like a working one.

Holding a copy SHALL remain an optimisation and SHALL NOT become load-bearing.
The gesture SHALL be fully correct with every held copy absent, and the absence
SHALL cost only the placeholder described below.

#### Scenario: A held copy is used when it is current

- **WHEN** a drag begins toward a category whose held copy matches the live
  search, sort and library state
- **THEN** that copy SHALL be shown as the incoming category, with no fetch
  during the gesture

#### Scenario: A changed search discards the held copies

- **WHEN** the viewer changes the search term or the sort order, and then drags
  to an adjacent category
- **THEN** that category SHALL display results matching the new selection, never
  the previous one

#### Scenario: A library mutation discards the held copies

- **WHEN** a poster is deleted or changed, or an import completes
- **THEN** any held copy SHALL be discarded, and a subsequent drag SHALL show
  the category as it is now

#### Scenario: A missing copy does not refuse the gesture

- **WHEN** a drag begins toward a category with no current held copy
- **THEN** the drag SHALL begin and follow the finger exactly as it otherwise
  would, showing a placeholder in the incoming panel while its results are
  fetched

#### Scenario: The placeholder is replaced in place

- **WHEN** the fetched results arrive while the drag is still in progress
- **THEN** they SHALL replace the placeholder without interrupting the gesture
  or moving the panel

#### Scenario: A commit waits for content rather than showing a placeholder

- **WHEN** a drag commits before the fetched results have arrived
- **THEN** the category change SHALL still take effect, and the deferred loading
  indication SHALL report the outstanding work as it does for any other
  in-place view change

### Requirement: A committed drag leaves the same state as tapping the tab

A drag that commits SHALL leave the application in exactly the state that
tapping the destination tab leaves it in: that category active in the bottom
bar, its results in the results region, the browser title updated, a history
entry pushed so the back gesture returns to the previous category, the active
search carried over, and the view positioned at the top.

There SHALL be one code path that performs the category change, used by both the
tap and the drag. Two paths that must agree about seven things will not keep
agreeing.

Infinite scroll SHALL be re-established for the newly active category at its
first page. A category returned to after a drag SHALL NOT restore however far it
had previously been scrolled — the held copy is a first page, and preserving
depth would make the cache load-bearing for what the viewer sees rather than
only for how quickly they see it.

#### Scenario: A committed drag pushes a history entry

- **WHEN** a drag commits from Movies to Shows
- **THEN** a history entry SHALL be pushed, and the browser's back gesture SHALL
  return to Movies

#### Scenario: A committed drag keeps the active search

- **WHEN** a search is active and a drag commits to another category
- **THEN** that category SHALL open filtered by the same search term

#### Scenario: A committed drag re-arms infinite scroll

- **WHEN** a drag commits and the destination category has more than one page
- **THEN** scrolling to the foot of its grid SHALL append the next page as it
  does after a tab tap

#### Scenario: Returning to a category shows its first page

- **WHEN** the viewer has scrolled several pages into a category, drags away,
  and drags back
- **THEN** that category SHALL be shown from its first page at the top, not at
  its previous depth

### Requirement: A drag is refused before it is claimed when an overlay is open

A touch SHALL NOT begin a drag while any overlay is open, or when the touch
begins inside an overlay panel or on its backdrop, or when it begins on the
category tab bar.

The refusal SHALL happen when the touch begins, not when it ends. A gesture that
discovers the conflict later has already suppressed the browser's handling and
taken both grids out of the scroller.

A touch inside an overlay belongs to that overlay — its own dismissal drag, a
scroll in its body, or a backdrop tap — and never to the gallery behind it. A
touch on the tab bar belongs to the tab bar.

The check for an open overlay SHALL be the same one the page scroll lock uses.
Two independent answers to "is an overlay open" will drift, and the cost of them
disagreeing here is a gesture that fights a tray.

#### Scenario: An open overlay refuses the drag

- **WHEN** a touch begins on the page while any tray or dialog is open
- **THEN** no drag SHALL begin, and the grids SHALL NOT move

#### Scenario: A touch inside an overlay reaches the overlay

- **WHEN** a touch begins inside an overlay's panel
- **THEN** that overlay's own gesture handling SHALL apply and no drag SHALL
  begin

#### Scenario: A tray dismissal drag still works

- **WHEN** a downward drag begins on an open tray's grab handle or head
- **THEN** the tray SHALL be dismissed as it is today, with no interference from
  the category gesture

#### Scenario: A touch on the tab bar is not a drag

- **WHEN** a touch begins on the fixed bottom tab bar
- **THEN** no drag SHALL begin, and the tap SHALL change category as it does
  today

### Requirement: An interrupted drag leaves nothing pinned

A drag SHALL be resolved to a correct resting state when it is interrupted by
anything: a cancelled touch, a viewport resize, an orientation change, a
category change from another control, or a second drag beginning.

Resolution SHALL run from a single routine that is safe to invoke repeatedly and
that clears everything the gesture set — both panels' pinning, transforms and
inline sizing, the spacer holding its place in the flow, the gesture-live flag,
and any pending frame callback.

A drag takes both grids out of the document's scroller. Leaving that in place
because a touch was cancelled by an incoming call gives the viewer a page that
cannot scroll, with nothing on screen to explain it.

#### Scenario: A cancelled touch resolves the drag

- **WHEN** a touch driving a drag is cancelled by the system
- **THEN** the drag SHALL resolve to a resting state and the page SHALL scroll
  normally

#### Scenario: A second gesture resolves the first

- **WHEN** a new drag begins while a settle from a previous one is still running
- **THEN** the previous one SHALL be fully resolved before the new one begins

#### Scenario: A resize during a drag resolves it

- **WHEN** the viewport is resized or the device is rotated while a drag is in
  progress
- **THEN** the drag SHALL resolve to a resting state and the gallery SHALL
  re-measure its layout

#### Scenario: The page never scrolls sideways

- **WHEN** a drag is in progress, at any offset, in either direction
- **THEN** the document SHALL NOT gain horizontal scroll, and no part of the
  moving panels SHALL be reachable by scrolling sideways

#### Scenario: Pinned and sticky chrome keeps its behaviour during a drag

- **WHEN** a drag is in progress, having begun at any scroll position
- **THEN** every element that was pinned or stuck to the viewport before the
  gesture SHALL remain so for its duration, and SHALL NOT move or stop sticking

## MODIFIED Requirements

### Requirement: Category tab presentation by screen size
On a narrow screen the category tabs SHALL be presented as a fixed, always-visible
bottom tab bar in which all five tabs fit the screen at once — each tab an icon
above a short label — rather than a scrolling row, so switching categories feels
like a native app tab bar. The gallery content SHALL reserve space so the tab bar
never hides the last posters or the footer. On a pointer/desktop screen the tabs
SHALL remain text tabs above the toolbar, and SHALL be pinned together with it as
the gallery scrolls (see "Responsive gallery layout and pinned controls"). Each tab SHALL retain its
full category name as its accessible name regardless of which presentation is
shown.

The bar SHALL mark the destination category as active from the moment a
horizontal drag is claimed, and SHALL mark the original again if that drag is
abandoned. The mark moves at the claim rather than at the release because it is
the application's acknowledgement that the gesture was recognised, which is owed
at the start of the gesture and not at the end of it.

The bar SHALL NOT itself move with the drag. It is fixed to the viewport, so the
grids slide beneath it and it needs no travel of its own; a bar that slid with
them would leave the viewer with nothing stationary to read the gesture against.

#### Scenario: All tabs fit at once in a bottom bar on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are shown as a fixed bottom bar of equal-width columns that all
  fit on screen without scrolling, each an icon over a short label
- **AND** content is not hidden behind the bar

#### Scenario: Active tab is indicated
- **WHEN** a category is active on a phone
- **THEN** its tab is visually highlighted as the current one

#### Scenario: Desktop tabs keep their text presentation
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the tabs render as text tabs directly above the toolbar, rather than as
  the phone's icon-over-label bottom bar

#### Scenario: The bar marks the destination when a drag is claimed
- **WHEN** a horizontal drag toward an adjacent category is claimed
- **THEN** that category's tab is marked active immediately, before the drag has
  been released

#### Scenario: An abandoned drag returns the mark
- **WHEN** a claimed drag is released without committing
- **THEN** the originally active tab is marked active again

#### Scenario: The bar stays still while the grids move
- **WHEN** a drag is in progress
- **THEN** the bottom tab bar remains in place, and only its active mark changes
