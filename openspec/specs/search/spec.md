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

#### Scenario: Earlier match ranks first
- **WHEN** a user searches "matrix" and both "Matrix Reloaded" and "The Matrix"
  match
- **THEN** the poster whose normalized title matches earliest is listed first

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
The system SHALL apply search within the current category and paginate the
filtered results, keeping the query when navigating between pages.

#### Scenario: Paging through search results keeps the query
- **WHEN** a user pages through search results
- **THEN** each page reflects the same query and category
