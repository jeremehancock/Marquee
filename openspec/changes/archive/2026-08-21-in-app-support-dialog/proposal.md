## Why

Support Development is the one entry in Marquee's navigation that throws the user
out of the app: it opens `getmarquee.now/#support` in a new tab, so someone who
means to give five dollars lands on a marketing page, scrolls to an anchor, and
reads the pitch for software they already installed and are already using. The
ask itself is one paragraph and one button — small enough to answer where it is
made. Keeping the user in place also makes the ask honest about its own weight:
it is a thing you can dismiss with a swipe, not a destination worth a page load.

## What Changes

- Support Development stops navigating. Choosing it — from the desktop header's
  ⋯ menu or the phone's actions tray — opens an in-app overlay over the current
  page instead of opening a new browsing context.
- The overlay carries the support ask ported from the marketing site: the heart
  mark, the "Support development" heading, the existing copy, and the
  "Hard drive fund" button linking to Buy Me a Coffee. That button is the only
  thing left that opens a new tab, because the payment page is genuinely
  elsewhere.
- One overlay, two presentations, from the shared `.modal` component the app
  already reshapes at 640px: a centred dialog with a close button on a pointer
  screen, an app-style bottom tray with a grab handle and drag-to-dismiss on a
  phone.
- The overlay is available on **every** page that draws the navigation, not just
  the gallery — its content is static, so unlike the Import/Orphans/Settings
  trays it needs no fetch and no per-page fallback to a page load.
- **BREAKING** (spec-level, not user-facing data): the requirement that Support
  Development open `https://getmarquee.now/#support` in a new browsing context is
  removed and replaced.

## Capabilities

### New Capabilities

None. The navigation, its two placements, and what each entry does are already
owned by `application-shell`.

### Modified Capabilities

- `application-shell`: Support Development becomes an in-app overlay rather than
  an external link. Replaces the "Support Development opens the project's support
  page" requirement and scenario; amends the actions-menu requirement that
  describes Support as one of the entries opening a new browsing context; adds
  what the overlay holds, how it is presented at each width, and how it is
  dismissed.

## Impact

- `templates/partials/_nav_macros.html.twig` — the Support entry becomes a
  control that opens an overlay rather than an anchor with `target="_blank"`;
  `overflow_current_class()` drops `support`, which can no longer be a page one
  is on.
- `templates/layout.html.twig` — the topbar's Alpine scope gains the overlay's
  open state, so both placements of the entry drive one overlay.
- New `templates/partials/_support.html.twig` — the overlay, teleported to
  `<body>` for the same containing-block reason `_menu.html.twig` is.
- `public/assets/app.css` — the overlay's internal layout (mark, heading, copy,
  button). No new overlay system; `.modal` and its 640px reshaping already exist.
- `tests/Functional/ApplicationShellTest.php`, `tests/Functional/GalleryTest.php`
  — both assert the current external href and the entry's link-ness.
- `tests/Functional/DialogFocusTest.php` — a new declared dialog falls under the
  focus contract it pins.
- No PHP, no routes, no settings, no new dependency.
