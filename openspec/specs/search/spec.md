# Search Specification

## Purpose

Filtering the gallery down to the posters a user is looking for. Matching is
deliberately specific rather than broadly fuzzy — a library of thousands of
posters makes loose matching worse than useless — and it applies live, within
the current category, without a page reload.
## Requirements
### Requirement: Specific poster search
The system SHALL filter the posters in a category by a search query, matching
only posters whose title contains every query term after normalization
(case-insensitive, diacritics and separators ignored). The match SHALL be
specific rather than broadly fuzzy.

#### Scenario: All query terms must match
- **WHEN** a user searches "star wars" in a category
- **THEN** the system shows posters whose title contains both "star" and "wars"
- **AND** hides posters that contain only one of the terms

#### Scenario: Case and accents ignored
- **WHEN** a user searches "amelie"
- **THEN** a poster titled "Amélie" is included in the results

#### Scenario: No matches
- **WHEN** a search matches no posters in the category
- **THEN** the system shows an empty gallery and reports zero results

### Requirement: Results ranked by match position
The system SHALL order matching posters by how early the query first matches in
the normalized title, so titles that begin with the query appear before titles
that merely contain it, breaking ties by title.

The tie-break SHALL compare titles on the same digit-aware terms the gallery
uses for its own ordering: a run of digits within a title is ordered by its
numeric value rather than character by character. Without this, searching for a
show would list its equally relevant seasons as Season 1, Season 10, Season 11,
Season 2 — the ordering defect the gallery's own listing does not have.

#### Scenario: Earlier match ranks first
- **WHEN** a user searches "matrix" and both "Matrix Reloaded" and "The Matrix"
  match
- **THEN** the poster whose normalized title matches earliest is listed first

#### Scenario: Equally relevant results order numbers by value
- **WHEN** a user searches for a show whose season posters all match equally
  early
- **THEN** the seasons are listed Season 1, Season 2, Season 3 and so on, with
  Season 10 after Season 9

#### Scenario: Ranking still leads
- **WHEN** results differ both in where the query matches and in the numbers
  their titles contain
- **THEN** match position determines the order, and the digit-aware comparison
  applies only between results that match equally early

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

