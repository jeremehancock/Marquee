## ADDED Requirements

### Requirement: Release ordering
The system SHALL order posters by release using only facts already recorded for
the Plex item: its release year, and — for a season — its season number.

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

#### Scenario: A poster with no Plex record is still ordered
- **WHEN** the gallery is ordered by release and a poster has no Plex mapping at
  all
- **THEN** it is ordered as a poster with no recorded release year
- **AND** it is not omitted from the listing

### Requirement: A set opens in release order
When the gallery presents the set a poster belongs to, it SHALL present that set
in release order, earliest first, whatever sort order the user last selected for
browsing.

This SHALL be a **default, not an override**. The sort control SHALL remain
present and active in a set view, SHALL indicate release as the field in force,
and activating any field in it SHALL re-order the set rather than leave it.

The order in force for a request SHALL be resolved in this precedence:

1. a sort order named explicitly in the address;
2. release order, when the view is a set;
3. the order the user selected earlier in the session;
4. the install's configured default sort.

A sort order resolved or chosen while a set is being shown SHALL NOT be recorded
as the user's session preference. Leaving the set SHALL return the library to the
order the user last chose for it. A set is a question about one work, and the
answer SHALL NOT outlive it.

Opening a set is a different act from filtering a list, and a set therefore
carries an order of its own. A search does not: an active query never changes the
order of the listing it narrows.

#### Scenario: A set opens in release order despite a chosen sort
- **WHEN** a user has ordered the gallery Z–A and activates Related posters on a
  film in a collection
- **THEN** the set is shown in release order, earliest first
- **AND** the sort control indicates release as the active field

#### Scenario: Sorting inside a set keeps the set
- **WHEN** a user viewing a set activates the title sort button
- **THEN** the same set is shown ordered by title
- **AND** the view is still the set, not the full library

#### Scenario: The set's order is not remembered
- **WHEN** a user viewing a set orders it by date added and then clears the set
- **THEN** the full library is shown in the order the user had chosen before
  opening the set

#### Scenario: An address naming a sort wins over the set's default
- **WHEN** a set view is opened at an address naming a sort order
- **THEN** the set is shown in that order rather than in release order

#### Scenario: A show's set reads show first, then seasons in order
- **WHEN** a user activates Related posters on a late season of a show
- **THEN** the show's own poster is shown first
- **AND** its seasons follow in season-number order

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

## MODIFIED Requirements

### Requirement: Sort order selection
The system SHALL support three gallery sort fields — **Alphabetical**
(article-aware title order), **Date added** (by when each poster's media was added
to Plex) and **Release** (by the release year recorded for each poster's Plex
item) — each in either direction, giving six effective orders: titles ascending
(A–Z), titles descending (Z–A), date added newest first, date added oldest first,
release earliest first, and release latest first. The selected order SHALL apply
to both a single category and the aggregate `all` view.

Each field SHALL have a default direction: ascending for Alphabetical, descending
(newest first) for Date added, and ascending (earliest first) for Release.

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

#### Scenario: Release order lists earliest first
- **WHEN** the effective sort order is Release ascending
- **THEN** the gallery lists posters by their recorded release year with the
  earliest first

#### Scenario: Release descending lists latest first
- **WHEN** the effective sort order is Release descending
- **THEN** the gallery lists posters by their recorded release year with the
  latest first

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
and `release` means earliest first.

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
- **THEN** the gallery orders posters by release, earliest first, until the user
  chooses otherwise

#### Scenario: Unrecognized value falls back to Alphabetical
- **WHEN** `DEFAULT_SORT` holds a value none of the accepted orders name
- **THEN** the system uses Alphabetical ascending rather than raising an error

### Requirement: Sort order toggle
The system SHALL present a control in the gallery toolbar with one button per
sort field. Activating the button of the field that is already active SHALL
reverse that field's direction; activating another field's button SHALL switch to
that field. The resulting order SHALL persist across navigation within the
session, taking precedence over `DEFAULT_SORT`, except while a set is being
shown, where the order applies to the request without being recorded.

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

#### Scenario: Activating another field switches to it
- **WHEN** the gallery is ordered Z–A and the user activates the date-added
  button
- **THEN** the gallery is ordered by date added

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

#### Scenario: Every field rests pointing the same way
- **WHEN** the gallery is ordered A–Z and neither the date-added nor the release
  field has been reversed this session
- **THEN** every button shows a downward arrow, despite A–Z and earliest-first
  being ascending and the date field's default being descending

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
- **WHEN** the gallery is ordered by release, latest first
- **THEN** the release button keeps its label and shows an upward arrow, latest
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
