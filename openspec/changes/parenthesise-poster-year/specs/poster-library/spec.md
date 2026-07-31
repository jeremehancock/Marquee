## MODIFIED Requirements

### Requirement: Poster presentation
The gallery SHALL show each poster's title in a caption beneath the poster, size
posters large enough for the overlay action stack to fit, and lazy-load images
with a subtle placeholder animation that resolves when the image loads. The
placeholder animation and fade-in SHALL apply on every page that renders poster
cards, not only the gallery, and SHALL resolve whether the image loads or fails.
Plex import bakes the source library name into the poster's filename, so it
surfaces as a trailing token in the derived title (e.g. "… 2003 Movies"). The
visible caption and its tooltip SHALL omit that library token, because the type is
already conveyed by the badge and the active tab.

When the poster's media has a known release year, the caption and its tooltip
SHALL show that year in parentheses at the end of the title (e.g. "Louis and the
Nazis (2003)"), so the year reads as metadata rather than as part of the title.
This SHALL apply to every poster with a known year — movies, TV shows, and TV
seasons — whether or not the year already appears in the derived title: where it
does, it SHALL be moved into parentheses rather than duplicated; where it does
not, it SHALL be added. A poster with no known year SHALL be shown unchanged.

Both the library token and the year SHALL be identified by comparing against the
poster's known library name and known year, never by pattern-matching the text,
so a title that merely ends in digits (e.g. the series "1883") is never mistaken
for a year and never has those digits removed.

Rendering the caption SHALL NOT modify any stored state: no poster file is
renamed and no database row is written.

The caption SHALL be constrained to a single line no wider than the poster above
it, truncating with an ellipsis rather than wrapping, so a long title never pushes
the following row of posters down.

#### Scenario: Title beneath the poster
- **WHEN** the gallery renders a poster
- **THEN** its title appears in a caption below the image rather than inside the
  hover overlay

#### Scenario: Caption omits the library token
- **WHEN** the gallery renders a Plex-imported poster whose derived title ends in
  its library name (e.g. "Louis and the Nazis 2003 Movies" from the "Movies"
  library)
- **THEN** the visible caption drops that trailing library token while the rest
  of the title is unchanged

#### Scenario: A year already in the title moves into parentheses
- **WHEN** the gallery renders a poster whose derived title (after the library
  token is dropped) ends in its known year — e.g. "Louis and the Nazis 2003" for
  a poster whose stored year is 2003
- **THEN** the caption shows "Louis and the Nazis (2003)", with the year appearing
  once

#### Scenario: A known year absent from the title is added
- **WHEN** the gallery renders a poster whose derived title does not contain its
  known year — e.g. a TV show "Breaking Bad" or a season "Breaking Bad - Season 1"
  with a stored year of 2008
- **THEN** the caption shows "Breaking Bad (2008)" / "Breaking Bad - Season 1
  (2008)"

#### Scenario: A trailing number that is not the year is kept
- **WHEN** the gallery renders a poster whose title ends in digits that are not
  its known year — e.g. the series "1883" with a stored year of 2021, or the
  movie "Blade Runner 2049" with a stored year of 2017
- **THEN** those digits remain part of the title and only the known year is
  parenthesised: "1883 (2021)" and "Blade Runner 2049 (2017)"

#### Scenario: Poster with no known year
- **WHEN** the gallery renders a poster that has no stored year (e.g. a
  collection, or a poster with no Plex mapping)
- **THEN** the caption shows its title with no parenthesised year added

#### Scenario: Captions never rewrite stored state
- **WHEN** the gallery renders any caption, including one whose year was added or
  moved
- **THEN** the poster's filename on disk and its database row are unchanged

#### Scenario: Tooltip matches the caption
- **WHEN** a user hovers a poster whose caption dropped its library token or
  parenthesised its year
- **THEN** the tooltip shows exactly the same title as the caption

#### Scenario: Non-Plex poster keeps its full title
- **WHEN** the gallery renders a poster with no known library (e.g. an uploaded
  poster)
- **THEN** the caption shows the full derived title unchanged

#### Scenario: Long caption truncates instead of wrapping
- **WHEN** a poster's caption is longer than the poster is wide
- **THEN** the caption stays on a single line no wider than the poster and ends
  in an ellipsis, and the posters below it are not pushed down

#### Scenario: Lazy-load animation
- **WHEN** a poster image has not yet loaded
- **THEN** a subtle placeholder animation is shown and the image fades in once
  loaded

### Requirement: Poster actions on touch devices
On touch devices the poster actions SHALL be presented in a bottom action sheet
opened by tapping the poster, rather than overlaid on the poster itself, so every
action is shown at full size and none can be triggered by accident. The sheet's
heading SHALL name the poster using exactly the same title as its caption —
library token dropped, release year in parentheses. The heading SHALL NOT append
the source library, because the user reached the sheet by tapping that poster in
a view they chose, and a parenthesised library beside a parenthesised year reads
as two competing parentheticals.

#### Scenario: Tapping a poster opens the action sheet
- **WHEN** a user taps a poster on a touch device
- **THEN** a sheet opens listing that poster's actions (change, send/fetch to
  Plex when linked, download, copy URL, full screen, delete) with its title

#### Scenario: Sheet heading matches the caption
- **WHEN** the action sheet opens for a Plex-imported poster whose caption shows
  "Louis and the Nazis (2003)"
- **THEN** its heading shows "Louis and the Nazis (2003)" — the same text, with
  no library appended

#### Scenario: No tap-through
- **WHEN** a user taps a poster on a touch device
- **THEN** the tap opens the sheet only and does not trigger any poster action

#### Scenario: Dismissing the sheet
- **WHEN** the user taps outside the sheet, presses Escape, or runs one of its
  actions
- **THEN** the sheet closes
