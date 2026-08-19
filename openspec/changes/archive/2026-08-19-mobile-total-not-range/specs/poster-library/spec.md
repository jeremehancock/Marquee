## MODIFIED Requirements

### Requirement: Gallery listing with pagination
The system SHALL list the posters in a category as a gallery, ordered by the
effective sort order (Alphabetical or Date added), and split into pages of a
configurable size (`IMAGES_PER_PAGE`). When a category spans more than one
page, the gallery SHALL present a numbered pagination control that provides:
go-to-first and go-to-last controls, previous and next steppers, and a run of
individual page numbers. The number run SHALL be windowed around the current
page and MUST collapse omitted ranges into an ellipsis so the count of rendered
numbers stays bounded regardless of the total page count (for example,
`< 1 2 3 … 82 >` on an early page of an 82-page listing). The current page
SHALL be marked as active and SHALL NOT be a navigation link; every other page
number, the first/last controls, and the previous/next steppers SHALL be links.
All pagination links SHALL preserve the active sort order and any active search
query.

Where the pagination control is shown, the gallery SHALL also report which
posters of the total are on the current page. That report describes a page, so it
belongs with the pager: on a narrow screen, where infinite scroll replaces
pagination, the gallery reports the category total instead (see "Infinite scroll
on small screens").

#### Scenario: Posters are paginated
- **WHEN** a category contains more posters than the page size
- **THEN** the system shows only one page of posters and provides navigation to
  the other pages

#### Scenario: A paged gallery reports the range it is showing
- **WHEN** the gallery is viewed on a pointer/desktop-width screen and the
  pagination control is shown
- **THEN** the gallery reports how many posters are shown out of the total, as a
  range of the current page

#### Scenario: Jump to first or last page
- **WHEN** the gallery spans more than one page
- **THEN** the pagination control offers a go-to-first and a go-to-last control
- **AND** activating them navigates to page 1 and the last page respectively

#### Scenario: Page numbers are windowed with an ellipsis
- **WHEN** the total page count exceeds what can be shown as a continuous run of
  numbers
- **THEN** the pagination control shows a bounded set of page numbers around the
  current page and collapses the omitted pages into an ellipsis
- **AND** the number of rendered page numbers does not grow with the total page
  count

#### Scenario: Current page is marked and not a link
- **WHEN** the pagination control renders the current page's number
- **THEN** that number is marked as active and is not a navigable link
- **AND** every other page number is a link

#### Scenario: Out-of-range page is clamped
- **WHEN** a user requests a page number beyond the last page
- **THEN** the system shows the last available page rather than an error

#### Scenario: Paging keeps the sort order
- **WHEN** a user is viewing a non-default sort order and moves to another page
- **THEN** the next page is listed in the same sort order

#### Scenario: Paging keeps the active search
- **WHEN** a search is active and the user follows any pagination link
- **THEN** the search query is preserved on the destination page

### Requirement: Infinite scroll on small screens
On a narrow screen the gallery SHALL load posters by infinite scroll instead of
pagination: it SHALL append the next page of posters as the user nears the bottom
of the current results, continuing until the last page is reached, so the whole
library becomes reachable by scrolling without loading it all at once. The
pagination controls SHALL be hidden on a narrow screen and SHALL remain on a
pointer/desktop screen.

Because there is no current page on a narrow screen, the gallery SHALL NOT report
a range of one. It SHALL report the number of posters in the category instead —
a figure that is true when the gallery opens and stays true however far the user
scrolls. A range would be neither: appending posters does not rewrite the line,
so a range would still name the first batch after the user had scrolled well past
it, and it would name a pager that is hidden.

The system SHALL choose between the two reports by the width of the screen alone,
so that a window resized across the threshold shows the report that matches the
navigation then in use. Exactly one of the two SHALL be presented to the user at
any width, including to assistive technology.

#### Scenario: Scrolling loads more posters
- **WHEN** a user on a narrow screen scrolls near the bottom of the current
  posters and more pages exist
- **THEN** the next page of posters is appended below without a manual page change

#### Scenario: Loading stops at the last page
- **WHEN** the last page of posters has been appended
- **THEN** no further loading is attempted

#### Scenario: Desktop keeps pagination
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the pagination controls are shown and used as before

#### Scenario: A scrolling gallery reports the total

- **WHEN** a category is viewed on a narrow screen
- **THEN** the gallery reports the number of posters in the category rather than
  a range of a page

#### Scenario: The reported total survives scrolling

- **WHEN** a user on a narrow screen has scrolled far enough that several further
  batches of posters have been appended
- **THEN** the reported figure is still the category total, and is still correct

#### Scenario: Only one report is presented

- **WHEN** the gallery is rendered at any width
- **THEN** exactly one of the two reports is presented, both to a reader and to
  assistive technology

#### Scenario: Resizing across the threshold switches the report

- **WHEN** the viewport is resized from a pointer/desktop width to a narrow width
  or back, without the page being reloaded
- **THEN** the report shown changes to match the navigation in use at that width

#### Scenario: A single-page category still reports its total

- **WHEN** a category whose posters all fit on one page is viewed on a narrow
  screen
- **THEN** the gallery reports the category total, as it does for any other
  category

#### Scenario: A search reports its matches unchanged

- **WHEN** a search is active on a narrow screen
- **THEN** the gallery reports the number of matches for the query, as it does on
  a pointer/desktop screen
