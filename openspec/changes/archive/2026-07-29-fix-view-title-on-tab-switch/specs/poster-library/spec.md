## ADDED Requirements

### Requirement: Browser title tracks the displayed view
The gallery navigates between views without a full page reload. Whenever it
replaces the displayed results this way, it SHALL also update the browser
document title to the title the server rendered for that view, so the title,
the address bar, and the visible grid always describe the same thing. This
SHALL hold for category tab switches, search, clearing a search, pagination,
and backward or forward history navigation alike. If a fetched response carries
no title, the current title SHALL be left as it is rather than cleared.

#### Scenario: Switching category tabs updates the title
- **WHEN** a user switches from one category tab to another (for example All to
  Movies) without reloading the page
- **THEN** the browser title changes to name the newly displayed view
- **AND** it does so immediately, without requiring a refresh

#### Scenario: Search and pagination keep the title correct
- **WHEN** a user searches within a view, clears the search, or moves to
  another page of results
- **THEN** the browser title continues to name the view being displayed

#### Scenario: History navigation restores the title
- **WHEN** a user presses the browser's back or forward control to return to a
  previously visited view
- **THEN** the browser title matches the restored view, alongside the restored
  tab and search box

#### Scenario: Missing title leaves the current one intact
- **WHEN** a fetched response contains no document title
- **THEN** the existing browser title is left unchanged rather than blanked
