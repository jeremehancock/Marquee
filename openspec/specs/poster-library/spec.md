# Poster Library Specification

## Purpose

The gallery: how stored posters are organized, listed, presented, and acted on.
Four fixed categories backed by directories on disk, paginated and sorted
listings, protected image serving, and the interaction model that exposes each
poster's actions on both pointer and touch devices.

This capability owns *browsing and presenting* posters. Posters enter the
library through `plex-import`; operating on one poster is `poster-editing`;
filtering the listing is `search`.
## Requirements
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

### Requirement: Article-aware ordering
The system SHALL sort posters by title, ignoring a leading article ("a", "an",
"the") when `IGNORE_ARTICLES_IN_SORT` is enabled.

Title comparison SHALL be digit-aware: a run of digits within a title SHALL be
ordered by its numeric value rather than character by character, so that
"Season 2" precedes "Season 10" and "Ocean's 8" precedes "Ocean's 11". This
SHALL apply wherever the system orders posters by title.

Digit-awareness SHALL affect ordering only. It SHALL NOT change any title shown
to the user, any filename on disk, or any stored record.

A run of digits longer than the system can compare numerically SHALL fall back
to character-by-character comparison rather than being ordered incorrectly. The
supported length SHALL comfortably exceed any number occurring in real media
titles.

Two titles that differ only by leading zeros in a digit run (such as "Season 01"
and "Season 1") compare as equal under digit-aware ordering. The system SHALL
break such a tie deterministically, so that repeated listings of the same
posters produce the same order.

#### Scenario: Leading article ignored in sort
- **WHEN** `IGNORE_ARTICLES_IN_SORT` is true and a poster titled "The Matrix"
  is sorted among others
- **THEN** it is ordered as if titled "Matrix"

#### Scenario: Numbers order by value
- **WHEN** a show's season posters from Season 1 through Season 10 or beyond are
  listed
- **THEN** they are ordered Season 1, Season 2, Season 3 and so on, with
  Season 10 after Season 9 rather than after Season 1

#### Scenario: Numbers within a title order by value
- **WHEN** posters titled "Ocean's 8" and "Ocean's 11" are listed
- **THEN** "Ocean's 8" is ordered before "Ocean's 11"

#### Scenario: Digit-awareness composes with article stripping
- **WHEN** `IGNORE_ARTICLES_IN_SORT` is true and the posters being ordered have
  both leading articles and numbers in their titles
- **THEN** the leading article is ignored and the numbers still order by value

#### Scenario: Displayed titles are unaffected
- **WHEN** posters are ordered by a digit-aware title
- **THEN** every caption, tooltip, and alt text shows the poster's title exactly
  as it did before, with no padding or other ordering artefact visible

#### Scenario: Leading zeros tie deterministically
- **WHEN** two posters' titles differ only by a leading zero in a number, such as
  "Season 01" and "Season 1"
- **THEN** they are ordered deterministically, so listing the same posters again
  produces the same order

### Requirement: Auth-protected image serving
The system SHALL serve poster image files only to authenticated users, with
caching headers, and SHALL never resolve a request outside the posters
directory. The rendered URL for a poster SHALL carry a version marker derived
from the file's modification time, so that replacing the file yields a different
URL. The system SHALL identify the requested image from the path alone and
SHALL ignore the version marker when serving.

#### Scenario: Authenticated image request succeeds
- **WHEN** an authenticated user requests an existing poster image
- **THEN** the system responds with the image bytes and an image content type

#### Scenario: Version marker is ignored when serving
- **WHEN** a poster image is requested with a version marker that is absent,
  outdated, or unrecognized
- **THEN** the system serves the poster currently on disk rather than failing or
  serving an earlier image

#### Scenario: Path traversal is refused
- **WHEN** a request for a poster image contains path separators or traversal
  sequences in the filename
- **THEN** the system responds with HTTP 404 and serves no file outside the
  posters directory

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

#### Scenario: Caption omits the library token
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

#### Scenario: Tooltip also omits the library token
- **WHEN** a user hovers a poster
- **THEN** the tooltip shows exactly the same title as the caption, library token
  included nowhere

#### Scenario: The delete confirmation names the poster the same way
- **WHEN** a user deletes a poster whose caption shows "Louis and the Nazis (2003)"
- **THEN** the confirmation names that poster identically, rather than showing the
  raw filename-derived title with its library token

#### Scenario: Non-Plex poster keeps its full title
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

#### Scenario: Poster cards outside the gallery
- **WHEN** a page other than the gallery renders poster cards, such as the
  orphans page
- **THEN** each poster image fades in once loaded, rather than staying invisible
  behind a placeholder that animates indefinitely

#### Scenario: Image that fails to load
- **WHEN** a poster image request fails
- **THEN** the placeholder animation stops rather than continuing to suggest the
  image is still loading

### Requirement: Poster actions on pointer devices
On pointer (hover-capable) devices the gallery SHALL reveal a poster's action
overlay on hover, without the actions being clipped or hidden off-card, and
clicking the poster itself SHALL open it full screen. Each action control in the
overlay SHALL present a distinct hover and keyboard-focus state so the control
about to be activated is clearly indicated before it is clicked.

#### Scenario: Hover reveals actions on desktop
- **WHEN** a user hovers a poster on a pointer device
- **THEN** the action overlay is shown

#### Scenario: Action control indicates hover and focus
- **WHEN** a user moves the pointer over, or keyboard-focuses, one of the overlay
  action controls
- **THEN** that control changes appearance to indicate it is the one that will be
  activated

#### Scenario: Clicking opens full screen on desktop
- **WHEN** a user clicks a poster (not one of its action buttons) on a pointer
  device
- **THEN** the poster opens full screen

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
  Plex when linked, download, related posters, full screen, delete) with its title

#### Scenario: Sheet heading matches the caption
- **WHEN** the action sheet opens for a Plex-imported poster whose caption shows
  "Louis and the Nazis (2003)"
- **THEN** its heading shows "Louis and the Nazis (2003)" — the same text, with no
  library appended

#### Scenario: No tap-through
- **WHEN** a user taps a poster on a touch device
- **THEN** the tap opens the sheet only and does not trigger any poster action

#### Scenario: Dismissing the sheet
- **WHEN** the user taps outside the sheet, presses Escape, or runs one of its
  actions
- **THEN** the sheet closes

### Requirement: Always-available Plex actions
For every poster linked to a Plex item, the gallery SHALL always offer Send to
Plex and Fetch from Plex, independent of whether the poster was recently changed.

#### Scenario: Linked poster always shows Plex actions
- **WHEN** the gallery renders a poster that is linked to a Plex item
- **THEN** both Send to Plex and Fetch from Plex are available for it

### Requirement: Fullscreen poster view
The system SHALL let a user view any gallery poster full screen.

While the full-screen image has not yet resolved, the system SHALL show the same
subtle placeholder animation used by poster cards, sized and positioned where the
poster will appear, and SHALL fade the poster in once it resolves. The placeholder
SHALL stop whether the image loads or fails, so a poster that cannot be fetched
never leaves an animation running indefinitely.

The placeholder SHALL hold one position for as long as it is shown. A browser
gives an image its box as soon as it knows the image's dimensions — well before
the image has arrived — so a placeholder that shares layout with the loading image
is displaced by it, briefly on a fast connection and for the whole download on a
slow one. Waiting is precisely when the view must be still.

Because one full-screen view is reused for every poster rather than being created
per poster, opening a different poster SHALL return the view to its unresolved
state — placeholder showing, poster hidden — rather than displaying the previous
poster, or treating the new poster as already loaded because the view had
resolved once before.

#### Scenario: Open a poster full screen
- **WHEN** a user activates a poster in the gallery
- **THEN** the system displays that poster in a full-screen view

#### Scenario: Full-screen image has not loaded yet
- **WHEN** a poster is opened full screen and its image has not yet resolved
- **THEN** the placeholder animation is shown where the poster will appear,
  rather than an empty backdrop, and the poster fades in once it resolves

#### Scenario: Placeholder position when width is the limiting dimension
- **WHEN** a poster is opened full screen on a narrow screen, where the poster is
  sized by the available width rather than the available height
- **THEN** the placeholder occupies the position the poster itself will occupy,
  rather than sitting above it and appearing to drop into place when it arrives

#### Scenario: Placeholder stays put while the image downloads
- **WHEN** a poster is opened full screen over a slow connection, so its image is
  being downloaded for an extended period
- **THEN** the placeholder stays where it was first drawn for the whole wait,
  rather than shifting as the image being loaded takes up room

#### Scenario: Full-screen image fails to load
- **WHEN** a poster opened full screen fails to load
- **THEN** the placeholder animation stops rather than continuing to suggest the
  image is still loading

#### Scenario: Reopening the view on a different poster
- **WHEN** a user opens one poster full screen, closes it, and opens a different
  poster
- **THEN** the view starts unresolved again — showing the placeholder until the
  newly opened poster resolves — and never shows the previously viewed poster

### Requirement: Delete a poster
The system SHALL allow an authenticated user to delete a poster from a category.
Deleting a poster SHALL remove both the image file and every Plex item mapping
that points at that poster, so a deleted poster leaves no residual mapping behind.

#### Scenario: Poster is deleted
- **WHEN** an authenticated user deletes an existing poster
- **THEN** the system removes the image file and it no longer appears in the
  gallery

#### Scenario: Mapping is cleared with the file
- **WHEN** an authenticated user deletes a poster that was imported from Plex
- **THEN** the system also removes every Plex item mapping row for that poster's
  category and filename
- **AND** no orphan entry can later be produced from that removed mapping

### Requirement: Background poster updates
The gallery SHALL apply poster actions — change, send to Plex, fetch from Plex,
and delete — without reloading the whole page, refreshing only the grid and
reporting the outcome.

A reported outcome SHALL stay on screen long enough to be read, in proportion to
how much it says. A one-line acknowledgement and an explanation of why an
operation could not finish SHALL NOT be given the same dwell time, since the
messages worth reading are the long ones.

#### Scenario: Updating a poster refreshes only the grid
- **WHEN** a user changes, sends, fetches, or deletes a poster
- **THEN** the grid reflects the result and a short confirmation is shown, and
  the surrounding page is not reloaded

#### Scenario: A long outcome message is readable
- **WHEN** an operation reports something the user has to act on, such as a
  change that could not be sent to Plex because the item may be orphaned
- **THEN** the message stays on screen materially longer than a bare
  acknowledgement like "Poster updated."

#### Scenario: Works without JavaScript
- **WHEN** a user performs a poster action with JavaScript disabled
- **THEN** the action still completes via a normal form submission

### Requirement: Modal confirmations
Destructive actions SHALL ask for confirmation through an in-app modal rather
than a native browser dialog. An action counts as destructive when it overwrites
or removes something the user cannot get back by undoing — this includes
overwriting the artwork on a Plex item and overwriting a stored poster, not only
deleting a file.

The confirmation SHALL present the confirming action's own heading, its own
action label, and its own button emphasis, so the dialog states which action is
about to run rather than a single generic wording. The emphasis SHALL reserve the
destructive (red) treatment for actions that remove a poster, so an overwrite is
not offered under a delete-coloured button.

On a small screen a confirmation SHALL be presented as a tray, and SHALL meet the
same dismissal guarantees as every other tray: a working drag-to-dismiss whose
drag region is a real element distinct from any scrolling content, and a
tappable backdrop. Its own Cancel action SHALL remain, but SHALL NOT be the only
way to decline. Dismissing a confirmation by any route without choosing its
confirming action SHALL leave the destructive action untaken.

Declining SHALL unwind exactly one layer. A confirmation raised from inside a
tray SHALL leave that tray open and unchanged when it is declined by any route —
Cancel, backdrop, close control, Escape, or drag — returning the user to the
actions they were choosing from. The raising tray SHALL be closed only when the
confirming action is actually taken.

A card action that requires confirmation SHALL do so identically whether it was
started from the card's hover overlay or from the mobile action tray.

#### Scenario: Confirm deleting a poster
- **WHEN** a user chooses to delete a poster
- **THEN** a confirmation modal appears and the poster is deleted only if the
  user confirms

#### Scenario: Confirm deleting all orphans
- **WHEN** a user chooses to delete all orphaned posters
- **THEN** a confirmation modal appears and the orphans are deleted only if the
  user confirms

#### Scenario: Confirm overwriting through a Plex action
- **WHEN** a user chooses Send to Plex or Fetch from Plex for a linked poster
- **THEN** a confirmation modal appears naming that action, and the operation
  runs only if the user confirms

#### Scenario: The dialog names the action being confirmed
- **WHEN** a confirmation is shown for an action other than deleting
- **THEN** its heading and its confirming button describe that action rather than
  deletion, and the button does not use the destructive treatment

#### Scenario: Confirming an action started from the mobile tray
- **WHEN** a user taps a poster on a small screen and chooses a confirmed action
  from the action tray
- **THEN** the same confirmation is shown, and the action runs only if the user
  confirms

#### Scenario: Declining returns the user to the action tray
- **WHEN** a user chooses a confirmed action from a poster's action tray on a
  small screen and then cancels the confirmation
- **THEN** the confirmation closes, the action tray is still open showing that
  poster's actions, and nothing has been deleted, overwritten, or sent

#### Scenario: Escape closes the confirmation before the tray
- **WHEN** a user presses Escape while a confirmation raised from a tray is open
- **THEN** only the confirmation closes; a second Escape closes the tray

#### Scenario: Confirming closes the tray that offered the action
- **WHEN** a user chooses a confirmed action from the action tray and confirms it
- **THEN** the tray closes and the action runs

#### Scenario: Dismissing a confirmation tray by dragging it down
- **WHEN** a user drags a confirmation tray downward by its handle on a small
  screen
- **THEN** the confirmation is dismissed and the destructive action is not taken

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
(mixed, not grouped by category), applying the same article-aware, digit-aware
ordering used elsewhere. When two posters have the same sort title, the system
SHALL break the tie by category in the order Movies, TV Shows, TV Seasons,
Collections, so the order is stable and deterministic.

#### Scenario: Titles are mixed across types
- **WHEN** the All view lists posters of different categories
- **THEN** they are ordered together by title rather than grouped into
  per-category blocks

#### Scenario: Equal titles break ties by category
- **WHEN** two posters in the All view share the same sort title but belong to
  different categories
- **THEN** they are ordered by category in the sequence Movies, TV Shows,
  TV Seasons, Collections

#### Scenario: Numbers order by value in the aggregate view
- **WHEN** the All view lists posters whose titles contain numbers
- **THEN** those numbers order by value, on the same terms as within a single
  category

### Requirement: Type badge in the aggregate view
In the All view the system SHALL display a small type badge on each poster
indicating its category (Movie, TV Show, TV Season, or Collection). The badges
SHALL be styled from the application's own color palette rather than unrelated
stock colors, while keeping the four types visually distinguishable from one
another. On pointer (hover-capable) devices the badge SHALL hide while the
poster's action overlay is shown, so it does not obscure or compete with the
actions. On touch devices, where the overlay is not used, the badge SHALL remain
visible. Type badges SHALL appear only in the All view; single-category views
SHALL NOT show them.

#### Scenario: Badge shows the poster's type in All
- **WHEN** the All view renders a poster
- **THEN** a badge on the poster identifies its category as Movie, TV Show,
  TV Season, or Collection

#### Scenario: Badges use the app palette
- **WHEN** the All view renders posters of different types
- **THEN** each badge is tinted from the application's theme palette and the four
  types remain distinguishable from one another

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
every poster action — change, send to Plex, fetch from Plex, download, related
posters, full screen, and delete — SHALL act on the poster's own category rather
than a single page-wide category. The change-poster modal opened for a poster
SHALL operate on that poster's category.

Related posters is the one action whose result is not confined to the poster's
category: it acts on the poster's own recorded title, which is read from that
poster's category, and then presents its results in the All view. Reading the
title from the wrong category would name the wrong work, so the keying matters
here for the same reason it matters everywhere else.

#### Scenario: An action targets the poster's own category
- **WHEN** a user runs an action on a poster in the All view
- **THEN** the action is applied to that poster within its own category, even if
  another category contains a poster with the same filename

#### Scenario: Change modal uses the poster's category
- **WHEN** a user opens the change-poster modal for a poster in the All view and
  applies a new image
- **THEN** the change is applied to that poster within its own category

#### Scenario: Related posters reads the title from the poster's own category
- **WHEN** a user activates Related posters on a poster in the All view, and
  another category holds a poster with the same filename
- **THEN** the query is the recorded title of the poster that was activated

### Requirement: Sort order selection
The system SHALL support three gallery sort fields — **Alphabetical**
(article-aware title order), **Date added** (by when each poster's media was added
to Plex) and **Release** (by the release year recorded for each poster's Plex
item) — each in either direction, giving six effective orders: titles ascending
(A–Z), titles descending (Z–A), date added newest first, date added oldest first,
release latest first, and release earliest first. The selected order SHALL apply
to both a single category and the aggregate `all` view.

Each field SHALL have a default direction: ascending for Alphabetical, and
descending for both fields that order by time — Date added leading with the most
recently added, Release leading with the most recently released.

#### Scenario: Alphabetical order lists by title
- **WHEN** the effective sort order is Alphabetical ascending
- **THEN** the gallery lists posters by title using the existing article-aware
  ordering, from A to Z

#### Scenario: Alphabetical descending reverses the title order
- **WHEN** the effective sort order is Alphabetical descending
- **THEN** the gallery lists posters by the same article-aware title ordering
  reversed, from Z to A

#### Scenario: Date-added order lists newest first
- **WHEN** the effective sort order is Date added descending
- **THEN** the gallery lists posters by their Plex "added at" timestamp with the
  most recently added poster first

#### Scenario: Date-added ascending lists oldest first
- **WHEN** the effective sort order is Date added ascending
- **THEN** the gallery lists posters by their Plex "added at" timestamp with the
  least recently added poster first

#### Scenario: Release order lists latest first
- **WHEN** the effective sort order is Release descending
- **THEN** the gallery lists posters by their recorded release year with the
  most recently released first

#### Scenario: Release ascending lists earliest first
- **WHEN** the effective sort order is Release ascending
- **THEN** the gallery lists posters by their recorded release year with the
  earliest first

#### Scenario: Sort order applies to the aggregate view
- **WHEN** a user views the `all` slug with any of the six orders
- **THEN** posters from every category are merged and ordered together under that
  order

#### Scenario: Ordering stays deterministic in either direction
- **WHEN** two posters compare equal on the selected field in a mixed listing
- **THEN** the tie is broken so the listing is stable, and reversing the
  direction does not leave equal posters in an arbitrary order

### Requirement: Default sort order configuration
The system SHALL read a preferred default sort order from the `DEFAULT_SORT`
environment variable, accepting `alphabetical`, `date_added` or `release`, and
SHALL fall back to Alphabetical when the variable is unset, empty, or holds an
unrecognized value. Each accepted value SHALL select its field in that field's
default direction, so `alphabetical` means A–Z, `date_added` means newest first,
and `release` means latest first.

Where an order has a slug for each direction, the **unsuffixed slug SHALL name
the field's default direction** and the suffixed one its reverse, so a value that
names a field alone always resolves to the order that field's button rests in.

#### Scenario: Default is Alphabetical when unset
- **WHEN** `DEFAULT_SORT` is not set
- **THEN** the gallery orders posters alphabetically from A to Z until the user
  chooses otherwise

#### Scenario: Date-added set as the install default
- **WHEN** `DEFAULT_SORT` is `date_added`
- **THEN** the gallery orders posters by date added, newest first, until the user
  chooses otherwise

#### Scenario: Release set as the install default
- **WHEN** `DEFAULT_SORT` is `release`
- **THEN** the gallery orders posters by release, latest first, until the user
  chooses otherwise

#### Scenario: Unrecognized value falls back to Alphabetical
- **WHEN** `DEFAULT_SORT` holds a value none of the accepted orders name
- **THEN** the system uses Alphabetical ascending rather than raising an error

### Requirement: Sort order toggle
The system SHALL present a control in the gallery toolbar with one button per
sort field. Activating the button of the field that is already active SHALL
reverse that field's direction; activating another field's button SHALL switch to
that field. The resulting order SHALL persist across navigation within the
session, taking precedence over `DEFAULT_SORT`.

When the user has not made a choice, the control SHALL reflect the configured
default. The buttons SHALL be rendered in a fixed order so the control does not
reshuffle itself as the user sorts, and the same buttons SHALL be offered
everywhere the control appears, whatever view is being shown.

#### Scenario: Toggling re-orders the gallery
- **WHEN** a user selects a sort order from the control
- **THEN** the gallery re-renders its listing in that order

#### Scenario: Activating the active field reverses it
- **WHEN** the gallery is ordered A–Z and the user activates the title button
- **THEN** the gallery is reordered Z–A

#### Scenario: Reversing again restores the original direction
- **WHEN** the gallery is ordered Z–A and the user activates the title button
- **THEN** the gallery is reordered A–Z

#### Scenario: Activating the other field switches to it
- **WHEN** the gallery is ordered Z–A and the user activates the date-added
  button
- **THEN** the gallery is ordered by date added

#### Scenario: Activating a third field switches to it
- **WHEN** the gallery is ordered Z–A and the user activates the release button
- **THEN** the gallery is ordered by release

#### Scenario: Every field is offered in every view
- **WHEN** the sort control is shown in the full library and again in a set view
- **THEN** it offers the same buttons in the same order in both

#### Scenario: Choice persists across navigation
- **WHEN** a user selects date added and then navigates to another category
- **THEN** that category is also ordered by date added without re-selecting it

#### Scenario: Direction persists across navigation
- **WHEN** a user selects Z–A and then navigates to another category
- **THEN** that category is also ordered Z–A without re-selecting it

#### Scenario: Toggle reflects the configured default before any choice
- **WHEN** a user opens the gallery having made no sort selection this session
- **THEN** the control indicates the field and direction given by `DEFAULT_SORT`

### Requirement: Date-added fallback for posters without a Plex timestamp
When sorting by Date added in either direction, the system SHALL order posters
that have no stored Plex "added at" timestamp using their file modification time,
so every poster holds a stable position in the ordering.

#### Scenario: Unmapped poster still has a position
- **WHEN** the gallery is ordered by date added in either direction and a poster
  has no Plex "added at" value
- **THEN** the poster is ordered by its file modification time rather than
  omitted or grouped unpredictably

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

### Requirement: Import and orphans run inside their trays on small screens
When the import or orphans experience is opened in a tray on a phone, it SHALL be
fully contained: running an import or deleting orphans SHALL happen in place
without navigating away, progress SHALL be shown contained within the tray rather
than as a full-screen overlay, and the result SHALL be reported to the user. After
an import completes the gallery SHALL reflect the newly imported posters, and after
orphans are deleted the gallery SHALL reflect their removal.

Opening the orphans tray SHALL scan for orphans, every time it is opened and not
only the first time. Each open SHALL show the tray's loading state and then the
current result. A previous scan's results SHALL NOT be presented on a later open,
because an orphan list is a statement about what Plex contains right now, and a
stale one invites deleting a poster that is no longer an orphan. Reopening the
tray is the means of refreshing it; no separate refresh control is required for
this purpose.

The import tray's contents MAY be fetched once and reused for the remainder of the
page session. This difference from the orphans tray is deliberate: the import tray
presents a configuration form, whose correctness does not decay, whereas the
orphans tray presents the result of a scan, which does.

#### Scenario: Import completes without leaving the tray
- **WHEN** a user submits the import form inside the import tray
- **THEN** progress is shown within the tray, the import runs without navigating to
  another page, the result is reported, and the gallery reflects any new posters

#### Scenario: Deleting orphans stays within the tray
- **WHEN** a user deletes one or all orphans from the orphans tray
- **THEN** progress is shown within the tray, the deletion happens without
  navigating away, and the result is reported

#### Scenario: Tray progress is contained
- **WHEN** an import or orphan operation is running inside a tray
- **THEN** its progress indicator is confined to the tray rather than covering the
  whole screen

#### Scenario: Reopening the orphans tray scans again
- **WHEN** a user opens the orphans tray, closes it by any means, and opens it again
- **THEN** the tray shows its loading state and then the result of a fresh scan,
  not the result of the previous one

#### Scenario: An orphan resolved since the last scan is no longer listed
- **WHEN** a poster was listed as an orphan, its media is restored in Plex, and the
  user reopens the orphans tray
- **THEN** that poster is no longer listed as an orphan

#### Scenario: Reopening does not accumulate handlers
- **WHEN** a user opens and closes the orphans tray repeatedly and then confirms a
  deletion
- **THEN** exactly one deletion is performed

### Requirement: Sort selection via a tray on small screens
On a narrow screen the sort control SHALL be presented as a tray opened from a
sort trigger in the toolbar, offering the same sort orders (Alphabetical and Date
added) as the desktop control and indicating the current order, so the toolbar
stays uncluttered.

#### Scenario: Sort trigger opens the sort tray
- **WHEN** a user on a narrow screen activates the sort trigger
- **THEN** a tray opens offering Alphabetical and Date added, with the current
  order indicated

#### Scenario: Choosing an order sorts the gallery
- **WHEN** the user chooses a sort order from the tray
- **THEN** the gallery is ordered accordingly

#### Scenario: Sorting stays on the current view
- **WHEN** the user changes the sort order after switching to a category
- **THEN** the gallery re-sorts within that same category rather than reverting to
  the aggregate All view

### Requirement: Import from Plex via a tray on small screens
On a touch device viewing the gallery, choosing Import from Plex SHALL open the
import experience in a tray over the gallery rather than navigating to a separate
page, loading the same Plex import form used by the import page. On a pointer
device, or on a page without the gallery, Import from Plex SHALL navigate to the
import page as before.

#### Scenario: Import opens in a tray on a phone
- **WHEN** a user on a touch device taps Import from Plex from the gallery menu
- **THEN** the import form opens in a tray over the gallery without navigating away

#### Scenario: Import navigates on desktop
- **WHEN** a user on a pointer device chooses Import from Plex
- **THEN** the import page opens as a normal page

#### Scenario: Submitting the import still works from the tray
- **WHEN** a user completes and submits the import form inside the tray
- **THEN** the import runs and the user is returned to the gallery with the result
  reported

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

### Requirement: Deferred loading indication for in-place view changes
The gallery replaces its results in place for a category tab switch, a search, a
cleared search, a pagination move, a history navigation, and a tray-triggered
refresh. While such a view change is in flight the gallery MAY dim its results
to indicate loading, but that indication SHALL be deferred: it SHALL NOT be
applied until the view change has been in flight for at least a grace period of
200 ms. A view change that completes within the grace period SHALL render no
loading indication at all — its results are replaced directly, with the gallery
never dimmed.

Once the indication has been applied it SHALL remain visible for a minimum of
300 ms, even if the results arrive sooner, so that a view change which only just
crosses the grace period does not produce a dim-and-restore flash of its own.
The replacement of the results SHALL NOT be delayed by this hold: new posters
are shown as soon as they are available, and only the dimming persists for the
remainder of the minimum.

A view change that is superseded, fails, or completes SHALL leave no loading
indication behind and SHALL NOT cause a later, unrelated view to dim.

This deferral governs view changes only. It SHALL NOT govern operations that
change stored data — importing from Plex, scanning for or deleting orphans, or
applying a found poster — whose progress is indicated immediately, because they
have no fast path that a deferral would protect against flickering.

#### Scenario: A fast tab switch never dims

- **WHEN** a user switches category tabs and the new view's results arrive
  within the grace period
- **THEN** the gallery is never dimmed at any point during the switch
- **AND** the new posters replace the old ones directly

#### Scenario: A slow view change dims

- **WHEN** a view change is still in flight after the grace period has elapsed
- **THEN** the gallery dims to indicate that it is loading

#### Scenario: A dimmed gallery is held long enough to be read

- **WHEN** the loading indication has been applied and the results arrive
  shortly afterwards
- **THEN** the dimming remains for the remainder of its minimum visible duration
  rather than clearing immediately
- **AND** the new posters are shown as soon as they arrive, without waiting for
  the dimming to clear

#### Scenario: Every in-place navigation behaves the same way

- **WHEN** a user searches, clears a search, moves to another page, navigates
  back or forward, or triggers a refresh from the import or orphans tray
- **THEN** the loading indication is deferred and held on exactly the same terms
  as a category tab switch

#### Scenario: A superseded view change leaves nothing dimmed

- **WHEN** a user starts one view change and then starts another before the
  first has finished
- **THEN** the pending indication for the abandoned view change does not dim the
  gallery afterwards
- **AND** the gallery ends in an undimmed state once the surviving view change
  has settled

#### Scenario: A failed view change clears the indication

- **WHEN** a view change fails
- **THEN** any loading indication it applied is cleared rather than left in place

#### Scenario: A mutation is not deferred

- **WHEN** a user starts an operation that changes stored data, such as applying
  a found poster
- **THEN** its progress is indicated immediately rather than after the grace
  period that applies to view changes

### Requirement: Consistent tray dismissal on small screens
Every overlay presented as a bottom tray on a small screen — the poster action
sheet, the app menu, sort, import, orphans, Change Poster, and confirmation
dialogs — SHALL be dismissible in the same two ways: by dragging the tray
downward, and by tapping the backdrop outside it. Trays SHALL NOT carry a close
button on a small screen; the grab handle and the backdrop are the whole
dismissal story, matching the native-app idiom the mobile experience follows.

Because there is no close button to fall back on, both of those routes SHALL be
reliable.

The region that accepts the dismissal drag SHALL be a real, hit-testable element
— a grab handle and the tray's heading — and SHALL be a distinct element from
the tray's scrolling region, so that dragging to dismiss and scrolling the
tray's contents can never be the same gesture on the same element. A tray whose
grab handle is drawn decoratively but cannot be targeted by touch does not
satisfy this requirement.

A downward drag that begins on a tray's handle or heading SHALL be claimed by
the tray for the duration of that gesture. It SHALL NOT scroll the page behind
the tray, and SHALL NOT trigger the browser's pull-to-refresh. A gesture that
begins on the drag region SHALL either dismiss the tray or return it to rest;
it SHALL NOT be abandoned partway by the browser taking the gesture over.

A tray SHALL leave a usable expanse of backdrop above it. No tray SHALL be
taller on a small screen than the established tray height, so the backdrop
remains a tap target of the same size on every tray rather than shrinking to a
sliver on the tallest ones.

Every tray SHALL also remain dismissible by pressing Escape, for users on a
device with a keyboard.

#### Scenario: Change Poster closes by dragging down
- **WHEN** a user opens Change Poster from the poster action tray on a small
  screen and drags its grab handle downward
- **THEN** the tray follows the drag and is dismissed, in the same way the poster
  action tray is

#### Scenario: A tall tray still leaves backdrop to tap
- **WHEN** a tray is open whose contents would otherwise fill the screen, such as
  Change Poster showing Find Posters results
- **THEN** the tray is no taller than any other tray, and the backdrop above it
  can be tapped to dismiss it

#### Scenario: Dismissal gesture is not stolen by the page
- **WHEN** a user drags downward on a tray's grab handle or heading while the
  page behind it is scrolled to the top
- **THEN** the tray moves with the drag, the page behind does not scroll, and the
  browser does not begin a pull-to-refresh

#### Scenario: Drag region is separate from the scrolling region
- **WHEN** a tray's contents are long enough to scroll
- **THEN** dragging the handle or heading dismisses the tray, and scrolling the
  tray's body scrolls its contents, with neither gesture performing the other

### Requirement: Dialogs raised from inside a tray appear above it
A confirmation dialog opened by an action taken inside a tray SHALL be displayed
above that tray, not behind it, so the user can see and answer the question they
were just asked.

#### Scenario: Confirming a deletion started in the orphans tray
- **WHEN** a user chooses to delete an orphan, or all orphans, from within the
  orphans tray on a small screen
- **THEN** the confirmation is shown above the orphans tray and can be read and
  answered without dismissing that tray first

### Requirement: The page behind an open overlay does not scroll
While any tray, confirmation dialog, or the fullscreen poster viewer is open, the
page behind it SHALL NOT scroll. This SHALL hold for a gesture that begins on the
overlay's backdrop as well as one that begins on the overlay itself.

When the overlay is dismissed, the page SHALL be restored to exactly the scroll
position it held when the overlay opened. An exact restoration is required rather
than an approximate one because the gallery loads further posters in response to
approaching the end of the page; a restored position that differs from the
original would cause the gallery to append posters the user never scrolled to.

This requirement SHALL apply on iOS Safari as well as on other mobile browsers.

#### Scenario: Page does not scroll behind an open tray
- **WHEN** a user drags on the backdrop beside or above an open tray
- **THEN** the page behind the tray does not scroll and no pull-to-refresh begins

#### Scenario: Scroll position survives a tray
- **WHEN** a user scrolls partway down the gallery, opens a tray, and closes it
- **THEN** the gallery is at the same scroll position it was before the tray
  opened, and no additional posters have been appended as a result

#### Scenario: Typing in a tray does not displace the page
- **WHEN** a user opens Change Poster, focuses its URL field so the on-screen
  keyboard appears, then dismisses the keyboard and closes the tray
- **THEN** the gallery is left at the scroll position it held before the tray
  opened, not offset by the keyboard's appearance

### Requirement: Paging returns the view to the top
When a user activates a pagination link, the system SHALL return the view to the
top of the gallery so the destination page begins at its first poster rather than
wherever the previous page left the viewport. The return SHALL be animated as a
smooth scroll, and SHALL begin when the link is activated rather than waiting for
the destination page to render. When the user's system preference asks for
reduced motion, the view SHALL still be returned to the top, but without the
animation.

This requirement concerns only the pagination controls, which are shown on a
pointer/desktop screen; a narrow screen replaces them with infinite scroll and
its viewport is never moved.

#### Scenario: Paging scrolls back to the top

- **WHEN** a user scrolled down to the pagination control follows a page number,
  a previous/next stepper, or a first/last control
- **THEN** the view returns to the top of the gallery, so the destination page is
  seen from its first poster

#### Scenario: The return is animated

- **WHEN** the view is returned to the top after a pagination link is activated
- **AND** the user has expressed no reduced-motion preference
- **THEN** the movement is a smooth scroll rather than an instant jump

#### Scenario: The scroll starts before the new page arrives

- **WHEN** a pagination link is activated
- **THEN** the view begins returning to the top immediately, without waiting for
  the destination page's posters to be fetched and rendered

#### Scenario: Reduced motion skips the animation

- **WHEN** a user whose system preference asks for reduced motion activates a
  pagination link
- **THEN** the view is at the top of the gallery, and it was moved there without
  an animation

#### Scenario: Infinite scroll is unaffected

- **WHEN** a user on a narrow screen reaches the bottom and the next page of
  posters is appended
- **THEN** the view is not moved, and the appended posters continue below the
  ones already on screen

### Requirement: A completed import leaves the import tray open and reset

When an import run from inside the import tray finishes, the tray SHALL remain
open and SHALL present the import form returned to its initial state: no content
type selected, no libraries selected, the "re-download unchanged posters" option
cleared, and the tray's progress indicator dismissed. The user SHALL be able to
begin another import immediately, starting from step 1, without reopening the
tray.

Importing is inherently repetitive — the form imports one content type at a
time, so populating a library means running it once per type — and closing the
tray after each run charges the user a reopen for every repeat.

The result of the completed import SHALL be reported, and the gallery behind the
tray SHALL reflect the newly imported posters.

An import that fails SHALL also leave the tray open, but SHALL NOT reset the
form: the user's selections are what they would need to re-enter to retry, so
they are preserved and only the failure is reported.

This requirement applies to the import tray alone. It SHALL NOT be read as
governing any other tray on a small screen — orphans, a poster's action tray,
sort, the app menu, and confirmations each keep the dismissal behavior their own
requirements define.

#### Scenario: The tray stays open after an import

- **WHEN** a user runs an import from inside the import tray and it completes
- **THEN** the tray is still open, the progress indicator is gone, the result is
  reported, and the gallery behind the tray reflects the newly imported posters

#### Scenario: The form is back at step 1

- **WHEN** an import completes inside the tray
- **THEN** the form in the still-open tray shows no content type selected, no
  libraries selected, and the re-download option cleared, so only step 1 is
  presented

#### Scenario: Running a second import without reopening the tray

- **WHEN** a user completes an import, then selects a different content type and
  its libraries in the same open tray and submits again
- **THEN** the second import runs in place and reports its own result, without
  the tray having been reopened

#### Scenario: A failed import keeps the user's selections

- **WHEN** an import run from inside the tray fails
- **THEN** the tray stays open, the failure is reported, and the content type and
  libraries the user chose are still selected so the import can be retried

#### Scenario: Dismissing the tray still discards the form

- **WHEN** a user closes the import tray by dragging it down, tapping the
  backdrop, or pressing Escape, and then reopens it
- **THEN** the tray opens on a freshly loaded form at step 1, as before

#### Scenario: Other trays keep their own dismissal behavior

- **WHEN** a user deletes orphans in the orphans tray, or confirms an action from
  a poster's action tray
- **THEN** those trays follow their own requirements, not the import tray's
  stay-open rule

### Requirement: Responsive gallery layout and pinned controls
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — including the All tab, five in total — fit on screen without
scrolling or crowding, toolbar controls fit their row, and posters are sized so
at least two fit per row on a phone.

The gallery view SHALL stay focused on the posters at every width: the secondary
navigation actions (Poster Wall, Import from Plex, Orphans, Settings, Support
Development) SHALL NOT be presented in the gallery toolbar. On a pointer/desktop
screen they
SHALL be presented in the shared page header instead (see "Secondary navigation in
the desktop page header" in `application-shell`); on a narrow screen they SHALL be
presented in the app-wide menu tray. On a narrow screen sort SHALL additionally
move out of the toolbar into its own tray, leaving the mobile toolbar to search
plus a sort trigger above the poster grid. On a pointer/desktop screen the inline
sort control SHALL remain in the toolbar beside search.

The gallery's controls SHALL remain pinned to the top of the viewport as the
gallery scrolls, so they are reachable at any scroll position without returning to
the top of the page. What is pinned differs by width. On a narrow screen the
toolbar alone is pinned — search and the sort trigger — because the category tabs
are already permanently on screen as a bottom tab bar. On a pointer/desktop screen
the category tabs and the toolbar SHALL be pinned together as a single block, so
switching category, searching, and re-sorting are all reachable while scrolled.

The pinned controls SHALL be drawn on a surface of their own that spans the full
width of the region the poster grid occupies, so no poster is ever visible beside
them at full strength. That surface MAY be translucent, so posters passing behind
it show through blurred and dimmed rather than being hidden outright (see
"Translucent floating chrome" in `visual-design`). Whether it is translucent MAY
differ by screen width, since what reads as chrome on a narrow screen and on a
wide one is not the same thing.

Where the surface is translucent, the tint SHALL be strong enough that every
control on it — each category tab label, the search field and its text, and the
sort control — stays fully legible against any poster that may pass behind it,
and the blur SHALL be strong enough that no poster remains individually
recognisable through it. Where it is opaque, no poster SHALL be visible behind it
at all.

Either way, a poster SHALL NOT appear at full strength anywhere within the pinned
block's bounds, including at its left and right edges, whether it is passing
behind the block or beside it.

The pinned controls SHALL layer above the poster grid and below every overlay —
the bottom tab bar, trays, dialogs, and the fullscreen viewer — so an open overlay
always covers them.

Pinning applies only while the page can scroll. When the results are too short to
fill the viewport — a search with no matches, most obviously — the controls SHALL
rest in their normal position below the topbar. This is the same state as an
unscrolled page and is not a failure to pin.

The toolbar SHALL stay pinned to the visible area while an on-screen keyboard is
open, so searching from part-way down the gallery does not push it out of view.
Where a browser offers the choice, the application SHALL ask that the keyboard
resize the layout viewport rather than only the visual one, since that is the
coordinate space the pinned toolbar and the fixed bottom tab bar resolve against.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs all fit on screen without overflowing the page width or
  crowding the other controls (see the native-style bottom tab bar)

#### Scenario: Secondary navigation is behind the menu on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the Poster Wall, Import from Plex, Orphans, Settings, and Support
  Development actions are not shown in the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Secondary navigation is in the header on desktop
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the Poster Wall, Import from Plex, Orphans, Settings, and Support
  Development actions are not shown in the gallery toolbar
- **AND** they are presented in the shared page header instead

#### Scenario: Toolbar stays available while scrolling on a phone
- **WHEN** a user on a narrow screen scrolls down the gallery
- **THEN** the toolbar stays pinned to the top of the viewport
- **AND** the search field and the sort trigger remain usable without scrolling
  back to the top

#### Scenario: Tabs and toolbar stay available while scrolling on desktop
- **WHEN** a user on a pointer/desktop-width screen scrolls down the gallery
- **THEN** the category tabs and the toolbar stay pinned together at the top of
  the viewport
- **AND** the search field, the inline sort control, and every category tab remain
  usable without scrolling back to the top

#### Scenario: Pinned toolbar hides the posters passing under it
- **WHEN** the gallery is scrolled on a narrow screen with the toolbar pinned
- **THEN** no poster passing behind the toolbar is individually recognisable, at
  any point across the full width of the viewport including its left and right
  edges
- **AND** the search field and the sort trigger remain fully legible over it

#### Scenario: Pinned desktop controls hide the posters passing under them
- **WHEN** the gallery is scrolled on a pointer/desktop-width screen
- **THEN** posters scrolling past are fully hidden behind the pinned tabs and
  toolbar, including at the left and right edges of the poster grid
- **AND** every category tab label, the search field, and the inline sort control
  remain fully legible

#### Scenario: No unsubdued strip beside the pinned controls
- **WHEN** the gallery is scrolled at any width
- **THEN** no strip of poster appears at full strength within the pinned block's
  bounds, at either edge, where the block's own surface does not reach

#### Scenario: A translucent pinned surface stays legible without blur support
- **WHEN** the gallery is scrolled on a narrow screen in a browser that does not
  support backdrop blur
- **THEN** the pinned toolbar falls back to an opaque surface, and every control
  on it remains fully legible

#### Scenario: Results too short to scroll leave the toolbar in flow
- **WHEN** a search returns no matches, on a narrow or a pointer/desktop screen
- **THEN** the page is too short to scroll and the gallery's controls rest below
  the topbar, as they do on an unscrolled page

#### Scenario: Toolbar survives the on-screen keyboard
- **WHEN** a user on a narrow screen scrolls part-way down the gallery and taps
  the search field, opening the on-screen keyboard
- **THEN** the toolbar stays visible at the top of the remaining area rather than
  being pushed out of view

#### Scenario: Overlays cover the pinned toolbar
- **WHEN** any tray, dialog, or the fullscreen viewer is open, on a narrow or a
  pointer/desktop screen
- **THEN** it renders above the gallery's pinned controls

### Requirement: Category tab presentation by screen size
On a narrow screen the category tabs SHALL be presented as a fixed, always-visible
bottom tab bar in which all five tabs fit the screen at once — each tab an icon
above a short label — rather than a scrolling row, so switching categories feels
like a native app tab bar. The gallery content SHALL reserve space so the tab bar
never hides the last posters or the footer. On a pointer/desktop screen the tabs
SHALL remain text tabs above the toolbar, and SHALL be pinned together with it as
the gallery scrolls (see "Responsive gallery layout and pinned controls"). Each tab SHALL retain its
full category name as its accessible name regardless of which presentation is
shown.

The bar SHALL mark the destination category as active from the moment a
horizontal drag is claimed, and SHALL mark the original again if that drag is
abandoned. The mark moves at the claim rather than at the release because it is
the application's acknowledgement that the gesture was recognised, which is owed
at the start of the gesture and not at the end of it.

The bar SHALL NOT itself move with the drag. It is fixed to the viewport, so the
grids slide beneath it and it needs no travel of its own; a bar that slid with
them would leave the viewer with nothing stationary to read the gesture against.

#### Scenario: All tabs fit at once in a bottom bar on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are shown as a fixed bottom bar of equal-width columns that all
  fit on screen without scrolling, each an icon over a short label
- **AND** content is not hidden behind the bar

#### Scenario: Active tab is indicated
- **WHEN** a category is active on a phone
- **THEN** its tab is visually highlighted as the current one

#### Scenario: Desktop tabs keep their text presentation
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the tabs render as text tabs directly above the toolbar, rather than as
  the phone's icon-over-label bottom bar

#### Scenario: The bar marks the destination when a drag is claimed
- **WHEN** a horizontal drag toward an adjacent category is claimed
- **THEN** that category's tab is marked active immediately, before the drag has
  been released

#### Scenario: An abandoned drag returns the mark
- **WHEN** a claimed drag is released without committing
- **THEN** the originally active tab is marked active again

#### Scenario: The bar stays still while the grids move
- **WHEN** a drag is in progress
- **THEN** the bottom tab bar remains in place, and only its active mark changes

### Requirement: Poster action controls carry icons
Every control in a poster's action set — Change poster, Send to Plex, Fetch from
Plex, Download, Related posters, Full screen, and Delete — SHALL present an icon
beside its label, so an action can be found by shape without reading the stack.
The icon SHALL lead the control and the controls SHALL be aligned so their icons
form a single column, since a glyph at a different horizontal position on every
row cannot be scanned.

Labels SHALL be retained on every control, at every viewport width, in both the
pointer-device overlay and the touch action sheet. The icon is an aid to
recognition and SHALL NOT be the only thing identifying a control: each icon SHALL
be hidden from assistive technology, and the control's accessible name SHALL
continue to come from its label.

The icon SHALL NOT increase a control's height. It sits beside the label rather
than above it, so the action stack occupies no more of the card than it does
without icons.

The two Plex actions SHALL be distinguished by direction and by nothing else.
Fetch from Plex SHALL use the same glyph as Import from Plex, because it performs
the same operation on a single item rather than a library. Send to Plex SHALL use
that glyph with its arrow reversed, keeping every other part of the mark
identical, so direction carries the whole distinction between two actions that
move the same image opposite ways and are each irreversible.

Related posters SHALL carry a glyph of its own, distinct from the glyph used for
the Collections category, because the action gathers posters that share a title
and a Plex collection is a different thing that happens to be adjacent in meaning.

The action sheet shown on touch devices SHALL present the same icons as the
pointer-device overlay, since it renders the poster's own action controls.

#### Scenario: Each action control shows an icon beside its label
- **WHEN** a poster's actions are shown on a pointer device
- **THEN** each control displays an icon followed by its text label
- **AND** no label has been removed or shortened to make room

#### Scenario: Icons line up in a column
- **WHEN** the action controls are shown
- **THEN** their icons share a single horizontal position, so the set reads as a
  column rather than as centred rows

#### Scenario: Fetch from Plex matches Import from Plex
- **WHEN** the Fetch from Plex control and the Import from Plex navigation action
  are compared
- **THEN** they display the same glyph

#### Scenario: Send to Plex reverses that glyph
- **WHEN** the Send to Plex and Fetch from Plex controls are compared
- **THEN** their glyphs are identical apart from the direction of the arrow

#### Scenario: Related posters does not reuse the Collections glyph
- **WHEN** the Related posters control and the Collections category are compared
- **THEN** they display different glyphs

#### Scenario: The touch action sheet shows the same icons
- **WHEN** a user taps a poster on a touch device and the action sheet opens
- **THEN** each action in the sheet displays the same icon it has in the
  pointer-device overlay

#### Scenario: Icons do not name the controls
- **WHEN** a poster's action controls are read by assistive technology
- **THEN** each control is announced by its label, and its icon is not announced

#### Scenario: Icons do not make the stack taller
- **WHEN** a poster's action stack is rendered with icons
- **THEN** each control occupies the same height it did without one

### Requirement: Poster cards fit their full action stack
A poster card SHALL be tall enough to show its complete action stack without
clipping or scrolling, at every viewport width at which the gallery is shown on a
pointer device. Because a card's height is a fixed ratio of the grid's column
width, the grid's minimum column width SHALL be large enough that the tallest
possible stack — the seven actions shown for a poster linked to Plex — fits
within the resulting card.

This makes precise the sizing already required by "Poster presentation", which
states that posters are sized large enough for the overlay action stack to fit.
Before this requirement that held at most column widths but not the narrowest,
where a linked poster's stack was clipped and the overlay scrolled.

#### Scenario: A linked poster's full stack fits at the narrowest column
- **WHEN** the gallery is viewed on a pointer device at the viewport width that
  produces the narrowest grid columns
- **AND** a poster linked to Plex is hovered, showing all seven actions
- **THEN** every action is visible within the card without the overlay scrolling

#### Scenario: An unlinked poster is unaffected
- **WHEN** a poster with no Plex link is hovered at any viewport width
- **THEN** its five actions are shown in full, as before

#### Scenario: Column count at the full content width is unchanged
- **WHEN** the gallery is viewed at or above the content column's maximum width
- **THEN** the grid shows the same number of columns as it did before this
  requirement

### Requirement: Sort direction remembered per field
The system SHALL remember, for the duration of the session, the direction last
used for each sort field independently. Switching to the other field and back
SHALL restore the direction that field was last left in, rather than resetting to
its default direction.

Before a field has been used in the session, its remembered direction SHALL be
its default direction.

#### Scenario: Returning to a field restores its direction
- **WHEN** a user sets titles to Z–A, switches to date added, then switches back
  to titles
- **THEN** the gallery is ordered Z–A rather than A–Z

#### Scenario: Each field remembers its own direction
- **WHEN** a user sets titles to Z–A and date added to oldest first, then
  alternates between the two fields
- **THEN** each field is restored to the direction it was last left in, and
  changing one field's direction does not change the other's

#### Scenario: Unused field starts at its default direction
- **WHEN** a user has only ever used titles this session and switches to date
  added for the first time
- **THEN** the gallery is ordered by date added, newest first

### Requirement: Sort control indicates field and direction
Each button in the sort control SHALL identify its field and show a direction
indicator: an arrow pointing down when that field is running its default
direction, and up when it has been reversed. The title button's label SHALL read
`A–Z` when its direction is ascending and `Z–A` when it is descending; the
date-added and release buttons SHALL each keep a constant label and convey
direction through their indicator alone.

The indicator SHALL report reversal rather than ascending or descending, because
those do not read alike across the fields: A–Z is ascending, newest-first is
descending and earliest-first is ascending, yet each is the ordinary way to read
its own field. Keying the arrow to reversal means every button rests pointing
down, and an arrow that has turned over always carries the same meaning.

The direction indicator SHALL be drawn as one half of the glyph that opens the
phone sort tray, that glyph being a down arrow beside an up arrow, so the control
and its trigger read as one mark whole and halved.

Each button SHALL carry a leading glyph identifying its field, drawn in the
application's existing icon style, and each field's glyph SHALL be distinguishable
from the others'.

The active button SHALL show the order the gallery is currently in, while
activating it applies the reversed order. Each inactive button SHALL show that
field's remembered direction, which is also the order activating it applies.

Because an arrow alone is not announced by assistive technology, each button
SHALL carry a text alternative, and the same wording SHALL serve as its tooltip
save for the verb naming the action: the tooltip SHALL say "click", appearing as
it does only on hover, and the text alternative SHALL use a device-neutral verb,
being read where there may be no pointer at all.

The active button's wording SHALL both state the order the gallery is in and name
the order activating it applies, those being different orders. Wording that names
only the order the button shows SHALL NOT be used on the active button: phrased as
an instruction it would tell the user to sort the way the gallery is already
sorted, which is the opposite of what activating it does. An inactive button
shows and applies the same order and SHALL name that one order as an instruction.

Each field's direction SHALL be worded in terms that suit that field, so no two
fields describe their directions in the same words where those words would mean
different things.

#### Scenario: Active button says what it is and what it does
- **WHEN** a user reads or hovers the active button while the gallery is ordered
  A–Z
- **THEN** the wording states that the gallery is sorted by title A to Z and names
  Z to A as what activating it applies

#### Scenario: Inactive button names only what it does
- **WHEN** a user reads or hovers an inactive button
- **THEN** the wording names the single order activating it applies, as an
  instruction

#### Scenario: The tooltip names the action as a click
- **WHEN** a user hovers the active button
- **THEN** the tooltip names the action as clicking, a tooltip being reachable
  only with a pointer
- **AND** the button's text alternative names the same action in a way that does
  not assume one

#### Scenario: No button instructs the order it already shows
- **WHEN** any button is read in any of the six orders
- **THEN** it never presents the order it is showing as the order activating it
  would apply

#### Scenario: Active title button shows the current direction
- **WHEN** the gallery is ordered Z–A
- **THEN** the title button is marked active, reads `Z–A`, and shows an upward
  arrow, Z–A being the title field reversed

#### Scenario: Both fields rest pointing the same way
- **WHEN** the gallery is ordered A–Z and the date-added field has not been
  reversed this session
- **THEN** both buttons show a downward arrow, despite A–Z being ascending and
  the date field's default being descending

#### Scenario: Every field rests pointing the same way
- **WHEN** the gallery is ordered A–Z and neither the date-added nor the release
  field has been reversed this session
- **THEN** every button shows a downward arrow, despite A–Z being ascending and
  both time fields resting descending
- **AND** the two time fields lead with the most recent, so a resting arrow means
  one thing across the control

#### Scenario: Direction indicator matches the tray's trigger
- **WHEN** a user opens the phone sort tray from its trigger
- **THEN** the direction shown on each row inside is the same mark as one half of
  the trigger's own two-arrow glyph

#### Scenario: Activating the active button applies the reverse of its label
- **WHEN** the title button reads `Z–A` and the user activates it
- **THEN** the gallery is reordered A–Z and the button then reads `A–Z`

#### Scenario: Inactive button previews its remembered direction
- **WHEN** the gallery is ordered by date added and the title field was last left
  at Z–A
- **THEN** the inactive title button reads `Z–A`, and activating it orders the
  gallery Z–A

#### Scenario: Date-added button conveys direction by indicator
- **WHEN** the gallery is ordered by date added, oldest first
- **THEN** the date-added button keeps its label and shows an upward arrow, oldest
  first being the date field reversed

#### Scenario: Release button conveys direction by indicator
- **WHEN** the gallery is ordered by release, earliest first
- **THEN** the release button keeps its label and shows an upward arrow, earliest
  first being the release field reversed

#### Scenario: Release and date added do not describe direction alike
- **WHEN** the release button and the date-added button are read in words
- **THEN** each names its direction in terms belonging to its own field, so the
  two cannot be mistaken for one another

#### Scenario: Direction is available as text
- **WHEN** assistive technology reads a sort button
- **THEN** it announces the button's field and direction in words rather than
  only the label, the arrow carrying no announcement of its own

#### Scenario: Control is consistent on phone and desktop
- **WHEN** a user opens the phone sort tray
- **THEN** its buttons carry the same labels, glyphs, direction indicators, and
  toggle behavior as the desktop toolbar control

### Requirement: Selected sort survives paging and category switching
The system SHALL carry the selected sort order — field and direction — in the
links it renders for pagination and for switching category, so neither action
resets the order.

#### Scenario: Paging keeps a reversed order
- **WHEN** a user orders the gallery Z–A and moves to page two
- **THEN** page two is also ordered Z–A

#### Scenario: Switching category keeps a reversed order
- **WHEN** a user orders the gallery by date added oldest first and switches to
  another category
- **THEN** that category is also ordered by date added oldest first

### Requirement: A tray has one scrolling region, and it contains its scrolling
A scrolling region inside an open tray SHALL contain its scrolling. When such a
region reaches the end of its content, the gesture SHALL stop there rather than
continuing into whatever scrolls behind it, so that no flick started inside a
tray can end up scrolling the gallery behind it.

A tray SHALL NOT nest one scrolling region inside another. Where a tray's
contents include a region that scrolls independently on a wider screen — the
stack of grouped candidates in the Change Poster tray is the case — that region
SHALL hand its scrolling up to the tray's body at tray widths, so the tray has
a single scroller.

Nesting is excluded rather than merely contained because the two rules combine
badly: containment makes the inner region the *only* one a gesture over it can
move, stopping a flick at the inner region's end instead of continuing into the
outer one. Whatever the outer region holds beyond the inner one's box then
becomes unreachable by any gesture the user would think to make. The two
scrollers also present two scrollbars for one tray, only one of which responds
where the user's finger is.

#### Scenario: Reaching the end of a tray's contents
- **WHEN** a user scrolls a tray's contents to the bottom and continues the flick
- **THEN** scrolling stops at the end of the tray's contents and the page behind
  the tray does not scroll

#### Scenario: A tray has one scroller, not two
- **WHEN** a user opens the Change Poster tray on a small screen and selects a
  tab whose candidates are grouped — Find Posters or Plex Posters
- **THEN** the tray presents a single scrolling region, and one flick begun
  anywhere over its contents moves all of them together

#### Scenario: Scrolling the grouped candidates to their end
- **WHEN** a user scrolls the grouped candidates in the Change Poster tray to
  their end and continues the flick
- **THEN** neither the surrounding tray nor the gallery behind it is scrolled by
  that gesture

### Requirement: A tray's contents are reachable in full
Every part of an open tray's contents SHALL be reachable by scrolling. No
content SHALL be laid out beyond the edge at which the tray's panel clips,
where no gesture available to the user can bring it into view.

This SHALL hold whatever the height of the viewport. A tray's panel and the
regions inside it are sized as proportions of the viewport, while the tray's
own furniture — its grab handle, heading, any tab strip, and the device's
bottom safe-area inset — occupies a fixed height that does not shrink with the
screen. A shorter viewport therefore gives that furniture a larger share, and
an arrangement that fits on a tall phone can clip its last row on a short one.
Reachability SHALL NOT depend on the viewport being tall enough to absorb the
difference.

Content lost this way reports nothing. The tray looks complete, the clipped row
is simply absent, and a user who cannot reach a candidate cannot choose it and
is given no reason why — which is why this is stated as a requirement rather
than left to the sizing rules to get right.

#### Scenario: The last row of found candidates can be reached
- **WHEN** a user opens Find Posters on a small screen for a title with enough
  candidates to overflow the tray
- **THEN** scrolling reaches the last row of candidates in full, and it can be
  tapped to preview

#### Scenario: The end of a long Plex group can be reached
- **WHEN** a user opens Plex Posters on a small screen for an item whose offered
  artwork runs to dozens of candidates
- **THEN** scrolling reaches the end of the last group in full, with no
  candidate left below the edge of the tray

#### Scenario: A short viewport clips nothing
- **WHEN** the same tray is opened on a screen short enough that its handle,
  heading, tab strip, and safe-area inset take a large share of the height
- **THEN** its contents are still reachable in full by scrolling, rather than
  the last row falling below the panel's edge

### Requirement: A horizontal drag on the gallery moves between adjacent categories

On a touch device, a horizontal drag on the gallery SHALL move between adjacent
category tabs. The outgoing category SHALL track the touch one-to-one, and the
incoming category SHALL enter from the opposite edge at the same rate, one
viewport apart, both updating for as long as the touch continues.

The two categories SHALL NOT overlap at any point in the gesture. One leaves as
the other arrives; neither is ever drawn over the other.

On release the gesture SHALL resolve to exactly one of three outcomes: it
commits and the categories complete their travel, it is abandoned and they
return to rest with the active category unchanged, or it was never claimed and
nothing moved.

The motion is the confirmation. A gesture evaluated only when the finger leaves
the glass produces the same first frame whether it is going to work or not, so
the viewer cannot see that it was recognised, cannot see how far is left, and
cannot change their mind. All three of those have to be true while the thumb is
still down.

Overlapping the two is specifically excluded. An incoming panel entering at a
fraction of the outgoing panel's speed — the familiar platform parallax — sits
on top of it for the whole gesture, and which of the two is drawn above the
other is then decided by their order in the document rather than by the
direction of travel, which reads as one direction winning every time.

#### Scenario: The grids follow the finger

- **WHEN** a horizontal drag is in progress
- **THEN** the outgoing category's horizontal offset SHALL correspond to the
  distance the touch has travelled, updating as the touch moves

#### Scenario: The incoming category arrives as the outgoing one leaves

- **WHEN** a horizontal drag is in progress
- **THEN** the incoming category SHALL be visible entering from the opposite
  edge at the same rate the outgoing one leaves, and SHALL arrive at rest at the
  same moment the outgoing one completes its travel

#### Scenario: Neither category is ever drawn over the other

- **WHEN** a horizontal drag is in progress, in either direction
- **THEN** the two SHALL remain one viewport apart and SHALL NOT overlap, so
  which is visible depends only on how far the drag has travelled

#### Scenario: Reversing the drag reverses the grids

- **WHEN** the touch reverses direction mid-drag
- **THEN** the grids SHALL follow it back, and a drag that returns to its origin
  SHALL leave them at rest

#### Scenario: A committed drag completes the travel

- **WHEN** a drag is released having travelled at least a third of the viewport
  width, or at a speed above the flick threshold in the direction of the
  incoming category
- **THEN** the outgoing category SHALL continue off the screen in the direction
  the finger travelled, the incoming one SHALL complete its entry, and the
  category change SHALL take effect

#### Scenario: An abandoned drag restores the previous category

- **WHEN** a drag is released having travelled less than the commit distance and
  below the flick threshold
- **THEN** both SHALL return to rest, the active category SHALL be unchanged,
  and the viewer SHALL be returned to the scroll position they were at when the
  drag began

#### Scenario: A drag past the threshold can still be abandoned

- **WHEN** a drag travels past the commit distance and is then dragged back
  below it before release
- **THEN** the gesture SHALL be abandoned rather than committed

#### Scenario: The settle is timed from the distance still to travel

- **WHEN** a drag is released
- **THEN** the grids SHALL complete their movement over a duration proportional
  to the distance remaining, bounded below by a floor and above by the standard
  transition duration

#### Scenario: A pointer device is unaffected

- **WHEN** the gallery is used on a device that reports a hover-capable pointer
- **THEN** no drag gesture SHALL be bound, and changing category by clicking a
  tab SHALL remain an instant cut

### Requirement: The drag's axis is decided once, early, and held

A touch on the gallery SHALL be assigned to exactly one axis within the first
few pixels of travel, and that assignment SHALL hold for the remainder of the
touch.

A touch assigned to the vertical axis SHALL be left entirely to the browser's
scrolling for the rest of its life. A touch assigned to the horizontal axis
SHALL have the browser's default handling suppressed from the move that claimed
it onward.

The listener that claims the gesture SHALL be registered non-passively from the
outset. A passive listener cannot suppress the default at all, and a touch
sequence whose early moves went uncancelled has on some platforms already been
given to the scroller, where later attempts to cancel it are ignored silently —
producing a gesture that works everywhere except the platform it was written
for.

A gesture that re-arbitrates its axis mid-drag can hand a moving page back to
the scroller halfway through, so it SHALL NOT.

#### Scenario: A vertical drag scrolls and is never claimed

- **WHEN** a touch's initial travel is predominantly vertical
- **THEN** the page SHALL scroll normally, the grids SHALL NOT move, and the
  touch SHALL NOT be claimed later in its life however it subsequently moves

#### Scenario: A horizontal drag is claimed before the page can scroll

- **WHEN** a touch's initial travel is predominantly horizontal
- **THEN** the gesture SHALL be claimed within the first few pixels of travel,
  and the page SHALL NOT scroll for the remainder of that touch

#### Scenario: A tap is neither

- **WHEN** a touch begins and ends without exceeding the axis-lock distance
- **THEN** no drag SHALL have begun, and the touch SHALL behave exactly as it
  does today — opening the poster's action tray when it lands on a card

#### Scenario: A two-finger gesture is not a drag

- **WHEN** a touch begins with more than one contact point, or a second contact
  arrives during a touch
- **THEN** no drag SHALL be claimed, so a pinch or zoom reaches the browser

### Requirement: A drag with nowhere to go resists

A horizontal drag toward a category that does not exist SHALL move the current
category by a damped fraction of the touch's travel and SHALL return it to rest
on release, without changing the active category.

The traversal order SHALL be the order the tabs are rendered in — All, Movies,
Shows, Seasons, Collections — so the gesture agrees with what the bottom bar
shows. All is the first and Collections is the last; the other three commit in
both directions.

A drag off either end that did nothing at all would be indistinguishable from a
gesture the application failed to recognise. Resistance says there is nothing
there, which is the fact the viewer is missing.

#### Scenario: Dragging past the first category resists

- **WHEN** the viewer drags rightward while All is active
- **THEN** All SHALL move by a damped fraction of the travel, no incoming
  category SHALL appear, and it SHALL return to rest on release

#### Scenario: Dragging past the last category resists

- **WHEN** the viewer drags leftward while Collections is active
- **THEN** Collections SHALL move by a damped fraction of the travel, no
  incoming category SHALL appear, and it SHALL return to rest on release

#### Scenario: A resisted drag never commits

- **WHEN** a resisted drag is released at any distance or speed
- **THEN** the active category SHALL be unchanged

#### Scenario: An interior category commits in both directions

- **WHEN** the viewer drags in either direction while Movies, Shows or Seasons
  is active
- **THEN** the adjacent category in that direction SHALL be the incoming one and
  the drag SHALL be committable

### Requirement: The adjacent categories are fetched before the gesture needs them

The application SHALL fetch and hold the rendered results of both categories
adjacent to the active one, so that a drag has real content to move from its
first frame rather than a placeholder.

The fetch SHALL happen after the active category has settled and SHALL NOT
delay it. A held copy SHALL be discarded rather than shown whenever anything
determining what that category displays has changed since it was fetched — the
search term, the sort order, or any mutation of the library such as a delete, a
poster change, or a completed import.

The comparison SHALL err toward discarding. Any input to what a category
displays that is not covered by it SHALL be treated as a change.

The asymmetry is the point. Wrongly discarding a good copy costs one fetch that
nobody needed. Wrongly trusting a stale one shows the viewer a grid that does
not match their search or sort — a wrong library that looks like a working one.

Holding a copy SHALL remain an optimisation and SHALL NOT become load-bearing.
The gesture SHALL be fully correct with every held copy absent, and the absence
SHALL cost only the placeholder described below.

#### Scenario: A held copy is used when it is current

- **WHEN** a drag begins toward a category whose held copy matches the live
  search, sort and library state
- **THEN** that copy SHALL be shown as the incoming category, with no fetch
  during the gesture

#### Scenario: A changed search discards the held copies

- **WHEN** the viewer changes the search term or the sort order, and then drags
  to an adjacent category
- **THEN** that category SHALL display results matching the new selection, never
  the previous one

#### Scenario: A library mutation discards the held copies

- **WHEN** a poster is deleted or changed, or an import completes
- **THEN** any held copy SHALL be discarded, and a subsequent drag SHALL show
  the category as it is now

#### Scenario: A missing copy does not refuse the gesture

- **WHEN** a drag begins toward a category with no current held copy
- **THEN** the drag SHALL begin and follow the finger exactly as it otherwise
  would, showing a placeholder in the incoming panel while its results are
  fetched

#### Scenario: The placeholder is replaced in place

- **WHEN** the fetched results arrive while the drag is still in progress
- **THEN** they SHALL replace the placeholder without interrupting the gesture
  or moving the panel

#### Scenario: A commit waits for content rather than showing a placeholder

- **WHEN** a drag commits before the fetched results have arrived
- **THEN** the category change SHALL still take effect, and the deferred loading
  indication SHALL report the outstanding work as it does for any other
  in-place view change

### Requirement: A committed drag leaves the same state as tapping the tab

A drag that commits SHALL leave the application in exactly the state that
tapping the destination tab leaves it in: that category active in the bottom
bar, its results in the results region, the browser title updated, a history
entry pushed so the back gesture returns to the previous category, the active
search carried over, and the view positioned at the top.

There SHALL be one code path that performs the category change, used by both the
tap and the drag. Two paths that must agree about seven things will not keep
agreeing.

Infinite scroll SHALL be re-established for the newly active category at its
first page. A category returned to after a drag SHALL NOT restore however far it
had previously been scrolled — the held copy is a first page, and preserving
depth would make the cache load-bearing for what the viewer sees rather than
only for how quickly they see it.

#### Scenario: A committed drag pushes a history entry

- **WHEN** a drag commits from Movies to Shows
- **THEN** a history entry SHALL be pushed, and the browser's back gesture SHALL
  return to Movies

#### Scenario: A committed drag keeps the active search

- **WHEN** a search is active and a drag commits to another category
- **THEN** that category SHALL open filtered by the same search term

#### Scenario: A committed drag re-arms infinite scroll

- **WHEN** a drag commits and the destination category has more than one page
- **THEN** scrolling to the foot of its grid SHALL append the next page as it
  does after a tab tap

#### Scenario: Returning to a category shows its first page

- **WHEN** the viewer has scrolled several pages into a category, drags away,
  and drags back
- **THEN** that category SHALL be shown from its first page at the top, not at
  its previous depth

### Requirement: A drag is refused before it is claimed when an overlay is open

The gesture SHALL be available across the whole page, not only where the grid
happens to reach. A grid short enough to leave empty space — a search matching a
handful of posters — SHALL still be swipeable in that empty space, and beneath
and beside it, because nothing there distinguishes it from the grid as far as
the viewer is concerned.

A touch SHALL NOT begin a drag while any overlay is open, when it begins inside
an overlay panel or on its backdrop, or when it begins on any of the application
bars: the category tab bar, the search and sort toolbar, or the page header.

The refusal SHALL happen when the touch begins, not when it ends. A gesture that
discovers the conflict later has already suppressed the browser's handling and
taken both grids out of the scroller.

A touch inside an overlay belongs to that overlay — its own dismissal drag, a
scroll in its body, or a backdrop tap — and never to the gallery behind it. A
touch on a bar belongs to that bar: the tab bar is how a category is tapped, a
horizontal drag across the search field is how text is selected in it, and the
header carries the navigation. Everything else — the gallery, the space around
it, the footer — is the page being browsed, and belongs to the gesture.

Naming the bars is required rather than incidental. A gesture offered across the
whole page reaches every bar on it, so each one keeps its touches only by being
excluded; the list is the mechanism, not a convenience.

The check for an open overlay SHALL be the same one the page scroll lock uses.
Two independent answers to "is an overlay open" will drift, and the cost of them
disagreeing here is a gesture that fights a tray.

#### Scenario: An open overlay refuses the drag

- **WHEN** a touch begins on the page while any tray or dialog is open
- **THEN** no drag SHALL begin, and the grids SHALL NOT move

#### Scenario: A touch inside an overlay reaches the overlay

- **WHEN** a touch begins inside an overlay's panel
- **THEN** that overlay's own gesture handling SHALL apply and no drag SHALL
  begin

#### Scenario: A tray dismissal drag still works

- **WHEN** a downward drag begins on an open tray's grab handle or head
- **THEN** the tray SHALL be dismissed as it is today, with no interference from
  the category gesture

#### Scenario: A touch on any application bar is not a drag

- **WHEN** a touch begins on the bottom tab bar, the search and sort toolbar, or
  the page header
- **THEN** no drag SHALL begin, and that bar's own controls SHALL behave as they
  do today

#### Scenario: Empty space below a short grid still swipes

- **WHEN** a search leaves a grid shorter than the screen, and a horizontal drag
  begins in the empty space below or beside it
- **THEN** the drag SHALL be claimed and SHALL move between categories exactly as
  one begun on a poster does

### Requirement: An interrupted drag leaves nothing pinned

A drag SHALL be resolved to a correct resting state when it is interrupted by
anything: a cancelled touch, a viewport resize, an orientation change, a
category change from another control, or a second drag beginning.

Resolution SHALL run from a single routine that is safe to invoke repeatedly and
that clears everything the gesture set — both panels' pinning, transforms and
inline sizing, the spacer holding its place in the flow, the gesture-live flag,
and any pending frame callback.

A drag takes both grids out of the document's scroller. Leaving that in place
because a touch was cancelled by an incoming call gives the viewer a page that
cannot scroll, with nothing on screen to explain it.

#### Scenario: A cancelled touch resolves the drag

- **WHEN** a touch driving a drag is cancelled by the system
- **THEN** the drag SHALL resolve to a resting state and the page SHALL scroll
  normally

#### Scenario: A second gesture resolves the first

- **WHEN** a new drag begins while a settle from a previous one is still running
- **THEN** the previous one SHALL be fully resolved before the new one begins

#### Scenario: A resize during a drag resolves it

- **WHEN** the viewport is resized or the device is rotated while a drag is in
  progress
- **THEN** the drag SHALL resolve to a resting state and the gallery SHALL
  re-measure its layout

#### Scenario: The page never scrolls sideways

- **WHEN** a drag is in progress, at any offset, in either direction
- **THEN** the document SHALL NOT gain horizontal scroll, and no part of the
  moving panels SHALL be reachable by scrolling sideways

#### Scenario: Pinned and sticky chrome keeps its behaviour during a drag

- **WHEN** a drag is in progress, having begun at any scroll position
- **THEN** every element that was pinned or stuck to the viewport before the
  gesture SHALL remain so for its duration, and SHALL NOT move or stop sticking

### Requirement: Related posters action
Every poster SHALL offer an action that finds the other posters belonging to the
same work — a show together with its seasons, a film together with its sequels
and its collection — without the user having to read the title off the card and
type it into the search box.

The action SHALL be labelled **Related posters** and SHALL be offered for every
poster in every category, on pointer devices and touch devices alike. It SHALL
NOT be switched off, hidden, or conditioned on the poster having a Plex record: a
poster with no record searches its own filename-derived title, which is narrow
but never wrong.

Activating it SHALL present, in the All view, the **set** the poster belongs to.

A poster's set SHALL be identified by the Plex item it belongs to, recorded at
import, and SHALL be resolved by that identity rather than by matching titles:

| Poster | Its set |
| --- | --- |
| A TV show | itself |
| A TV season | its show |
| A collection | itself |
| A movie in a collection | every collection holding it |

A poster SHALL be shown in a set whenever that set is among the ones it records,
so a poster belonging to several appears in each of them. Activating the action on
**any** member SHALL open a set holding every other member — a late season and its show, a
sequel and the film it followed, alike. A set SHALL therefore not depend on which
member it was opened from.

Because a set is identified rather than described, two works that share a title
SHALL NOT be gathered into one set, and members that share no words in their
titles — as the films of a studio or franchise collection often do not — SHALL
still be gathered.

A poster's own set SHALL include itself, so the poster the action was activated on
is always among the results.

A poster with **no recorded set** — a movie in no collection, a poster with no
Plex record, or any poster whose set has not been recorded yet — SHALL fall back to
searching for the poster's **related title**, which SHALL be:

- for a TV season, the title recorded for its **show**, so the search gathers the
  show's poster and every sibling season rather than the one season it started
  from;
- for a movie, TV show, or collection, the title recorded for that item;
- for a season whose show title has not yet been recorded, the recorded display
  title with its own season removed — that title being the show's and the
  season's joined, and the season number being recorded already, so the suffix
  removed is one the system can name rather than one it goes looking for;
- for a poster with no Plex record at all, the poster's own filename-derived
  title.

The removal SHALL be narrow. It SHALL remove only a trailing season matching the
season number recorded for that poster, and SHALL leave any title it does not
recognise exactly as it is. It SHALL NOT split the title at its last or first
separator: a show whose own name contains one, and a season whose name does, are
both real, and either split would produce a plausible-looking wrong answer. A
title the removal does not recognise is narrow rather than incorrect, and the
recorded show title supersedes it at the next import.

The removal SHALL NOT produce an empty query, which would match every poster.

The related title SHALL be the recorded title alone and SHALL NOT carry the
release year the caption appends. A year in the query would narrow the search back
to the single poster the action was started from, which is the opposite of its
purpose.

The action SHALL be rendered as a link to the filtered view it produces, so it
works when scripting is unavailable and can be opened in a new tab or copied like
any other link. Where scripting is available the gallery SHALL intercept it and
change view without a full page reload, through the same single routine that a
category tab tap uses, so the active tab, the results, the browser title, the
history entry, the carried query, the scroll position, and infinite scroll are all
left correct.

The action SHALL be rendered from the same markup on both surfaces, so the label
and icon a user learns on one cannot differ from the other.

The results SHALL be presented from the top, whatever the reader's scroll
position when the action was activated. A new view is a new list and is read from
its first result.

This SHALL hold when the action is activated from the touch action sheet, where
the page behind the sheet is pinned and cannot be scrolled: the position the page
is released to when the sheet closes SHALL be the top of the new view, not the
offset the reader held in the view they left.

The action SHALL NOT add an eighth control to the stack. It takes the place of
Copy URL, leaving seven controls for a poster linked to Plex and five otherwise,
so the sizing fixed by "Poster cards fit their full action stack" is unchanged.

#### Scenario: Related posters from a TV season
- **WHEN** a user activates Related posters on the poster for "Breaking Bad -
  Season 5", whose set has been recorded
- **THEN** the gallery shows the All view holding that show's set
- **AND** the show's own poster and every imported season of it are among the
  results

#### Scenario: Related posters from a film in a collection
- **WHEN** a user activates Related posters on a film recorded as belonging to a
  collection
- **THEN** the gallery shows the All view holding that collection's set
- **AND** every other imported film in that collection is among the results
- **AND** the collection's own poster is among them when it has been imported

#### Scenario: The same set from any member
- **WHEN** a user activates Related posters on the last film of a collection, and
  separately on the first
- **THEN** both show the same set

#### Scenario: Overlapping collections each gather their shared films
- **WHEN** a film belongs to two collections and a user opens either of them
- **THEN** that film is among the results both times
- **AND** neither collection is reduced to its own poster by the other having
  claimed the film

#### Scenario: A set whose members share no words
- **WHEN** a user activates Related posters on a film in a collection whose films
  have no words in common, such as a studio or franchise collection
- **THEN** every film in that collection is shown
- **AND** the result does not depend on the films resembling one another by name

#### Scenario: Two works sharing a title are not merged
- **WHEN** two shows have the same title and a user activates Related posters on a
  season of one of them
- **THEN** only that show and its own seasons are shown

#### Scenario: A collection's poster need not have been imported
- **WHEN** a user activates Related posters on a film whose collection's own
  poster has not been imported
- **THEN** the other films of that collection are still shown
- **AND** no error is reported

#### Scenario: A film in no collection falls back to a title search
- **WHEN** a user activates Related posters on a film that belongs to no
  collection
- **THEN** the gallery shows the All view filtered by that film's title
- **AND** the films sharing that title are among the results, as they were before
  sets were recorded

#### Scenario: The release year is not part of the query
- **WHEN** a user activates Related posters on a poster captioned "The Matrix
  (1999)"
- **THEN** the query is "The Matrix"
- **AND** posters for the other films of that name are among the results

#### Scenario: Offered for every poster
- **WHEN** a poster's actions are shown, whatever its category and whether or not
  it is linked to a Plex item
- **THEN** Related posters is present and can be activated

#### Scenario: A poster with no Plex record searches its own title
- **WHEN** a user activates Related posters on a poster that has no Plex mapping
- **THEN** the gallery shows the All view filtered by the poster's
  filename-derived title
- **AND** no error is reported

#### Scenario: A season whose show title is not yet recorded
- **WHEN** a user activates Related posters on a season titled "Severance -
  Season 1" that was imported before show titles were recorded, and whose
  recorded season number is 1
- **THEN** the gallery shows the All view filtered by "Severance"
- **AND** the show's poster and every sibling season are among the results,
  without the user having run an import first

#### Scenario: A season whose name the removal does not recognise
- **WHEN** a user activates Related posters on a season whose recorded title does
  not end in the season its recorded season number predicts
- **THEN** the query is that season's title unchanged
- **AND** the result is narrow rather than incorrect, and widens once the show
  title has been recorded by a later import

#### Scenario: A show whose own name contains the separator is not split
- **WHEN** a season of a show named "Cowboy Bebop - Remastered" is activated and
  no show title has been recorded for it
- **THEN** the query is "Cowboy Bebop - Remastered", not "Cowboy Bebop"

#### Scenario: The action works without scripting
- **WHEN** scripting is unavailable and a user follows the Related posters action
- **THEN** the browser loads the All view filtered by the related title

#### Scenario: The stack does not grow
- **WHEN** a poster linked to Plex is hovered on a pointer device
- **THEN** seven action controls are shown, Related posters among them
- **AND** the grid's column width is unchanged from before this action existed

#### Scenario: Both surfaces show the same control
- **WHEN** the Related posters control in the pointer-device overlay and the one
  in the touch action sheet are compared
- **THEN** they carry the same label and the same icon

#### Scenario: Activating it from the touch sheet closes the sheet
- **WHEN** a user activates Related posters from the touch action sheet
- **THEN** the sheet closes and the filtered All view is shown

#### Scenario: The results are read from the top
- **WHEN** a user scrolls part-way down a category and activates Related posters
  on a poster there
- **THEN** the All view is shown scrolled to its first result

#### Scenario: A sheet dismissal does not restore the previous view's position
- **WHEN** a user scrolls part-way down a category on a touch device, taps a
  poster to open its action sheet, and activates Related posters
- **THEN** the page is released at the top of the All view
- **AND** it is not returned to the offset it held in the category the user left

### Requirement: Release ordering
The system SHALL order posters by release using only facts already recorded for
the Plex item: its release year, and — for a season — its season number.

The field's default direction SHALL be **descending — latest first — matching
Date added.** The two fields both answer a question about time and sit side by
side in the same control, where the direction indicator reports whether a field
is running its ordinary way rather than whether it ascends. Giving them opposite
ordinary directions would leave both buttons resting identically while ordering
time in opposite directions, which is the single confusion that convention exists
to prevent.

Ascending, the order SHALL be decided by, in turn:

1. the recorded release year, with a poster whose release year is **not known**
   ordered **before** every poster whose year is known;
2. the recorded season number, with a poster carrying **no** season number
   ordered **before** those that do, so a show precedes its own seasons and the
   seasons read 1, 2, 3;
3. category, in the order Movies, TV Shows, TV Seasons, Collections;
4. the same article-aware, digit-aware title order used elsewhere, so the
   listing is fully deterministic.

Reversing the direction SHALL reverse the release year and nothing else. The
tie-breaks below it SHALL always run forwards, on the same terms as every other
sort field, so a show's seasons still read 1, 2, 3 with the order reversed rather
than scrambling.

An unknown release year SHALL order first rather than last because it is the
placement that is correct however Plex answers: a collection Plex reports no year
for then leads the films it holds, and a collection Plex does report a year for
sorts among its earliest films. Ordering unknowns last is correct only in the
second case.

A poster whose Plex item records no year SHALL be ordered, never omitted, and
SHALL NOT be treated as an error.

#### Scenario: A trilogy reads in the order it was released
- **WHEN** the gallery is ordered by release, earliest first, over "The Matrix"
  (1999), "The Matrix Reloaded" (2003) and "The Matrix Revolutions" (2003)
- **THEN** "The Matrix" is listed first
- **AND** the two 2003 films follow it

#### Scenario: A show precedes its own seasons
- **WHEN** the gallery is ordered by release, earliest first, over a show and its
  seasons, every one of which records the show's year
- **THEN** the show's own poster is listed before its seasons
- **AND** the seasons follow in season-number order

#### Scenario: Reversing keeps the seasons in order
- **WHEN** the same show and seasons are ordered by release, latest first
- **THEN** the show's poster and its seasons hold the same relative order as
  before, the reversal having applied to the release year alone

#### Scenario: A poster with no recorded year is ordered first
- **WHEN** the gallery is ordered by release, earliest first, and a poster's Plex
  item records no release year
- **THEN** that poster is listed before every poster whose year is known
- **AND** no error is reported

#### Scenario: Release rests the same way as date added
- **WHEN** neither the release nor the date-added field has been reversed this
  session
- **THEN** both rest in their descending direction, leading with the most recent
- **AND** the direction indicator on each carries the same meaning

#### Scenario: A poster with no Plex record is still ordered
- **WHEN** the gallery is ordered by release and a poster has no Plex mapping at
  all
- **THEN** it is ordered as a poster with no recorded release year
- **AND** it is not omitted from the listing

### Requirement: A set is ordered by the active sort
The gallery SHALL order a set by the sort order in force, exactly as it orders a
listing narrowed by a search. Opening a set SHALL NOT change which field is
active, SHALL NOT change its direction, and SHALL NOT alter what the sort control
displays.

A sort order chosen while a set is being shown SHALL be recorded as the user's
preference on the same terms as any other, so the control obeys one rule
everywhere rather than a different one per view.

**A set SHALL NOT carry an order of its own.** Ordering a set by release was
tried and withdrawn. The sort control is a global control, and a view that
reinterprets it makes the toolbar change on its own — which reads as the user's
own setting being overwritten whether or not anything was stored, that
distinction being invisible from the button. Release is offered as a field the
user selects; selecting it persists like any other selection.

The sort control's links SHALL carry the active set, so activating one re-orders
the set rather than leaving it. That is a difference in where the links point and
not in what the control shows.

#### Scenario: A set uses the order the user chose
- **WHEN** a user has ordered the gallery by release, latest first, and opens a
  set
- **THEN** the set is shown by release, latest first

#### Scenario: The order applies to a set in either direction
- **WHEN** the same set is opened with the gallery ordered by release earliest
  first, and again latest first
- **THEN** the set is listed in the opposite order the second time

#### Scenario: Opening a set leaves the sort control alone
- **WHEN** a user opens a set
- **THEN** the sort control shows the same active field, the same labels and the
  same direction indicators it showed before the set was opened

#### Scenario: An order chosen inside a set is remembered
- **WHEN** a user viewing a set selects a different sort order and then clears
  the set
- **THEN** the gallery keeps the order they selected

#### Scenario: A set is ordered alphabetically when that is the active order
- **WHEN** a user browsing A–Z opens a set
- **THEN** the set is listed A–Z
- **AND** the user may select release order to read it in the order it was
  released, that selection persisting as any other does

### Requirement: A set persists like an active query
A set SHALL survive the same navigation an active search query survives, so
Related posters behaves the same way whether it finds a set or falls back to a
title search.

A set SHALL be carried through switching view, changing the sort order, and
paging, and the address SHALL reflect it throughout, so the view stays shareable
and is restored by back/forward navigation.

A set SHALL be dropped when the user types a query into the search box — a typed
query being a new intent — and when the user activates the clear control shown
with the set.

Switching to a view holding none of the set's posters SHALL show the filtered
empty state, indicating that the view is filtered rather than that it is empty, on
the same terms as an active query that matches nothing there.

Any held copy of an adjacent view used to make a gesture immediate SHALL be
treated as stale when the active set differs from the one it was fetched under.
A held copy of the unfiltered view is not an answer to a set, and showing one
would present a wrong listing that looks like a working one.

#### Scenario: Switching view keeps the set
- **WHEN** a user viewing a set switches from All to Movies
- **THEN** the Movies view shows only that set's films
- **AND** the address reflects both the Movies view and the set

#### Scenario: Changing sort keeps the set
- **WHEN** a user viewing a set activates a sort button
- **THEN** the set is still shown, re-ordered
- **AND** the address still reflects the set

#### Scenario: Paging keeps the set
- **WHEN** a set holds more posters than one page and the user moves to page two
- **THEN** page two holds the same set

#### Scenario: Typing a query drops the set
- **WHEN** a user viewing a set types into the search box
- **THEN** the results are those of the typed query
- **AND** the set is no longer applied

#### Scenario: Clearing returns to the full view
- **WHEN** a user viewing a set activates the clear control
- **THEN** the full, unfiltered view is shown

#### Scenario: A view holding none of the set is a filtered empty state
- **WHEN** a user viewing a film collection's set switches to TV Shows
- **THEN** the view indicates that it is filtered and holds nothing, rather than
  that the category has no posters

#### Scenario: A swipe inside a set stays inside it
- **WHEN** a user viewing a set on a touch device drags horizontally to the
  adjacent category
- **THEN** the adjacent category is shown filtered by the same set
- **AND** a copy of that category fetched before the set was opened is not shown

#### Scenario: Back returns to the view the set was opened from
- **WHEN** a user opens a set and navigates back
- **THEN** the view they came from is restored

### Requirement: A set names the other sets its poster belongs to
When a set is opened from a poster, the address SHALL carry the poster it was
opened from, and the set view SHALL name every **other** set that poster belongs
to, each as a link to that set.

The line SHALL **name the poster it is about**. A set view holds many posters, so
a line reading only "also in MonsterVerse" does not say which of them it refers
to — and following it makes that worse, because the next set says the same thing
about a poster the reader can no longer identify. The poster SHALL be named the
way it is named everywhere else, so the line and the card agree.

The link SHALL carry the same origin poster, so a poster in several sets can be
followed from any one of them to any other.

The sets named SHALL be those of the **origin poster** alone, not the union of
every member's sets. A member of a large collection commonly belongs to several
others, and listing them all answers a question the user did not ask; the origin
poster's own list is short and answers the one they did — which of this poster's
sets am I looking at, and where are the others.

A set SHALL be named where its name is known and described where it is not; a set
whose name is unknown SHALL still be offered as a link rather than omitted.

The origin poster SHALL be **optional and inert**. A set address without one SHALL
be presented exactly as a set is presented today, and an origin poster that can no
longer be resolved — deleted, or renamed by a later import — SHALL be treated as
absent. It SHALL NOT affect which posters the set holds; membership SHALL be
decided by the set alone.

A poster belonging to exactly one set SHALL be told nothing, there being nothing
to say.

#### Scenario: A film in two collections names the other
- **WHEN** a user activates Related posters on a film recorded in both a "King
  Kong" and a "MonsterVerse" collection, and the set opened is King Kong
- **THEN** the set view names MonsterVerse as another set that film belongs to
- **AND** that name is a link to the MonsterVerse set
- **AND** the line names the film it is about, not only the other set

#### Scenario: The film stays named after following the link
- **WHEN** the user follows that link to the MonsterVerse set
- **THEN** the line there names the same film and offers King Kong
- **AND** the reader can still tell which poster the line refers to

#### Scenario: Following it carries the poster onward
- **WHEN** the user follows that link
- **THEN** the MonsterVerse set is shown
- **AND** it names King Kong as another set the same film belongs to

#### Scenario: A film in one collection is told nothing
- **WHEN** a user activates Related posters on a film recorded in exactly one
  collection
- **THEN** the set view names no other set

#### Scenario: A season is told nothing
- **WHEN** a user activates Related posters on a TV season
- **THEN** the show's set is shown and no other set is named, a season belonging
  to exactly one show

#### Scenario: A set address with no origin poster still works
- **WHEN** a user opens a set address that names no origin poster
- **THEN** the set is shown with its members and its clear control
- **AND** no other set is named

#### Scenario: An origin poster that no longer exists is ignored
- **WHEN** a set address names an origin poster that has since been deleted
- **THEN** the set is shown with exactly the same members as it would be without
  one
- **AND** no error is reported

#### Scenario: An unnamed other set is still offered
- **WHEN** an origin poster belongs to another set whose name is not known
- **THEN** that set is offered as a link described rather than named
- **AND** it is not omitted

### Requirement: A set is named from what Plex reported
The gallery SHALL name a set from the name Plex reported for the item that names
it, whether or not that item's own poster was imported.

The summary SHALL report the posters as being **for** the set rather than **in**
it, and SHALL name the view it is filtering, on the same terms as the summary for
an active query.

A set is two different relations wearing one word. A film really is *in* a
collection; a season is not *in* its show — it is artwork *for* it — so "6
posters in Breaking Bad" reads as a mistake while "9 posters in MonsterVerse" does
not. One wording SHALL serve every kind of set: asking which kind is being shown
would mean recording a type the gallery has no other use for, and would leave
sets recorded before that was stored with no answer at all.

A set whose name is not known SHALL be described rather than named, and the set
SHALL be presented correctly either way. A name is a courtesy; the members are the
answer.

Before the first import that records set names, and for a set whose naming item
Plex no longer reports, the gallery SHALL name the set from a recorded poster
where one exists and otherwise describe it, exactly as it does today.

#### Scenario: A collection with no imported poster is still named
- **WHEN** a user opens the set of a film whose collection's own poster was never
  imported, and that collection's name has been recorded
- **THEN** the set view names the collection

#### Scenario: An unknown name is described, not blank
- **WHEN** a set's name is not known by any means
- **THEN** the set view describes it without naming it
- **AND** the set's members and its clear control are shown unchanged

#### Scenario: A show's set does not read as posters inside the show
- **WHEN** a user opens the set of a show that has imported seasons
- **THEN** the summary reports the posters as being for that show
- **AND** it does not report them as being in it

#### Scenario: A collection's set reads the same way
- **WHEN** a user opens the set of a collection
- **THEN** the summary uses the same wording it uses for a show's set, one
  phrasing serving both kinds of set

#### Scenario: The summary names the view being filtered
- **WHEN** a user viewing a set switches from All to Movies
- **THEN** the summary names the newly selected view alongside the set

### Requirement: The gallery reads a category's recorded facts once per render
Rendering a view SHALL read each category's recorded Plex facts — the item's
title, release year, season number, related title, sets, and Plex "added at"
timestamp — in **one** read per category, whatever sort order is in force and
whether or not a query or a set is active.

The number of these reads SHALL be decided by how many categories the view
holds, and SHALL NOT grow with how many facts are shown, how many filters are
applied, how the listing is ordered, or how many surfaces consume them. Adding a
fact to a poster card SHALL cost no read at all.

This governs the reads that **scan** a category. Naming a set is a separate
question answered by a single lookup keyed on the set, once per render rather
than once per category, and is not one of these reads.

Every fallback that a fact's absence produces SHALL be preserved exactly:

- a poster whose recorded title is empty SHALL be captioned from its filename;
- a poster with no recorded year SHALL be captioned without one;
- a poster with no recorded set SHALL fall back to the title search;
- a poster with no recorded "added at" timestamp SHALL be ordered by its file's
  modification time.

Whether a poster offers the actions that need a live Plex connection SHALL
continue to depend on the connection as well as the mapping. The mapping outlives
a disconnection; the actions it enables do not.

#### Scenario: The aggregate view reads once per category
- **WHEN** the All view, holding four categories, is rendered and compared with a
  single-category view
- **THEN** the All view scans the recorded facts exactly three times more than
  the single-category view does

#### Scenario: Filtering adds no reads
- **WHEN** the All view is rendered with a query active
- **THEN** it scans the recorded facts no more than the unfiltered view does

#### Scenario: Showing a set adds no scans
- **WHEN** the All view is rendered showing a set
- **THEN** it scans the recorded facts no more than the unfiltered view does
- **AND** any further read it makes is the single keyed lookup that names the set

#### Scenario: Sorting by date adds no reads
- **WHEN** a view is rendered ordered by date added
- **THEN** it scans the recorded facts no more than the same view ordered by
  title

#### Scenario: A missing fact still falls back
- **WHEN** a poster's Plex record holds no title, no year, no set, and no "added
  at" timestamp
- **THEN** it is captioned from its filename without a year, its Related posters
  action searches its title, and it is ordered by its file's modification time

#### Scenario: A recorded fact is preferred to the fallback
- **WHEN** the same poster's Plex record holds a title, a year, a set, and an
  "added at" timestamp
- **THEN** each is used in place of the fallback it replaces

