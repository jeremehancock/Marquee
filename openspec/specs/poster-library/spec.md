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
  Plex when linked, download, copy URL, full screen, delete) with its title

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

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
the category tabs — including the All tab, five in total — fit on screen without
scrolling or crowding, toolbar controls fit their row, and posters are sized so
at least two fit per row on a phone.

On a narrow screen the gallery view SHALL stay focused on the posters: the
secondary navigation actions (Poster Wall, Import from Plex, Orphans) SHALL move
out of the gallery toolbar into the app-wide menu tray, and sort SHALL move out of
the toolbar into its own tray, leaving the mobile toolbar to search plus a sort
trigger above the poster grid. On a pointer/desktop screen the secondary actions
and the inline sort control SHALL remain in the toolbar exactly as before.

On a narrow screen the gallery toolbar SHALL remain pinned to the top of the
viewport as the gallery scrolls, so search and the sort trigger are reachable at
any scroll position without returning to the top of the page. The pinned toolbar
SHALL be opaque and SHALL span the full viewport width, so no poster is visible
passing behind or beside it. It SHALL layer above the poster grid and below every
overlay — the bottom tab bar, trays, dialogs, and the fullscreen viewer — so an
open overlay always covers it. On a pointer/desktop screen the toolbar SHALL
continue to scroll with the page.

Pinning applies only while the page can scroll. When the results are too short to
fill the viewport — a search with no matches, most obviously — the toolbar SHALL
rest in its normal position below the topbar. This is the same state as an
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
- **THEN** the Poster Wall, Import from Plex, and Orphans actions are not shown in
  the gallery toolbar
- **AND** they are reachable from the app menu tray instead

#### Scenario: Toolbar stays available while scrolling on a phone
- **WHEN** a user on a narrow screen scrolls down the gallery
- **THEN** the toolbar stays pinned to the top of the viewport
- **AND** the search field and the sort trigger remain usable without scrolling
  back to the top

#### Scenario: Pinned toolbar hides the posters passing under it
- **WHEN** the gallery is scrolled on a narrow screen with the toolbar pinned
- **THEN** posters scrolling past are fully hidden behind the toolbar, including
  at the left and right edges of the viewport

#### Scenario: Results too short to scroll leave the toolbar in flow
- **WHEN** a search returns no matches on a narrow screen
- **THEN** the page is too short to scroll and the toolbar rests below the
  topbar, as it does on an unscrolled page

#### Scenario: Toolbar survives the on-screen keyboard
- **WHEN** a user on a narrow screen scrolls part-way down the gallery and taps
  the search field, opening the on-screen keyboard
- **THEN** the toolbar stays visible at the top of the remaining area rather than
  being pushed out of view

#### Scenario: Overlays cover the pinned toolbar
- **WHEN** any tray, dialog, or the fullscreen viewer is open on a narrow screen
- **THEN** it renders above the pinned toolbar

#### Scenario: Desktop toolbar is unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the secondary navigation actions and the inline sort control render in
  the gallery toolbar as they did before this change
- **AND** the toolbar scrolls with the page rather than staying pinned

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

### Requirement: Native-style category tab bar on small screens
On a narrow screen the category tabs SHALL be presented as a fixed, always-visible
bottom tab bar in which all five tabs fit the screen at once — each tab an icon
above a short label — rather than a scrolling row, so switching categories feels
like a native app tab bar. The gallery content SHALL reserve space so the tab bar
never hides the last posters or the footer. On a pointer/desktop screen the tabs
SHALL remain text tabs in their original position. Each tab SHALL retain its full
category name as its accessible name regardless of which presentation is shown.

#### Scenario: All tabs fit at once in a bottom bar on a phone
- **WHEN** the gallery is viewed on a narrow screen with all five tabs present
- **THEN** the tabs are shown as a fixed bottom bar of equal-width columns that all
  fit on screen without scrolling, each an icon over a short label
- **AND** content is not hidden behind the bar

#### Scenario: Active tab is indicated
- **WHEN** a category is active on a phone
- **THEN** its tab is visually highlighted as the current one

#### Scenario: Desktop tabs are unchanged
- **WHEN** the gallery is viewed on a pointer/desktop-width screen
- **THEN** the tabs render as the text tabs used before this change, in their
  original position

### Requirement: Infinite scroll on small screens
On a narrow screen the gallery SHALL load posters by infinite scroll instead of
pagination: it SHALL append the next page of posters as the user nears the bottom
of the current results, continuing until the last page is reached, so the whole
library becomes reachable by scrolling without loading it all at once. The
pagination controls SHALL be hidden on a narrow screen and SHALL remain on a
pointer/desktop screen.

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

### Requirement: Scrolling within a tray stays within the tray
A scrolling region inside an open tray SHALL contain its scrolling. When such a
region reaches the end of its content, the gesture SHALL stop there rather than
continuing into whatever scrolls behind it. This SHALL hold for a scrolling
region nested inside another scrolling region — such as the Find Posters results
grid inside the Change Poster tray — at every level, so that no flick started
inside a tray can end up scrolling the gallery behind it.

#### Scenario: Reaching the end of a tray's contents
- **WHEN** a user scrolls a tray's contents to the bottom and continues the flick
- **THEN** scrolling stops at the end of the tray's contents and the page behind
  the tray does not scroll

#### Scenario: Nested scrolling region in Change Poster
- **WHEN** a user scrolls the Find Posters results grid to its end and continues
  the flick
- **THEN** neither the surrounding Change Poster tray nor the gallery behind it
  is scrolled by that gesture

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

