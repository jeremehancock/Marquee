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

#### Scenario: Posters are paginated
- **WHEN** a category contains more posters than the page size
- **THEN** the system shows only one page of posters and provides navigation to
  the other pages
- **AND** reports how many posters are shown out of the total

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

### Requirement: Remembered library section
When a user leaves the gallery for the Orphans or Import pages, the system SHALL
return them to the library section they were last viewing, including the All
view. The All view is a rememberable section like any category. The link that
returns to the gallery SHALL be labelled "Back to gallery".

#### Scenario: Return to the last section
- **WHEN** a user viewing a library section (a single category or All) opens
  Orphans or Import and then follows the "Back to gallery" link
- **THEN** they return to the section they were viewing, not a different one

#### Scenario: Return to All
- **WHEN** a user viewing the All view opens Orphans or Import and then follows
  the "Back to gallery" link
- **THEN** they return to the All view
