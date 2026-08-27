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
accented button labelled "Buy me a coffee" linking to the project's Buy Me a
Coffee page. That button SHALL open in a new browsing context — the payment page
is genuinely elsewhere, and it is the only thing in the overlay that leaves the
app.

The button's label SHALL name where it goes. It previously read "Hard drive fund",
a callback to the joke in the paragraph above it, which meant the one control in
the overlay described the maintainer's motive rather than the user's next step —
and a user who skipped the paragraph, as a user reading a dialog reasonably may,
met a button that named nothing they could act on. The paragraph keeps the joke;
the button does not carry it. This is an instance of the existing rule that a
label states its action, not a fact peculiar to this overlay, and it is why the
label is not free to drift back toward a private reference.

The heading SHALL be the name the navigation entry uses — "Support Development" —
and the overlay's accessible name SHALL be that same string. This is an instance
of the naming rule rather than a fact about this overlay: the entry and the
overlay are declared in different templates, so the name is stated in each and
they are free to drift. They have drifted once. The overlay previously read
"Support development" while the entry that opened it read "Support Development",
which a keyboard user met as a different name announced one gesture after they
chose the first. The agreement SHALL therefore be asserted by comparing the two
rendered strings, not by quoting the expected spelling twice.

The button's label is a different kind of string and is asserted differently. It
is not a surface name, so it is sentence case and is stated in exactly one place —
the overlay template — with nothing to agree with. Quoting it in a test is
therefore safe in the way quoting the heading is not.

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
  project's support copy, and a "Buy me a coffee" button
- **AND** that button links to the project's Buy Me a Coffee page and opens it in a
  new browsing context
- **AND** the mark is presented with the heading rather than as a separate element
  above the copy

#### Scenario: The button names its destination rather than the joke above it

- **WHEN** the support overlay's call to action is read on its own, without the
  paragraph above it
- **THEN** its label says what activating it does — it names the payment
  destination, not the maintainer's reason for asking
- **AND** the label "Hard drive fund" no longer appears anywhere in the
  application, its templates, or its tests

#### Scenario: The overlay is named what the entry that opens it is named

- **WHEN** the support overlay's heading and its dialog accessible name are read
  from the rendered page
- **THEN** both equal the label of the navigation entry that opens it
- **AND** editing either the entry's label or the overlay's name alone fails a test

#### Scenario: Contributing dismisses the ask

- **WHEN** a user activates the "Buy me a coffee" button
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
