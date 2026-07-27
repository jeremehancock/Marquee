## Context

The orphans page (`OrphanController`, `templates/orphans.html.twig`,
`templates/orphans/_results.html.twig`) renders a shell that fetches
`GET /orphans/list` after paint and swaps in the scan result. Today the only
mutating control is a page-level "Delete all orphans" button that posts to
`POST /orphans/delete-all` and does a full-page redirect with a flash message.
Orphan cards are display-only.

The poster library already has the interaction pattern the user wants:
- **Desktop/pointer**: a CSS-only hover overlay (`.card__frame:hover
  .card__overlay`) exposes per-card action buttons — no JS required.
- **Touch/mobile**: tapping a card dispatches `gallery:sheet`, which opens the
  shared action tray (`.sheet`) rendering that card's `.card__actions` markup.
- Mutations use `js-mutate` forms → optional `data-confirm` → confirm modal →
  AJAX POST → in-place grid refresh → toast.

That machinery lives in `public/assets/gallery.js`, but it is gated behind the
`[data-gallery]` root and the `galleryUI` Alpine component, neither of which is
present on the orphans page. The orphans page runs its own `orphansPage` Alpine
component (loading spinner + delete-all confirm) and refreshes by re-fetching
`GET /orphans/list`, not by the gallery's `#results` model.

Constraints: PHP 8.3 strict types, thin controllers → services, Twig +
Alpine.js with no build step, PHPStan max + PHPUnit green in CI. Reuse existing
CSS classes; introduce no new visual system.

## Goals / Non-Goals

**Goals:**
- Per-orphan **Download** and **Delete** controls on every orphan card.
- Identical presentation to the library: hover overlay on pointer, action tray
  on touch.
- Single-orphan delete runs in place (no full reload), with confirmation and a
  toast, and refreshes the orphan count.
- Reuse existing CSS (`.card__overlay`, `.card__actions`, `.sheet`, toast,
  confirm modal) and the existing overlay component behavior.

**Non-Goals:**
- Changing "Delete all orphans" behavior.
- Adding library-style actions that don't apply to orphans (Change poster, Send
  to Plex, Find Posters, Copy URL, full-screen viewer). Only Download + Delete.
- Reworking the orphan scan or its detection logic.
- Optimizing the cost of the orphan scan.

## Decisions

### 1. Share the tray/confirm/toast overlays via a common Alpine mixin, not by mounting `galleryUI` on the orphans page

Two Alpine `x-data` components cannot coexist on one element, and the orphans
page needs its own scan/loading logic. Rather than fork the overlay code,
extract the reusable overlay state and methods (`sheet`, `confirm`, `toast`,
`view`, `openSheet`, `closeSheet`, `askConfirm`, `doConfirm`, `notify`) into a
shared factory in `gallery.js` and spread it into **both** `galleryUI` and
`orphansPage`. Both pages then expose the same state shape and method names.

This lets the tray/confirm/toast **markup be a single shared Twig partial**
(e.g. `templates/partials/_overlays.html.twig`) included by both
`gallery.html.twig` and `orphans.html.twig`, bound with the same `@gallery:*`
window listeners. One source of truth for the overlay look and behavior.

_Alternatives considered:_ (a) Duplicate the sheet/confirm/toast markup and
methods into the orphans page — simplest diff, but drifts over time and
violates the "same approach" intent. (b) Make the orphans page a full
`[data-gallery]` root — drags in tabs/search/pagination/`#results` refresh that
don't apply and would need to be guarded around, more risk than reuse.

### 2. Desktop overlay is free once the markup exists

The pointer overlay is pure CSS keyed on `.card__frame` / `.card__overlay`.
Rendering orphan cards with the same `.card__frame > .card__overlay >
.card__actions` structure gives the desktop hover overlay with no JS. Orphan
cards already use `.card`/`.card__frame`/`.card__image`; we add the overlay
layer with the two buttons.

### 3. Touch tray reuses the existing card-tap → `gallery:sheet` handler, generalized off `[data-gallery]`

Generalize the card-frame tap handler and the `js-mutate` submit handler so
they also bind on the orphans root. On touch, tapping an orphan card opens the
tray with that card's `.card__actions`; the existing `.sheet__body a[download]`
handler already closes the tray after a download tap — reused as-is for the
Download link.

### 4. Single-orphan delete: dedicated endpoint + service method, in-place refresh

- **Route**: `POST /orphans/delete` → `OrphanController::delete`.
- **Controller**: reads `category` + `filename` from the body, calls
  `OrphanService::delete(category, filename)`, adds a flash, redirects to
  `/orphans` (302). This keeps parity with `deleteAll` and works without JS.
- **Service**: `OrphanService::delete(PosterCategory $category, string
  $filename): bool`. It resolves the record via
  `PlexItemRepository::findByFilename()` and deletes the file + mapping **only
  if that record is actually an orphan** (its rating key is absent from Plex).
  This preserves the capability's safety guarantee that a live poster is never
  un-imported through the orphan path.
- **Frontend**: the orphan delete form is `js-mutate` with `data-confirm`, so it
  flows through the shared confirm modal. On success the component removes just
  that orphan's card from the DOM (matched by `data-category` + `data-filename`),
  decrements the count and updates the toolbar, swaps in the in-sync message when
  the last orphan is gone, and shows a toast — no full reload. Success/failure is
  read from the followed 302's flash (`.alert--success`); that redirect target
  (`GET /orphans`) only renders the shell and runs no scan, so it is cheap.

_Why not re-fetch `GET /orphans/list` after a delete:_ that endpoint re-runs a
full Plex library scan — the slow operation the page shows a spinner for on
load. Deleting one orphan does not change whether the others are orphans, so a
re-scan buys nothing and stalls the page. Removing the single card client-side
matches what a fresh scan would show, and the next page open scans fresh anyway.

_Progress overlay during a delete:_ the delete itself is still slow because
`OrphanService::delete` verifies the target against a fresh Plex scan before
removing it (see decision 5), so both the single delete (AJAX) and delete-all
(native form post) raise a `deleting` flag that shows the existing scan-style
overlay until the operation resolves. A cheaper single-rating-key existence
check would shrink this to a blink, but that needs a new `PlexClient` method
across the real and fake clients — deferred as a separate optimization.

### 5. Verify orphan status without a per-delete full Plex scan where possible

`OrphanService::delete` needs to confirm the target is an orphan. Reusing
`findOrphans()` (a full library scan) per single delete is correct but slow if
several are deleted in a row. Decision: check only the one record — resolve it
by category+filename, then confirm its single rating key is absent from Plex
(reusing the same "current rating keys" logic, scoped to what's needed). If
scoping the check to one key proves awkward against the current
`collectCurrentRatingKeys` shape, fall back to `findOrphans()` membership; a
full scan per delete is acceptable and matches `deleteAll`'s cost. Correctness
(never delete a non-orphan) takes priority over this optimization.

## Risks / Trade-offs

- **Overlay mixin refactor touches the working library page** → Extract behavior
  without changing `galleryUI`'s public surface; verify the library page's
  tray/confirm/toast/change-poster flows still work after the split. Covered by
  existing functional tests plus a manual pass.
- **Shared overlay partial must work under both Alpine roots** → Both roots
  expose the same state names and `@gallery:*` bindings; keep the partial free
  of page-specific references.
- **Single-orphan delete could un-import a live poster if the orphan check is
  skipped** → The service always verifies the target is a true orphan before
  deleting; a non-orphan request is a no-op (or flashes an error). Covered by a
  functional test asserting a present poster is preserved.
- **Duplicate rating keys / filename collisions** → Filenames are unique within
  a category and `findByFilename` is category-scoped, matching how
  `deleteByRatingKey` already keys deletion.

## Migration Plan

Additive change: new route, new controller/service method, template and JS
edits. No schema or data migration, no config changes. Rollback is reverting
the commit; the delete-all path and existing scan behavior are untouched.

## Open Questions

- Should single-orphan deletion surface a per-item toast message (e.g. the
  poster title) or a generic "Orphan deleted"? Default: a generic toast, matching
  the library's delete feedback. Can be refined during apply.
