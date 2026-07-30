## MODIFIED Requirements

### Requirement: Filtered view is clearly indicated
When a search query is active, the gallery SHALL make it visually clear that the
grid is a filtered subset of the current view rather than the full view. The
indication SHALL include the active query and a way to clear it back to the full
list, and SHALL persist as the user switches views while the query remains
active.

The gallery SHALL present exactly one clear control for an active query, and
that control SHALL be co-located with the filtered-state indication, so the
indication and its clear control appear, update, and disappear as one unit
whenever the results change. The browser's own in-field clear affordance for the
search input is not a gallery control and is not counted here.

#### Scenario: Result summary shown while filtering
- **WHEN** a search query is active in the current view
- **THEN** the gallery shows a summary identifying the active query and the
  number of matches in the current view
- **AND** provides an obvious control to clear the search back to the full list

#### Scenario: A single clear control is offered
- **WHEN** a search query is active in the current view, however the view was
  reached — typing in the search box, loading a filtered address directly, or
  switching views with the query still active
- **THEN** the gallery offers exactly one control for clearing the search
- **AND** that control is the one presented with the filtered-state summary

#### Scenario: The clear control disappears with the filtered state
- **WHEN** a user clears an active search
- **THEN** the gallery shows the full, unfiltered list
- **AND** no clear control remains anywhere in the gallery

#### Scenario: Filtered indication persists across a view switch
- **WHEN** a user with an active query switches views
- **THEN** the search box remains populated with the query
- **AND** the filtered-state indication is shown for the newly selected view

#### Scenario: Filtered empty state is distinguishable
- **WHEN** an active query matches no posters in the selected view
- **THEN** the empty grid indicates the view is filtered by the query, not that
  the view has no posters
- **AND** provides a way to clear the search
