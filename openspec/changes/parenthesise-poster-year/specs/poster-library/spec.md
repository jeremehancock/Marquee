## MODIFIED Requirements

### Requirement: Poster presentation
The gallery SHALL show each poster's title in a caption beneath the poster, size
posters large enough for the overlay action stack to fit, and lazy-load images
with a subtle placeholder animation that resolves when the image loads. The
placeholder animation and fade-in SHALL apply on every page that renders poster
cards, not only the gallery, and SHALL resolve whether the image loads or fails.
For a poster mapped to a Plex item, the caption and its tooltip SHALL show the
title recorded for that item at import, **not** a title reconstructed from the
poster's filename. The filename is a sanitised, lossy copy: import flattens every
run of non-alphanumeric characters and appends the source library, so a title
rebuilt from it loses punctuation and gains a library token that the recorded
title never had. A poster with no such record, or whose recorded title is empty,
SHALL fall back to the filename-derived title.

When the poster's media has a known release year, the caption and its tooltip
SHALL append that year in parentheses (e.g. "Louis and the Nazis (2003)"), so the
year reads as metadata rather than as part of the title. The year SHALL NOT be
appended when the title already contains it in parentheses, so a Plex title that
names its own year (e.g. a show "Lucky (2026)") shows that year once rather than
twice. A poster with no known year SHALL be shown unchanged.

**TV season posters SHALL never be given a year.** A season record carries its
*show's* release year, because Plex reports no year on a season node, so appending
it would date every season of a long-running show to the year that show began.
A year that is part of the show's own title (e.g. "Lucky (2026) - Season 1") SHALL
still be shown, because it belongs to the title Plex reported rather than being
added by the system.

The check for an already-present year SHALL match the parenthesised form
specifically, never bare digits, so a title whose own words include a number (e.g.
"Class of 2026", "Blade Runner 2049", the series "1883") keeps those digits and
still receives its release year.

Every place the gallery names a poster SHALL use this one title — the caption, its
tooltip, the image's alternative text, the action sheet heading, the change-poster
dialog heading, and the delete confirmation — so a poster reads identically
wherever it appears.

Rendering the caption SHALL NOT modify any stored state: no poster file is
renamed and no database row is written.

The caption SHALL be constrained to a single line no wider than the poster above
it, truncating with an ellipsis rather than wrapping, so a long title never pushes
the following row of posters down.

#### Scenario: Title beneath the poster
- **WHEN** the gallery renders a poster
- **THEN** its title appears in a caption below the image rather than inside the
  hover overlay

#### Scenario: Caption never shows the source library
- **WHEN** the gallery renders a Plex-imported poster whose filename carries its
  library (e.g. `Louis_and_the_Nazis_2003_Movies.jpg` from the "Movies" library)
- **THEN** the caption shows the recorded title, "Louis and the Nazis (2003)",
  with no library token — the recorded title never held one

#### Scenario: Caption preserves punctuation the filename lost
- **WHEN** the gallery renders a poster whose recorded title contains characters
  the filename sanitiser replaces — e.g. "Marvel's Agents of S.H.I.E.L.D." or
  "Spider-Noir B&W"
- **THEN** the caption shows those characters, rather than the underscores-turned-
  spaces of the filename ("Marvel s Agents of S H I E L D")

#### Scenario: A known year is appended
- **WHEN** the gallery renders a movie or TV show poster whose recorded title does
  not name its year — e.g. a movie "Louis and the Nazis" (2003) or a show
  "Breaking Bad" (2008)
- **THEN** the caption appends it: "Louis and the Nazis (2003)", "Breaking Bad
  (2008)"

#### Scenario: A season shows no year
- **WHEN** the gallery renders a season poster — e.g. "Breaking Bad - Season 5",
  whose record carries the show's year of 2008
- **THEN** the caption shows "Breaking Bad - Season 5" with no year appended,
  rather than dating a 2012 season to 2008

#### Scenario: A season keeps a year belonging to its show's title
- **WHEN** the gallery renders a season of a show Plex names "Lucky (2026)", whose
  recorded title is "Lucky (2026) - Season 1"
- **THEN** the caption shows "Lucky (2026) - Season 1" — the year is part of the
  reported title, so it is neither removed nor duplicated

#### Scenario: A year already in the title is not repeated
- **WHEN** the gallery renders a poster whose recorded title already contains its
  year in parentheses — e.g. a show Plex names "Lucky (2026)" with a stored year
  of 2026
- **THEN** the caption shows "Lucky (2026)", naming the year once

#### Scenario: Numbers in the title are not mistaken for a present year
- **WHEN** the gallery renders a poster whose recorded title contains bare digits
  matching its year — e.g. a movie "Class of 2026" released in 2026
- **THEN** the caption still appends the year, "Class of 2026 (2026)", because the
  digits are not parenthesised

#### Scenario: Numbers in the title survive
- **WHEN** the gallery renders a poster whose title contains digits that are not
  its year — e.g. the series "1883" (2021) or the movie "Blade Runner 2049" (2017)
- **THEN** those digits remain and the release year is appended: "1883 (2021)",
  "Blade Runner 2049 (2017)"

#### Scenario: Poster with no known year
- **WHEN** the gallery renders a poster that has no stored year (e.g. a
  collection, or a poster with no Plex mapping)
- **THEN** the caption shows its title with no parenthesised year added

#### Scenario: Captions never rewrite stored state
- **WHEN** the gallery renders any caption, including one whose year was appended
- **THEN** the poster's filename on disk and its database row are unchanged

#### Scenario: Tooltip matches the caption
- **WHEN** a user hovers a poster
- **THEN** the tooltip shows exactly the same title as the caption

#### Scenario: The delete confirmation names the poster the same way
- **WHEN** a user deletes a poster whose caption shows "Louis and the Nazis (2003)"
- **THEN** the confirmation names that poster identically, rather than showing the
  raw filename-derived title with its library token

#### Scenario: Poster with no Plex record falls back to its filename
- **WHEN** the gallery renders a poster with no `plex_items` record, or one whose
  recorded title is empty
- **THEN** the caption shows the filename-derived title, unchanged from the
  behaviour before this change

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
as recorded by Plex, release year in parentheses. The heading SHALL NOT append the
source library, because the user reached the sheet by tapping that poster in a
view they chose, and a parenthesised library beside a parenthesised year reads as
two competing parentheticals.

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
