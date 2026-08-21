## Why

Every tray in Marquee slides up from the bottom edge wearing the same grab
handle, so a user reads them as one thing appearing in one way. They are not
spaced like one thing: the distance from the handle to the tray's title is
16.4px in six of the trays, 7.3px in three, and 13.2px in Support development.
The eye catches the inconsistency before it can name it — the trays look
subtly misaligned as a set, and the Support tray's heart tile sits 2px under
the handle, close enough to read as a mistake.

The cause is that bottom trays grew from two independently styled lineages
that were never reconciled. Nothing is broken, so nothing has ever failed;
the drift is only visible when the trays are opened one after another.

## What Changes

- Every mobile tray head — whether the tray is a `.sheet__panel` or a
  `.modal__panel` docked to the bottom edge — separates the grab handle from
  its title by the same distance.
- The normalized value is the **largest** of the three that exist today, so no
  tray gets tighter and no title gets smaller. Six trays gain a little room,
  three gain a lot, and Support development's heart tile stops crowding the
  handle.
- A tray head's height is set by its **title**, not by whatever else the head
  happens to hold. Support development's 40px heart tile currently drives that
  height and pushes its title down; after this change the tile is centred on
  the title's line without resizing the row. This is what makes the rule hold
  for the next tray head that adds an icon, badge, or taller control.
- No behavior changes. Nothing moves on desktop, no tray gains or loses a
  control, and the drag-to-dismiss gesture is untouched.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `visual-design`: adds a requirement that every tray head presents its title
  at the same distance below the grab handle, and that a head's height is
  governed by its title rather than by its other contents.

## Impact

- `public/assets/app.css` — three rules: `.modal__head` inside the
  `@media (max-width: 640px)` block, `.sheet__title`, and `.support-ask__head`.
- `tests/Unit/Asset/` — a new shape tripwire in the family of
  `AlertGlyphClearanceTest` and `TraySurfaceTest`, pinning the shared head
  contract so a future re-tune cannot quietly reintroduce the drift.
- No templates, no PHP, no behavior. The `.sheet__grip` / `.sheet__handle`
  rules are already shared by both tray families and stay as they are.

### Explicit non-goals

- **The divider stays asymmetric.** `.sheet__head` draws a `border-bottom` and
  a 12px `margin-bottom`; `.modal__head` draws neither. After this change the
  gaps match but the heads still will not look identical. Unifying the divider
  is a separate product decision about whether a confirmation wants a rule
  under its heading, and it is not made here.
- **Desktop dialogs are untouched.** The padding change lives inside the mobile
  block and `.modal__head h2` itself does not change. The only effect above
  640px is a `.sheet` opened at a wide viewport, whose title grows by 1.6px.
- **`.sheet__title` stays a `<span>`.** Giving tray titles heading semantics
  may be worth doing; it is a separate accessibility question and changing the
  element here would bundle two unrelated decisions into one diff.
