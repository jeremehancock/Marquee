## ADDED Requirements

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

### Requirement: Search preserves category and pagination
The system SHALL apply search within the current category and paginate the
filtered results, keeping the query when navigating between pages.

#### Scenario: Paging through search results keeps the query
- **WHEN** a user pages through search results
- **THEN** each page reflects the same query and category
