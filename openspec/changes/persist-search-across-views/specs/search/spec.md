## MODIFIED Requirements

### Requirement: Search preserves category and pagination
The system SHALL apply search within the current view and paginate the filtered
results, keeping the query when navigating between pages AND when switching
between views. Switching views (tabs) SHALL carry the active query into the
newly selected view without a full page reload, and SHALL update the address so
the filtered view is shareable and restored by back/forward navigation. When the
search box is emptied, view switching SHALL return to showing full, unfiltered
views.

#### Scenario: Paging through search results keeps the query
- **WHEN** a user pages through search results
- **THEN** each page reflects the same query and view

#### Scenario: Switching views keeps the query
- **WHEN** a user has an active search in the All view and switches to the
  Movies view
- **THEN** the Movies view is shown filtered by the same query
- **AND** the grid updates without a full page reload
- **AND** the address reflects both the Movies view and the active query

#### Scenario: Clearing the search restores full views
- **WHEN** a user clears the search box and then switches views
- **THEN** the newly selected view shows its full, unfiltered list

## ADDED Requirements

### Requirement: Filtered view is clearly indicated
When a search query is active, the gallery SHALL make it visually clear that the
grid is a filtered subset of the current view rather than the full view. The
indication SHALL include the active query and a way to clear it back to the full
list, and SHALL persist as the user switches views while the query remains
active.

#### Scenario: Result summary shown while filtering
- **WHEN** a search query is active in the current view
- **THEN** the gallery shows a summary identifying the active query and the
  number of matches in the current view
- **AND** provides an obvious control to clear the search back to the full list

#### Scenario: Filtered indication persists across a view switch
- **WHEN** a user with an active query switches views
- **THEN** the search box remains populated with the query
- **AND** the filtered-state indication is shown for the newly selected view

#### Scenario: Filtered empty state is distinguishable
- **WHEN** an active query matches no posters in the selected view
- **THEN** the empty grid indicates the view is filtered by the query, not that
  the view has no posters
- **AND** provides a way to clear the search
