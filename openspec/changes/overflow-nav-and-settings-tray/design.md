## Context

The shared layout carries the secondary navigation in two places from one source
of truth: `nav.secondary_links()` in `templates/partials/_nav_macros.html.twig`
renders both the desktop header group in `layout.html.twig` and the mobile actions
tray in `partials/_menu.html.twig`.

Two constraints already recorded in the codebase shape this change:

- **The header is a containing block.** `.topbar` carries `backdrop-filter`, which
  makes it a containing block for `position: fixed` descendants. That is what
  forced the mobile tray to be teleported to `<body>` — the trap is written up at
  length in `partials/_menu.html.twig:7-24`. Any panel hung off the header must be
  `position: absolute`, never `fixed`.
- **The header's contents are aligned to the 960px content column**, not the
  viewport (`app.css:176-183`, deliberately matching the project's landing page).
  A wider monitor buys the navigation no room. Measured against that budget:
  brand ≈ 123px with the title "Marquee" and ≈ 250px with a real one, six labelled
  items ≈ 610px, connection status ≈ 91px — 824px to 909px of 960. The existing
  icon-only fallback at 641–900px already handles the narrow end; the crowding is
  at the wide end, where labels are on.

On the mobile side, `_loadTray(url, ref)` in `public/assets/gallery.js:878-899`
already does the whole job of presenting a page inside a tray: fetch, parse,
strip the "Back to gallery" link and the `<h1>`, inject, and re-run
`Alpine.initTree`. Import and Orphans both ride on it. `.sheet__panel--tall`
(`app.css:382-385`) exists for exactly "trays that hold a whole page".

`SettingsController` distinguishes its two outcomes by status: `302` to `/settings`
on a valid save, `200` re-rendering the form on an invalid one.

## Goals / Non-Goals

**Goals:**

- Keep the desktop header within its width at any `SITE_TITLE`, with labels on.
- Give Settings the same tray presentation on a phone that Import and Orphans have.
- Add no server-side code. The controller already answers what the tray needs.
- Keep the single source of truth for the link set intact across all three
  placements (bar, overflow menu, mobile tray).

**Non-Goals:**

- Reworking the Plex connection status. It stays in the bar, unchanged, as a
  reading rather than a destination.
- A pinned save button in the settings tray. Deferred — see Decision 7.
- Making the trays available outside the gallery. `/plex` and `/orphans` host no
  trays today and will not host a settings tray either; that seam is inherited.
- Widening the header past the content column, or dropping labels on desktop
  entirely. Both were considered and rejected — see Decision 1.

## Decisions

### 1. Split the bar rather than drop labels or widen the header

Three ways to fit the header were weighed:

| Option | Bar width | Why not |
| --- | --- | --- |
| Icons only at every desktop width | ≈ 327px | Smallest of the three, but Poster Wall and Support Development are weak glyphs, and it trades a permanent loss of legibility for a width problem that only bites at long site titles |
| Let the bar span the viewport | unchanged | Breaks the deliberate alignment with the content column, and fixes nothing below ~1100px |
| **Bar + overflow menu** | **≈ 422px** | **Chosen** |

The overflow is not merely the cheapest fit — it is the only one that states a
hierarchy. Poster Wall, Import, and Orphans are what you came to do; Settings,
Support, and Log out are housekeeping. Poster Wall stays in the bar despite
opening in a new tab: it is the feature people open the app to show someone, and
demoting it two clicks to save ~80px is not a trade worth making.

The split composes with the existing 641–900px icon-only fallback rather than
replacing it. The overflow control has no label to drop, so the two mechanisms are
independent, and together the bar fits a very long title at every width.

### 2. `position: absolute`, and a new rung on the elevation ladder

The panel hangs off `.topnav` (which gains `position: relative`). Not `fixed` — see
Context.

Absolute positioning alone is not enough. `.gallery-head` is
`position: sticky; z-index: 30` (`app.css:1002-1006`) and sits directly beneath the
header in the content region the panel opens over, so the panel needs to out-stack
it.

**The z-index goes on `.topbar`, not on the panel**, and this was got wrong first
time round — shipped to `:dev` and caught by eye. `backdrop-filter` does two things
to the header, and only one of them is written up in the codebase: it makes the
header a containing block for fixed descendants (the trap that teleported the phone
tray), **and it makes the header a stacking context**. Every z-index inside the
header is therefore resolved against the header, and the header itself paints where
an unpositioned in-flow element paints — below `.gallery-head`'s 30. The panel's own
`z-index: 35` looked correct, tested green, and could never have worked.

So `.topbar` takes `position: relative; z-index: 35` — a new rung between "pinned
controls 30" and "tab bar 40". `relative` is there only because z-index does nothing
to a static element; the header stays in flow and still scrolls away. The panel
keeps a z-index of its own, but it now only orders it against its siblings inside
the header.

This is safe against the controls it now outranks: the header sits above them in
flow with the container's padding between, and `.gallery-head` only sticks once the
header has scrolled entirely off the top, so the two boxes never share space.

Two things must be updated by hand:

- The ladder comment at `app.css:21-27`, which is the ladder's only prose home.
- `DesignTokenContractTest::testElevationAgreesWithTheStackingLadder`. That test
  transcribes the tiers into its own assertions rather than reading them out of the
  CSS — a deliberate limit documented in the same comment — so adding a rung means
  re-reading the tiers, not trusting the suite to notice.

The panel is desktop-only, so it never coexists with the tab bar or a tray; 35 is
chosen for where it sits in the story, not because anything at 40 or 50 is on
screen with it.

### 3. The overflow trigger reuses the mobile menu button's glyph

The same three-dot "more actions" mark, so one affordance means one thing at both
widths. On a phone it holds all six entries; on desktop it holds the three the bar
does not. That is a consistent promise — "the rest of the actions are here" —
rather than two different controls that happen to look alike.

### 4. Current-page marking carries through the trigger

The spec requires the current destination be marked rather than linked. Putting
Settings behind a click hides that marker on `/settings`, so the trigger takes the
`--current` treatment whenever the page being viewed is one the panel holds. The
entry inside the panel is still rendered as a `<span>` rather than an anchor, by
the existing `item()` logic — no change there.

Determining "is the current destination inside the panel" is a Twig-level check
against the overflow group's keys, done where the group is defined so the two
cannot drift.

### 5. `nav.secondary_links()` splits into two named groups

`item()` is unchanged except for emitting a `data-settings` hook alongside the
existing `data-import` / `data-orphans`. `secondary_links()` becomes two macros —
a bar group and an overflow group — and the mobile tray renders both in sequence,
preserving the single source of truth. The panel rows reuse `item()` under a
container class that turns full labels on and tooltips off, which is what
`.menu__body` already does for the mobile tray; the same rules extend to the panel
rather than being duplicated.

### 6. The settings tray reads the controller's status, not a new response shape

`saveSettings(form)` posts the form's `FormData` with `redirect: 'manual'`:

```
POST /settings  (redirect: 'manual')
   │
   ├── response.type === 'opaqueredirect'   → valid, saved
   │      └── close tray → location.reload()
   │
   └── 200                                   → invalid
          └── swap the re-rendered fragment back in, re-bind submit
```

Two alternatives were rejected:

- **A JSON branch in `SettingsController` keyed on `X-Requested-With`.** Explicit
  and testable, but it adds a second response shape to a controller whose two
  branches already differ by status, for no information the status does not carry.
- **`redirect: 'follow'` plus `response.redirected`.** Reads more plainly, but the
  followed `GET /settings` consumes the `Settings saved.` flash from the session in
  a response that is thrown away. `manual` leaves the flash in place, and the
  gallery already renders flash (`templates/gallery.html.twig:64`), so the reload
  surfaces the confirmation for free — under the new title and sort, which is
  exactly where a user should see it.

`redirect: 'manual'` yields `type: 'opaqueredirect'`, `status: 0`, `ok: false`. That
is the success branch here, which is unusual enough to deserve a comment at the call
site.

### 7. Save-and-reload, and the button stays where it is

Reload rather than close-and-refresh. The settings on this form change the header
(site title) as well as the grid (page size, default sort, article-aware sorting,
library exclusions); `gallery:refresh` redraws the grid but not the header, so a
partial update would leave the page half-describing the old configuration. The
reload is also the acknowledgement: the confirmation rides on it via the untouched
flash.

The Save button stays at the end of the form for this pass. A pinned tray footer is
the obvious refinement, but it needs either a button outside the form wired by
`form="…"` (with the in-fragment one stripped, adding a second thing `_loadTray`
must know about the settings page) or JS forwarding a click into `requestSubmit()`.
Neither is hard; both are easier to judge after using the real thing.

### 8. The settings tray is fetched fresh on every open

Import fetches once (a configuration form does not decay); Orphans re-fetches (a
scan does). Settings re-fetches, for two reasons: the library list comes from Plex
and can change or start failing, and the superseded-variable notice depends on the
environment as much as the store.

Re-fetching means re-running `Alpine.initTree` over a fresh fragment, which
`gallery.js:874-877` warns can re-bind whatever the fragment binds on init. That
hazard does not apply here: `settings.html.twig` and `partials/_form.html.twig`
carry no `x-data`, `x-model`, or `x-init` at all. Worth re-checking if the settings
form ever gains a component.

The body is cleared on close, so a tray reopened after a failed save starts from the
server's state rather than the rejected submission.

## Risks / Trade-offs

- **The elevation-ladder test will not catch a wrong rung** → The test transcribes
  tiers by hand and never reads a `z-index` out of the CSS. Adding 35 means editing
  the ladder comment and the test's assertions together, and eyeballing the panel
  over an open gallery. Called out as its own task rather than folded into the CSS
  work. *This risk landed:* the by-eye task is what caught the stacking-context
  mistake above, after a green suite had signed the arrangement off. `HeaderOverflow
  MenuTest` now compares `.topbar` against `.gallery-head` — the pair that actually
  decides the paint order — and that assertion has been checked against a revert.

- **An expired session's redirect reads as a successful save** → The auth middleware
  answers an unauthenticated POST with a 302, which is indistinguishable from the
  save redirect under `redirect: 'manual'`. The tray closes and reloads, and the
  reload lands on the sign-in screen. That is the correct outcome — the user does
  need to sign in — but the settings they typed are lost. Accepted: the same is true
  of submitting the page today.

- **Settings still navigates on `/plex`, `/orphans`, and `/connect`** → The tray lives
  in the gallery's Alpine root, and the click delegate is gated on `[data-gallery]`
  being present. Import and Orphans have the same seam today, so this adds no new
  inconsistency, but it does mean the same tap does two different things depending on
  which page you are on. Lifting all three trays into the layout is the fix, and is
  deliberately out of scope.

- **A long form in a bottom sheet is the thing the old spec said not to build** →
  Mitigated by `.sheet__panel--tall` (92vh, min 60vh) and the body's own scroll with
  `overscroll-behavior: contain`, which is the presentation Import and Orphans already
  use for whole pages. If it still feels wrong in use, the pinned save footer from
  Decision 7 is the first lever, not a revert.

- **Hiding Settings behind a click on desktop** → Accepted deliberately: it is
  configuration, reached rarely, and the trigger is marked current while you are on it
  so the location is never ambiguous.

## Migration Plan

None. No data, no configuration, no routes change. `/settings` continues to serve the
same page to the same requests; everything here is presentation in the shared layout
and the gallery's client code. Rollback is reverting the commit.

## Open Questions

- Does the tall settings tray want the gallery's `gallery:refresh` as a fallback if a
  future change makes the reload undesirable? Not now — reload is the contract the spec
  states — but it is the seam to pull if the reload proves jarring in use.
- Should the overflow panel animate, and if so against which duration token? The trays
  and dialogs have entrance transitions; a header popover may read better instant.
  Defer to what it looks like on the screen.
