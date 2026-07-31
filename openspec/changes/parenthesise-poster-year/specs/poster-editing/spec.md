## ADDED Requirements

### Requirement: The change-poster dialog names the poster it will replace
The change-poster dialog (a modal on pointer devices, a tray on touch) SHALL name
the poster being replaced using exactly the same title the gallery caption shows
for it — the source library token dropped, the release year in parentheses. It
SHALL NOT append the source library, so the heading carries one parenthetical
(the year) rather than two, and so the same title text serves the caption, the
action sheet, and this dialog.

#### Scenario: Dialog heading matches the caption
- **WHEN** a user opens the change-poster dialog for a poster whose caption shows
  "Louis and the Nazis (2003)"
- **THEN** the dialog names it "Louis and the Nazis (2003)", with no library
  appended

#### Scenario: Poster with no known year
- **WHEN** a user opens the change-poster dialog for a poster with no stored year
  (e.g. a collection)
- **THEN** the dialog shows its title with no parenthesised year and no library

#### Scenario: Dialog title does not affect the replacement
- **WHEN** the change-poster dialog submits an upload, a URL, or a Find Posters
  selection
- **THEN** the poster is identified by its filename and category as before, and
  the displayed title has no effect on which file is replaced
