## ADDED Requirements

### Requirement: A surface is named one way wherever it is named
The application SHALL distinguish a **named surface** from a description of one,
and SHALL spell each in its own register.

A named surface is a destination or overlay the interface offers by name — Poster
Wall, Import from Plex, Plex Connection, Plex Posters, Find Posters, Support
Development. Such a name SHALL be Title Case, and SHALL be the same string in
every placement that names it: the navigation entry, the tray or dialog title, the
page heading, the document title, and the accessible name.

Everything else a user reads SHALL be sentence case. This covers actions ("Change
poster", "Send to Plex", "Copy URL", "Full screen", "Save settings", "Clear
search"), confirmation titles ("Delete poster?", "Send to Plex?"), form labels,
section headings ("Presentation", "Auto-import", "Updates"), and positional
accessible names ("First page", "More actions", "Poster actions", "Sort order").

The two registers SHALL be allowed to describe the same destination differently,
and that is not a divergence. "Orphans" names the destination in navigation;
"Orphaned posters" describes what the page holds, and appears identically in the
page heading, the document title and the tray that opens it. A name and a
description of the same thing are two correct strings in two registers; the same
name spelled two ways is one incorrect string.

A name appearing inside a sentence SHALL keep its Title Case. This requirement
governs how a surface is named, not how prose is written.

Two placements of a name SHALL NOT be independent literals where the application
can avoid it. Every label a user meets at both widths is emitted once and drawn
twice — the navigation entries from one macro that both the desktop header and the
phone tray call, the sort buttons from one macro that the desktop toolbar and the
phone tray call, the card actions from one macro whose markup the touch action
sheet clones rather than re-renders. A mobile/desktop divergence in those is not
merely absent but unrepresentable, and that SHALL remain the primary defence.

Where a name genuinely cannot come from one place — a surface declared in one
template and named by an entry in another — the agreement between them SHALL be
asserted by a test that compares the two rendered strings to each other rather
than to a hard-coded expectation, so that editing either side alone fails. Such a
test cannot catch a new surface whose name was never in the right register to
begin with; that is why the rule is stated here in prose as well.

The short form a navigation entry renders in a narrow header SHALL NOT be treated
as a second name. It is hidden from assistive technology and the entry's
accessible name always carries the full name, so the label shortens and the name
does not.

#### Scenario: A named surface is spelled identically in every placement

- **WHEN** a surface is offered by name — in navigation, as a tray or dialog
  title, as a page heading, as the document title, or as an accessible name
- **THEN** every one of those placements uses the same Title Case string
- **AND** no placement introduces a variant spelling of it

#### Scenario: An action is sentence case

- **WHEN** a control names something the user does rather than a place they go
- **THEN** its label is sentence case, capitalising only the first word and any
  proper noun it contains

#### Scenario: A name and a description of the same destination both stand

- **WHEN** navigation names a destination "Orphans" and the page it opens is
  headed "Orphaned posters"
- **THEN** both are correct, because one names the destination and the other
  describes its contents
- **AND** each is used consistently wherever it applies

#### Scenario: A name inside a sentence keeps its casing

- **WHEN** prose refers to a named surface, as the connection screen does in
  saying the Poster Wall is unaffected
- **THEN** the name keeps its Title Case within the sentence
- **AND** this requirement does not otherwise govern the casing of that sentence

#### Scenario: A shortened navigation label is not a second name

- **WHEN** the desktop header renders a navigation entry's short form because the
  bar is too narrow for the full label
- **THEN** the short form is hidden from assistive technology
- **AND** the entry's accessible name is still the surface's full name

#### Scenario: An entry and the surface it opens are compared to each other

- **WHEN** a navigation entry names a surface that is declared in a different
  template from the entry itself
- **THEN** a test reads both rendered strings and asserts they are equal
- **AND** editing either the entry's label or the surface's name alone fails that
  test

## MODIFIED Requirements

### Requirement: In-app support ask
The application SHALL present its request for financial support as an overlay over
the page the user is already on, rather than by sending them to the project's
marketing site. The ask is one mark, one heading, one paragraph and one button —
small enough to be answered where it is made, and small enough that a page load
spent on it is a page load the user did not ask for. Reading it SHALL leave the
user exactly where they were, with nothing to navigate back from.

The overlay SHALL be available from every page that renders navigation, and SHALL
NOT depend on any page's own interactive state. Its content is fixed text and a
fixed link, so unlike the trays that hold Import from Plex, Orphans and Settings
it SHALL NOT fetch anything, and SHALL NOT fall back to navigating on a page that
cannot host it — there is no such page.

The overlay SHALL hold: a heart mark drawn from the application's icon set,
matching the one the Support Development navigation entry already wears; a heading
naming the surface; the project's support copy, stating that Marquee is free and
always will be and inviting a contribution to ongoing development; and a single
accented button labelled "Hard drive fund" linking to the project's Buy Me a
Coffee page. That button SHALL open in a new browsing context — the payment page
is genuinely elsewhere, and it is the only thing in the overlay that leaves the
app.

The heading SHALL be the name the navigation entry uses — "Support Development" —
and the overlay's accessible name SHALL be that same string. This is an instance
of the naming rule rather than a fact about this overlay: the entry and the
overlay are declared in different templates, so the name is stated in each and
they are free to drift. They have drifted once. The overlay previously read
"Support development" while the entry that opened it read "Support Development",
which a keyboard user met as a different name announced one gesture after they
chose the first. The agreement SHALL therefore be asserted by comparing the two
rendered strings, not by quoting the expected spelling twice.

The mark SHALL be presented with the heading rather than as an element of its own
above the copy. The project site centres its version of this ask, but the site's
card has no title bar and its heading is therefore the top of the composition;
an overlay's heading sits in a head beside the close control, so a mark centred
beneath that head belongs to neither the heading it names nor the copy it sits
above.

Activating the button SHALL also dismiss the overlay. The payment page opens
alongside the current page rather than replacing it, so an overlay left standing
is one the user returns from their contribution to find still asking for it. This
matches how the application already treats a destination that opens in a new
browsing context: the actions tray dismisses itself when Poster Wall is chosen.

The overlay SHALL use the application's shared dialog presentation and therefore
SHALL take the two forms that presentation already gives: on a pointer/desktop
screen a centred dialog with its own close control, and on a narrow screen an
app-style bottom tray with a grab handle, dismissable by the drag gesture, the
backdrop and Escape like every other tray. It SHALL NOT introduce a second overlay
system to achieve this.

The overlay SHALL be dismissable without contributing, by every means the shared
presentation offers, and dismissing it SHALL leave the page beneath it untouched —
not reloaded, not navigated, not scrolled.

The overlay SHALL declare itself a dialog and SHALL be focus-managed on the same
terms as every other overlay in the application: focus moves onto it when it opens
and returns to the control that opened it when it closes.

#### Scenario: The support ask opens over the current page

- **WHEN** a user activates Support Development from the desktop header's overflow
  menu or from the narrow-screen actions tray
- **THEN** the support ask opens as an overlay over the page they were on
- **AND** no navigation occurs and no new browsing context is opened
- **AND** the page beneath it is left in place

#### Scenario: The support ask holds the ask and one way to answer it

- **WHEN** the support overlay is open
- **THEN** it shows the heart mark, the heading "Support Development", the
  project's support copy, and a "Hard drive fund" button
- **AND** that button links to the project's Buy Me a Coffee page and opens it in a
  new browsing context
- **AND** the mark is presented with the heading rather than as a separate element
  above the copy

#### Scenario: The overlay is named what the entry that opens it is named

- **WHEN** the support overlay's heading and its dialog accessible name are read
  from the rendered page
- **THEN** both equal the label of the navigation entry that opens it
- **AND** editing either the entry's label or the overlay's name alone fails a test

#### Scenario: Contributing dismisses the ask

- **WHEN** a user activates the "Hard drive fund" button
- **THEN** the payment page opens in a new browsing context
- **AND** the overlay closes rather than remaining over the page behind it

#### Scenario: Presented as a dialog on desktop and a tray on a phone

- **WHEN** the support overlay is opened on a pointer/desktop-width screen
- **THEN** it is presented as a centred dialog carrying its own close control
- **WHEN** it is opened on a narrow screen
- **THEN** it is presented as an app-style bottom tray carrying a grab handle
- **AND** it names itself the same way at both widths

#### Scenario: The support ask is dismissed without contributing

- **WHEN** the support overlay is open and the user activates its close control,
  taps the backdrop, presses Escape, or drags the tray down on a narrow screen
- **THEN** the overlay closes
- **AND** the page beneath it is neither reloaded nor navigated away from

#### Scenario: The support ask is reachable from every page with navigation

- **WHEN** a user is on the Import from Plex page or the Orphaned posters page, at
  any width
- **THEN** Support Development still opens the overlay in place rather than
  navigating
- **AND** this holds without the page providing anything of its own

#### Scenario: Focus is managed like every other overlay

- **WHEN** a keyboard user opens the support overlay
- **THEN** focus moves onto the dialog and its name is announced
- **AND** the name announced is the one the entry they activated used
- **WHEN** they dismiss it
- **THEN** focus returns to the control they opened it from
