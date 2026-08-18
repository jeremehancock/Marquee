## Why

Adding Settings to the secondary navigation pushed the desktop header past what
it can hold, and gave the phone a navigation entry that behaves unlike every
other one beside it.

On desktop the header's contents are aligned to the 960px content column, and
they now carry a brand of unbounded width — `SITE_TITLE` is user-configurable —
plus six labelled actions and the Plex connection status. With the default title
that is roughly 824px of 960; with a real one ("Anansi Media Library") it is
roughly 909px. The bar is one long title away from crowding, and Settings was
the sixth straw rather than the cause.

On a phone, Import from Plex and Orphans open as trays over the gallery, but
Settings navigates to its own page — where the way back is a "Back to gallery"
link, which is a desktop gesture on a device that has a swipe and a backdrop for
exactly this. The one entry that leaves the gallery is the one a user reaches for
to change how the gallery behaves.

## What Changes

**Desktop header splits into a bar plus an overflow menu.**

- The bar keeps Poster Wall, Import from Plex, and Orphans — the actions that
  operate on your poster library — followed by a single overflow control and then
  the Plex connection status.
- The overflow menu holds Settings, Support Development, and Log out: the
  housekeeping, the external ask, and the session exit.
- The connection status stays in the bar. A status you have to click for is not a
  status, and it deliberately does not present as a destination.
- The overflow control reuses the same three-dot "more actions" glyph the phone
  menu button already uses, so one affordance means the same thing at both widths.
- The existing icon-only fallback between 641px and 900px is kept, and composes
  with the split: the trigger has no label to drop.
- Where the destination being viewed lives inside the overflow menu, the trigger
  itself is marked as current, so hiding an entry behind a click does not hide
  that you are on it.

**Settings opens as a tray on a phone.**

- Choosing Settings from the actions tray opens the settings screen as a tray over
  the gallery, in the same tall presentation Import and Orphans already use, rather
  than navigating away.
- Saving from the tray closes it and reloads the page, so the gallery underneath is
  redrawn under the settings that were just saved — a changed site title, page size,
  default sort, or library exclusion all take effect immediately and visibly. The
  "Settings saved." confirmation lands on the reloaded gallery.
- A submission that fails validation stays in the tray with its errors, exactly as
  the page does.
- `/settings` remains a real page. Desktop, deep links, and pages that host no
  gallery all continue to navigate to it; the tray is a second presentation of the
  same screen, as it is for Import and Orphans.
- **BREAKING** (spec-level, not user-facing): this reverses the current requirement
  that Settings navigate to its own page at every width, and the rationale behind
  it. The tall tray presentation and the save-and-reload contract are what replace
  that reasoning.

## Capabilities

### New Capabilities

None. Both halves of this change are the shared layout's navigation, which
`application-shell` already owns.

### Modified Capabilities

- `application-shell`: two requirements change. **App-wide mobile actions menu** no
  longer carves Settings out as the one entry that navigates; it becomes a tray like
  Import and Orphans, with a defined save-and-reload contract and a defined behaviour
  on validation failure. **Secondary navigation in the desktop page header** gains a
  two-tier structure — a bar and an overflow menu — with a stated split, dismissal and
  accessible-name requirements for the trigger, and current-page marking that carries
  through the trigger when the current destination sits inside the menu.

## Impact

**Templates**

- `templates/layout.html.twig` — the desktop nav region splits; `moreOpen` joins the
  existing `menuOpen` scope on `.topnav`.
- `templates/partials/_nav_macros.html.twig` — `secondary_links()` splits into a bar
  group and an overflow group; `item()` emits a `data-settings` hook.
- `templates/gallery.html.twig` — a new settings tray.

**Assets**

- `public/assets/app.css` — the overflow panel, and a new rung on the elevation ladder.
  The panel must out-stack `.gallery-head` (sticky, `z-index: 30`), which sits directly
  beneath the header.
- `public/assets/gallery.js` — the secondary-destination click delegate gains a
  `data-settings` clause; a new `openSettings` / `closeSettings` / `saveSettings` trio on
  the gallery component, reusing the existing `_loadTray` helper.

**PHP**

- None. `SettingsController` already answers the two cases the tray needs — 302 on save,
  200 re-render on invalid — so the tray reads them without a server change.

**Tests**

- `DesignTokenContractTest::testElevationAgreesWithTheStackingLadder` — the ladder gains a
  rung and is transcribed by hand, so it must be re-read rather than assumed.
- New asset/template assertions for the two-tier nav split and the settings tray wiring,
  alongside the existing navigation tests.

**Docs**

- `README.md` describes Settings as living "in the header"; on desktop it now lives behind
  the overflow control there, and on a phone it opens as a tray. The mobile-experience
  passage and the "Does it work on mobile?" answer both name the trays and should name this
  one.
