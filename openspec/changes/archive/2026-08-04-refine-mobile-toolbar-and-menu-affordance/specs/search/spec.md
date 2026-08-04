## MODIFIED Requirements

### Requirement: Live search
The gallery SHALL filter posters as the user types in the search box, without
requiring the user to submit, and SHALL restore the full list when the box is
emptied.

When a search updates the results, the gallery SHALL return the user to the top
of the results, so a search started from part-way down the gallery presents its
matches from the first one rather than from wherever the previous list happened
to be scrolled to. The return SHALL be animated rather than instantaneous, and
SHALL honour a reduced-motion preference by jumping instead. This matches how
pagination and switching category already behave.

#### Scenario: Filtering as you type
- **WHEN** a user types text into the gallery search box
- **THEN** the grid updates to matching posters shortly after the user stops
  typing, without a full page reload

#### Scenario: Clearing the search
- **WHEN** the search box becomes empty
- **THEN** the gallery shows the full, unfiltered list again

#### Scenario: Searching after scrolling shows matches from the first one
- **WHEN** a user scrolls part-way down the gallery and then searches
- **THEN** the gallery returns to the top of the results as they update
- **AND** the first match is what the user sees

#### Scenario: Return to the top is animated
- **WHEN** the gallery returns to the top after a search
- **THEN** it scrolls smoothly rather than jumping

#### Scenario: Reduced motion suppresses the scroll animation
- **WHEN** the user has asked for reduced motion
- **THEN** the gallery moves to the top of the results without animating
