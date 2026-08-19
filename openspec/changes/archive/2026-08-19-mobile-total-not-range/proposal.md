## Why

On a phone the gallery says "Showing 1–24 of 1948" and keeps saying it after you
have scrolled past two hundred posters. A phone has no pager — it loads more as
you scroll — so the sentence names a page that does not exist, describes a range
that stopped being true at the first batch, and points at a pagination control
hidden directly below it. A reader who wants to know how large a category is has
to ignore the only line on the screen that claims to tell them.

## What Changes

- On a narrow screen the gallery reports the size of the category — `Total: 1948`
  — instead of a range of a page.
- On a pointer/desktop screen nothing changes: the pager exists there, and
  "Showing 1–24 of 1948" is exactly what it means.
- The spec is corrected. It currently requires the broken behaviour: the
  pagination requirement says the gallery "reports how many posters are shown out
  of the total" without qualification, which the infinite-scroll requirement then
  makes impossible to honour on a phone.

Search results are unchanged — "3 matches for “x” in Movies" is already a count
rather than a range, and stays true however far you scroll. The empty state is
unchanged.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: the paginated listing reports a range only where a pager is
  shown; on a narrow screen, where infinite scroll replaces the pager, it reports
  the category total instead.

## Impact

- `templates/partials/gallery_results.html.twig` — the count line renders both
  sentences.
- `public/assets/app.css` — the existing `max-width: 640px` block, which already
  hides `.pagination`, chooses which sentence is shown.
- `tests/Functional/GalleryTest.php` — extended for both sentences being
  rendered.
- `tests/Unit/Asset/` — a new CSS-reading test pinning which sentence the narrow
  block hides.

No PHP, no routing, no JavaScript, and no data changes. `PosterPage` already
exposes the total; nothing new is computed.
