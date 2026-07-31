## ADDED Requirements

### Requirement: Paging returns the view to the top
When a user activates a pagination link, the system SHALL return the view to the
top of the gallery so the destination page begins at its first poster rather than
wherever the previous page left the viewport. The return SHALL be animated as a
smooth scroll, and SHALL begin when the link is activated rather than waiting for
the destination page to render. When the user's system preference asks for
reduced motion, the view SHALL still be returned to the top, but without the
animation.

This requirement concerns only the pagination controls, which are shown on a
pointer/desktop screen; a narrow screen replaces them with infinite scroll and
its viewport is never moved.

#### Scenario: Paging scrolls back to the top

- **WHEN** a user scrolled down to the pagination control follows a page number,
  a previous/next stepper, or a first/last control
- **THEN** the view returns to the top of the gallery, so the destination page is
  seen from its first poster

#### Scenario: The return is animated

- **WHEN** the view is returned to the top after a pagination link is activated
- **AND** the user has expressed no reduced-motion preference
- **THEN** the movement is a smooth scroll rather than an instant jump

#### Scenario: The scroll starts before the new page arrives

- **WHEN** a pagination link is activated
- **THEN** the view begins returning to the top immediately, without waiting for
  the destination page's posters to be fetched and rendered

#### Scenario: Reduced motion skips the animation

- **WHEN** a user whose system preference asks for reduced motion activates a
  pagination link
- **THEN** the view is at the top of the gallery, and it was moved there without
  an animation

#### Scenario: Infinite scroll is unaffected

- **WHEN** a user on a narrow screen reaches the bottom and the next page of
  posters is appended
- **THEN** the view is not moved, and the appended posters continue below the
  ones already on screen
