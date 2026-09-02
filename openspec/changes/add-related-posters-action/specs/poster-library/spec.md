## ADDED Requirements

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

Activating it SHALL search for the poster's **related title** and present the
results in the All view. The related title SHALL be:

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

The action SHALL NOT add an eighth control to the stack. It takes the place of
Copy URL, leaving seven controls for a poster linked to Plex and five otherwise,
so the sizing fixed by "Poster cards fit their full action stack" is unchanged.

#### Scenario: Related posters from a TV season
- **WHEN** a user activates Related posters on the poster for "Breaking Bad -
  Season 5"
- **THEN** the gallery shows the All view filtered by "Breaking Bad"
- **AND** the show's own poster and every imported season of it are among the
  results

#### Scenario: Related posters from a movie
- **WHEN** a user activates Related posters on the poster for a film that is part
  of a trilogy
- **THEN** the gallery shows the All view filtered by that film's title
- **AND** the other films sharing that title, and the collection poster if one has
  been imported, are among the results

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

## MODIFIED Requirements

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
