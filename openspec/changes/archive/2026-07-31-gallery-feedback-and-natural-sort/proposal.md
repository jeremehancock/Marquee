## Why

Two things in the gallery make it feel broken. Applying a poster found through
**Find Posters** takes seconds — Marquee downloads the full-resolution image
from the source and uploads it to Plex — but the confirm button gives no sign
that anything is happening, so the natural response is to tap it again. And
**A–Z** is not the order a person reads as alphabetical: seasons list as
Season 1, Season 10, Season 11 … Season 2, because titles are compared
character by character and "1" sorts before "2" regardless of the number it
belongs to.

## What Changes

**Find Posters: show that the change is running, and let it only run once.**

- Confirming **Change poster** in the Find Posters preview immediately raises a
  full-screen progress overlay with a spinner and a message, held until the
  change succeeds or fails.
- The **Change poster** button is disabled while the change is in flight, and a
  repeat activation is ignored even if it lands before the overlay paints.
- The overlay clears on failure as well as success, so a failed change leaves
  the preview usable rather than stranded behind a spinner.
- The overlay is shown *immediately*, with no grace delay — unlike the deferred
  dim used for in-place view changes, which exists so that a fast, cached tab
  switch never flashes. This operation is never fast.
- Scope is exactly the Find Posters preview path, on desktop and touch. The
  Upload and From URL tabs already route through the gallery's busy tracker and
  are unchanged.
- Small hardening, easily struck if unwanted: the apply request does not check
  the response status today, so a server error is parsed as if it were a
  success page. Treat a failed response as a failure.
- Spec drift found while writing this up, and corrected here because it
  describes the very button being changed: the spec says each candidate has an
  apply action "labelled Select", but the shipped UI has no such button — a
  candidate is tapped to preview it, then confirmed through **Use this poster**
  and **Change poster**. No behaviour changes; the spec is brought in line with
  what already ships.

**A–Z: order numbers by value, not by digit.**

- Runs of digits in a poster's sort title are compared by numeric value, so
  Season 2 precedes Season 10, and "Ocean's 8" precedes "Ocean's 11".
- The same ordering applies wherever the gallery orders by title: a single
  category, the aggregate **All** view, and the tie-break between equally
  relevant search results. Without the last of these, searching a show would
  still list its seasons out of order.
- Nothing displayed changes — this affects the sort key only, never a caption,
  a filename, or anything written to disk.
- Date-added ordering (timestamps) and the poster wall (random) are unaffected.

Not in scope, having been considered and rejected: moving the sort key off the
filename-derived title and onto the title recorded from Plex. It does not fix
the punctuation-ordering oddity it appears to (a title like `M*A*S*H` sorts
ahead of `Mad Men` either way, because punctuation and spaces both sort below
letters — the cause is punctuation participating in the sort at all, not the
filename), and posters with no Plex record would fall back to the filename,
leaving the library sorted under two different schemes at once.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: adds a requirement that applying a found poster shows
  progress for as long as it runs and cannot be started twice, and corrects
  *Preview and apply a found poster*, which still describes a per-candidate
  "Select" button that the shipped UI replaced with a preview-and-confirm flow.
- `poster-library`: amends *Article-aware ordering* and *Aggregate view
  ordering* so title comparison is digit-aware, and scopes *Deferred loading
  indication for in-place view changes* to view changes rather than mutations.
- `search`: amends the relevance tie-break to use the same digit-aware title
  comparison.

The Find Posters half targets `poster-sources` rather than `poster-editing`:
the preview-and-apply flow's user-facing behaviour is specified there, and
splitting one flow across two specs would leave neither readable on its own.

## Impact

- `public/assets/gallery.js` — `applyFinderSelection()` gains an in-flight flag,
  a re-entrancy guard, a response-status check, and a `finally` that clears the
  flag on both paths; `finder` state gains the flag.
- `templates/gallery.html.twig` — a progress overlay in the Find Posters
  preview, and a disabled binding on the confirm button.
- `public/assets/app.css` — no change expected. `.overlay`, `.overlay__box`,
  and `.spinner` already exist, already carry a reduced-motion opt-out, and
  `.overlay` already stacks above the preview viewer.
- `src/Poster/Poster.php` — `sortKey()` becomes digit-aware.
- `src/Poster/Search/PosterSearch.php` — the tie-break key becomes digit-aware.
- `src/Poster/PosterLibrary.php` — no change expected; it consumes `sortKey()`
  and its category tie-break is unaffected.
- Tests: unit coverage for the sort key and the search tie-break, and
  functional coverage for gallery ordering.
- No configuration, database, dependency, or Docker change. No user-facing
  documentation change expected; `README.md` and `docs/` describe the A–Z
  toggle and the Find Posters flow in terms this change does not alter, which
  is to be confirmed rather than assumed.
