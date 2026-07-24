## ADDED Requirements

### Requirement: Sort order selection
The system SHALL support two gallery sort orders — **Alphabetical** (article-aware
title order) and **Date added** (by when each poster's media was added to Plex,
newest first) — and SHALL apply the selected order to both a single category and
the aggregate `all` view.

#### Scenario: Alphabetical order lists by title
- **WHEN** the effective sort order is Alphabetical
- **THEN** the gallery lists posters by title using the existing article-aware
  ordering

#### Scenario: Date-added order lists newest first
- **WHEN** the effective sort order is Date added
- **THEN** the gallery lists posters by their Plex "added at" timestamp with the
  most recently added poster first

#### Scenario: Sort order applies to the aggregate view
- **WHEN** a user views the `all` slug with the Date added order
- **THEN** posters from every category are merged and ordered together by their
  Plex "added at" timestamp, newest first

### Requirement: Default sort order configuration
The system SHALL read a preferred default sort order from the `DEFAULT_SORT`
environment variable, accepting `alphabetical` or `date_added`, and SHALL fall
back to Alphabetical when the variable is unset, empty, or holds an unrecognized
value.

#### Scenario: Default is Alphabetical when unset
- **WHEN** `DEFAULT_SORT` is not set
- **THEN** the gallery orders posters alphabetically until the user chooses
  otherwise

#### Scenario: Date-added set as the install default
- **WHEN** `DEFAULT_SORT` is `date_added`
- **THEN** the gallery orders posters by date added until the user chooses
  otherwise

#### Scenario: Unrecognized value falls back to Alphabetical
- **WHEN** `DEFAULT_SORT` holds a value other than `alphabetical` or `date_added`
- **THEN** the system uses Alphabetical rather than raising an error

### Requirement: Sort order toggle
The system SHALL present a control in the gallery toolbar to switch between
Alphabetical and Date added, and the user's choice SHALL persist across
navigation within the session, taking precedence over `DEFAULT_SORT`. When the
user has not made a choice, the toggle SHALL reflect the configured default.

#### Scenario: Toggling re-orders the gallery
- **WHEN** a user selects a sort order from the toggle
- **THEN** the gallery re-renders its listing in that order

#### Scenario: Choice persists across navigation
- **WHEN** a user selects Date added and then navigates to another category
- **THEN** that category is also ordered by date added without re-selecting it

#### Scenario: Toggle reflects the configured default before any choice
- **WHEN** a user opens the gallery having made no sort selection this session
- **THEN** the toggle indicates the order given by `DEFAULT_SORT`

### Requirement: Date-added fallback for posters without a Plex timestamp
When sorting by Date added, the system SHALL order posters that have no stored
Plex "added at" timestamp using their file modification time, so every poster
holds a stable position in the ordering.

#### Scenario: Unmapped poster still has a position
- **WHEN** the gallery is ordered by date added and a poster has no Plex "added
  at" value
- **THEN** the poster is ordered by its file modification time rather than
  omitted or grouped unpredictably

## MODIFIED Requirements

### Requirement: Gallery listing with pagination
The system SHALL list the posters in a category as a gallery, ordered by the
effective sort order (Alphabetical or Date added), and split into pages of a
configurable size (`IMAGES_PER_PAGE`). Pagination links SHALL preserve the
active sort order.

#### Scenario: Posters are paginated
- **WHEN** a category contains more posters than the page size
- **THEN** the system shows only one page of posters and provides navigation to
  the other pages
- **AND** reports how many posters are shown out of the total

#### Scenario: Out-of-range page is clamped
- **WHEN** a user requests a page number beyond the last page
- **THEN** the system shows the last available page rather than an error

#### Scenario: Paging keeps the sort order
- **WHEN** a user is viewing a non-default sort order and moves to another page
- **THEN** the next page is listed in the same sort order
