## ADDED Requirements

### Requirement: A tray has one scrolling region, and it contains its scrolling
A scrolling region inside an open tray SHALL contain its scrolling. When such a
region reaches the end of its content, the gesture SHALL stop there rather than
continuing into whatever scrolls behind it, so that no flick started inside a
tray can end up scrolling the gallery behind it.

A tray SHALL NOT nest one scrolling region inside another. Where a tray's
contents include a region that scrolls independently on a wider screen — the
stack of grouped candidates in the Change Poster tray is the case — that region
SHALL hand its scrolling up to the tray's body at tray widths, so the tray has
a single scroller.

Nesting is excluded rather than merely contained because the two rules combine
badly: containment makes the inner region the *only* one a gesture over it can
move, stopping a flick at the inner region's end instead of continuing into the
outer one. Whatever the outer region holds beyond the inner one's box then
becomes unreachable by any gesture the user would think to make. The two
scrollers also present two scrollbars for one tray, only one of which responds
where the user's finger is.

#### Scenario: Reaching the end of a tray's contents
- **WHEN** a user scrolls a tray's contents to the bottom and continues the flick
- **THEN** scrolling stops at the end of the tray's contents and the page behind
  the tray does not scroll

#### Scenario: A tray has one scroller, not two
- **WHEN** a user opens the Change Poster tray on a small screen and selects a
  tab whose candidates are grouped — Find Posters or Plex Posters
- **THEN** the tray presents a single scrolling region, and one flick begun
  anywhere over its contents moves all of them together

#### Scenario: Scrolling the grouped candidates to their end
- **WHEN** a user scrolls the grouped candidates in the Change Poster tray to
  their end and continues the flick
- **THEN** neither the surrounding tray nor the gallery behind it is scrolled by
  that gesture

### Requirement: A tray's contents are reachable in full
Every part of an open tray's contents SHALL be reachable by scrolling. No
content SHALL be laid out beyond the edge at which the tray's panel clips,
where no gesture available to the user can bring it into view.

This SHALL hold whatever the height of the viewport. A tray's panel and the
regions inside it are sized as proportions of the viewport, while the tray's
own furniture — its grab handle, heading, any tab strip, and the device's
bottom safe-area inset — occupies a fixed height that does not shrink with the
screen. A shorter viewport therefore gives that furniture a larger share, and
an arrangement that fits on a tall phone can clip its last row on a short one.
Reachability SHALL NOT depend on the viewport being tall enough to absorb the
difference.

Content lost this way reports nothing. The tray looks complete, the clipped row
is simply absent, and a user who cannot reach a candidate cannot choose it and
is given no reason why — which is why this is stated as a requirement rather
than left to the sizing rules to get right.

#### Scenario: The last row of found candidates can be reached
- **WHEN** a user opens Find Posters on a small screen for a title with enough
  candidates to overflow the tray
- **THEN** scrolling reaches the last row of candidates in full, and it can be
  tapped to preview

#### Scenario: The end of a long Plex group can be reached
- **WHEN** a user opens Plex Posters on a small screen for an item whose offered
  artwork runs to dozens of candidates
- **THEN** scrolling reaches the end of the last group in full, with no
  candidate left below the edge of the tray

#### Scenario: A short viewport clips nothing
- **WHEN** the same tray is opened on a screen short enough that its handle,
  heading, tab strip, and safe-area inset take a large share of the height
- **THEN** its contents are still reachable in full by scrolling, rather than
  the last row falling below the panel's edge

## REMOVED Requirements

### Requirement: Scrolling within a tray stays within the tray
**Reason**: Replaced by "A tray has one scrolling region, and it contains its
scrolling". The containment guarantee is carried forward unchanged and is not
what this change weakens. What cannot be carried forward is the premise the
requirement was built on: it required containment to hold "for a scrolling
region nested inside another scrolling region — such as the Find Posters
results grid inside the Change Poster tray — at every level", and its "Nested
scrolling region in Change Poster" scenario tested exactly that arrangement.

The arrangement is now excluded rather than accommodated, because it was the
fault itself. Containment on a nested inner region stops a flick at that
region's end instead of handing the remainder to the outer one, which left the
last row of Find Posters laid out below the tray panel's clipping edge with no
gesture able to reach it. A scenario asserting that the nested region contains
its scrolling cannot survive the removal of the nested region.

The requirement's example was also already stale. The scroll moved from the
Find Posters results grid up to the stack of grouped candidates when Plex
Posters and Find Posters began sharing that stack, so the named grid had not
been the nested scroller for some time.

**Migration**: None. No route, endpoint, stored data, or user action is
affected. Containment at the end of a tray's contents behaves exactly as
before; the change is that a phone reaches the end of the Change Poster tray's
candidates by the same flick that scrolls everything else in it.
