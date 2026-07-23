## ADDED Requirements

### Requirement: Aggregate All view
The system SHALL provide an aggregate "All" view, addressed by the reserved slug
`all`, that lists posters from all four categories together in a single gallery.
The All view SHALL be the default landing view and the first tab in the category
strip; opening the site root SHALL take the user to the All view. The All view is
not a fifth stored category and has no directory of its own — it is a combined
listing over the four real categories.

#### Scenario: All view is browsable
- **WHEN** a user opens the `all` slug
- **THEN** the system shows a single gallery containing posters from every
  category

#### Scenario: All is the default landing view
- **WHEN** a user opens the site root
- **THEN** the system takes them to the All view rather than a single category

#### Scenario: All is the first tab
- **WHEN** the gallery renders its category tabs
- **THEN** an All tab appears first, ahead of Movies, TV Shows, TV Seasons, and
  Collections

#### Scenario: All view paginates the combined listing
- **WHEN** the combined listing contains more posters than the page size
- **THEN** the system shows one page of the combined posters and provides
  navigation to the other pages, reporting how many are shown out of the
  combined total

### Requirement: Aggregate view ordering
Within the All view the system SHALL order posters by title across all types
(mixed, not grouped by category), applying the same article-aware ordering used
elsewhere. When two posters have the same sort title, the system SHALL break the
tie by category in the order Movies, TV Shows, TV Seasons, Collections, so the
order is stable and deterministic.

#### Scenario: Titles are mixed across types
- **WHEN** the All view lists posters of different categories
- **THEN** they are ordered together by title rather than grouped into
  per-category blocks

#### Scenario: Equal titles break ties by category
- **WHEN** two posters in the All view share the same sort title but belong to
  different categories
- **THEN** they are ordered by category in the sequence Movies, TV Shows,
  TV Seasons, Collections

### Requirement: Type badge in the aggregate view
In the All view the system SHALL display a small type badge on each poster
indicating its category (Movie, TV Show, TV Season, or Collection). On pointer
(hover-capable) devices the badge SHALL hide while the poster's action overlay is
shown, so it does not obscure or compete with the actions. On touch devices,
where the overlay is not used, the badge SHALL remain visible. Type badges SHALL
appear only in the All view; single-category views SHALL NOT show them.

#### Scenario: Badge shows the poster's type in All
- **WHEN** the All view renders a poster
- **THEN** a badge on the poster identifies its category as Movie, TV Show,
  TV Season, or Collection

#### Scenario: Badge hides on hover on pointer devices
- **WHEN** a user hovers a poster in the All view on a pointer device and the
  action overlay is revealed
- **THEN** the type badge is hidden while the overlay is shown

#### Scenario: Badge persists on touch
- **WHEN** the All view is viewed on a touch device
- **THEN** the type badge stays visible on each poster

#### Scenario: No badge in single-category views
- **WHEN** a single category (Movies, TV Shows, TV Seasons, or Collections) is
  viewed
- **THEN** no type badge is shown on its posters

### Requirement: Poster actions keyed by category in the aggregate view
Because poster filenames are unique only within a category, in the All view the
system SHALL identify each poster by its category together with its filename, and
every poster action — change, send to Plex, fetch from Plex, download, copy URL,
full screen, and delete — SHALL act on the poster's own category rather than a
single page-wide category. The change-poster modal opened for a poster SHALL
operate on that poster's category.

#### Scenario: An action targets the poster's own category
- **WHEN** a user runs an action on a poster in the All view
- **THEN** the action is applied to that poster within its own category, even if
  another category contains a poster with the same filename

#### Scenario: Change modal uses the poster's category
- **WHEN** a user opens the change-poster modal for a poster in the All view and
  applies a new image
- **THEN** the change is applied to that poster within its own category

## MODIFIED Requirements

### Requirement: Poster categories
The system SHALL organize posters into four fixed categories — Movies, TV Shows,
TV Seasons, and Collections — each backed by its own directory within the
posters storage location. In addition to the four categories, the system SHALL
accept the reserved slug `all` as an aggregate view over them (see the Aggregate
All view requirement).

#### Scenario: Known category is browsable
- **WHEN** a user opens a category by its slug (`movies`, `tv-shows`,
  `tv-seasons`, `collections`)
- **THEN** the system shows the gallery for that category

#### Scenario: Aggregate slug is browsable
- **WHEN** a user opens the reserved `all` slug
- **THEN** the system shows the combined gallery rather than responding 404

#### Scenario: Unknown category is rejected
- **WHEN** a user requests a slug that is neither one of the four categories nor
  the reserved `all` slug
- **THEN** the system responds with HTTP 404

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — now including the All tab, five in total — scroll rather than
overflow or crowd the screen, toolbar controls wrap to their own rows, and
posters are sized so at least two fit per row on a phone. On a narrow screen the
tab strip SHALL lay the tabs out in a single horizontal row that scrolls
(rather than a wrapping grid), so adding the All tab does not push tabs off the
edge of the screen or leave an awkward orphaned row.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are laid out in a scrollable horizontal row without
  overflowing the page width or crowding the other controls

### Requirement: Remembered library section
When a user leaves the gallery for the Orphans or Import pages, the system SHALL
return them to the library section they were last viewing, including the All
view. The All view is a rememberable section like any category.

#### Scenario: Return to the last section
- **WHEN** a user viewing a library section (a single category or All) opens
  Orphans or Import and then follows the back-to-library link
- **THEN** they return to the section they were viewing, not a different one

#### Scenario: Return to All
- **WHEN** a user viewing the All view opens Orphans or Import and then follows
  the back-to-library link
- **THEN** they return to the All view
