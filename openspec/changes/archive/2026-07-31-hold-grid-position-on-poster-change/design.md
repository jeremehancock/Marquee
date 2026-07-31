## Context

Every poster mutation in the gallery ends the same way: `submitForm()` posts the
form, then calls `load(currentUrl(), false)`, which re-fetches the current view
and replaces `#results` wholesale. `applyFinderSelection()` in the change tray
does the same by dispatching `gallery:refresh`. That single path serves four
very different mutations — replace this poster's image, re-send it to Plex,
delete it, import a library — and it is only correct for the ones that change
which posters exist.

For a poster *change* it is actively harmful. Two things go wrong:

1. **Infinite scroll depth is lost.** `currentUrl()` is the page-1 URL on a
   phone, because infinite scroll appends pages without touching the URL. The
   swap therefore replaces an N-page grid with a 1-page grid. The document
   collapses, the browser clamps the scroll offset to the new (much shorter)
   height, `setupInfinite()` sees its sentinel on screen and starts appending
   pages again. The user watches the grid rebuild itself and ends up near the
   top instead of where they were.
2. **Even when depth is preserved** (desktop, where `currentUrl()` carries
   `?page=`), the whole grid is re-fetched and every `<img>` re-rendered to
   change one image.

The gallery's scroll-lock already restores the exact offset when the change tray
closes; it does that correctly, and then the grid swap invalidates it. So the
fix is not more scroll bookkeeping — it is not destroying the grid in the first
place.

Two facts make a card-local update safe:

- A poster change never reorders the grid. Both sort orders are
  `alphabetical` (filename/title) and `date_added` (the Plex `addedAt`
  timestamp); neither reads the poster file's mtime.
- A poster change never changes membership. Posters enter the library only
  through `plex-import`, and a change keeps the poster's filename, category and
  Plex mapping. So the counts in the stats line, the pagination, and the
  linked/unlinked badge are all still correct afterwards.

The only thing that changes on screen is one card's image bytes — and its URL,
which is `"/posters/<category>/<filename>?v=" ~ mtime` and therefore differs
after the file is rewritten.

## Goals / Non-Goals

**Goals:**

- A poster change leaves scroll position, loaded grid extent, and every other
  card byte-identical.
- The changed card shows the new image without a cache hit on the old one.
- The fallback when the card cannot be found is the current full refresh, so no
  path can leave a stale image on screen.
- "Send to Plex", which writes nothing locally, stops refreshing the grid.

**Non-Goals:**

- Preserving position across **delete** and **import**. Both change which
  posters exist, so the stats line ("Showing 1–24 of 300") and the pagination
  control go stale; holding position there means reconciling counts and page
  boundaries, which is a larger change with its own spec work.
- A general "restore infinite-scroll depth after a full refresh" mechanism. It
  would fix delete and import too, but it costs one request per loaded page and
  is the wrong tool when the correct answer for a change is *zero* requests.
- Any server-side change. No new endpoint, query parameter, or response shape.

## Decisions

### Update the card, don't re-fetch the grid

For the four operations that replace a poster's stored image — `change/upload`,
`change/url`, Find Posters apply, and `fetch-from-plex` — locate the poster's
card in the live DOM and rewrite only what depends on the image URL. No fetch,
no `#results` swap, no `setupInfinite()` teardown, so nothing can move.

*Alternative considered — refetch pages 1..N and concatenate.* Preserves depth
and would fix delete and import as well, but issues N requests to restore a grid
that never needed to change, and still re-renders every card. Rejected as the
answer for a change; it stays available as the answer for delete/import if that
is ever taken on.

*Alternative considered — capture and restore `window.scrollY` around the swap.*
Doesn't work: the restore target doesn't exist yet. Page 2..N are not in the
document at the moment of the swap, so any offset past page 1 is clamped away
before infinite scroll can re-append them.

### Identify the card the way the DOM already does

Each card renders a change button carrying `data-filename` and `data-category`
(the pair is required because a filename is unique only within its category in
the All view). That pair is the card's identity for the purposes of this change;
no new identifier is introduced. The lookup is
`[data-action="change"][data-filename=…][data-category=…]`, then `.closest('.card')`.

### The forms declare what kind of refresh they need

`submitForm()` currently treats every `.js-mutate` form identically. Rather than
matching on the action URL — brittle, and it would put routing knowledge in the
delegation handler — each poster-mutating form declares its intent in markup:

- image-replacing forms (`change/upload`, `change/url`, `fetch-from-plex`)
  declare a card-local refresh, and carry the category the card lookup needs
  (the form action already encodes it, but the handler should not have to parse
  a URL to get it). The other half of the card's identity, the filename, is read
  from the submitted form data — every one of these forms already posts it — so
  the card that gets updated is by definition the poster the request acted on,
  rather than a second copy in an attribute that could drift from it;
- `send-to-plex` declares that it needs no refresh at all;
- `delete` declares nothing and keeps the existing full-grid refresh, which
  stays the default so any future form is safe by omission.

`applyFinderSelection()` does not go through `submitForm()`; it replaces its
`dispatch('gallery:refresh')` with the same card-local update, addressed by the
`change.category` / `change.filename` it already holds.

### Bust the cache client-side

The server computes `?v=<mtime>`, but the client does not know the new mtime
without asking for the grid — which is the thing being avoided. The card update
therefore strips any existing query string from the current `src` and appends a
fresh, monotonic value of its own. The server ignores unknown query parameters
on the poster route (`copyUrl()` already relies on this), and the next real page
render restores the canonical mtime-based URL, so the two never disagree about
which image is current — only about the spelling of the URL.

The same new URL is written to everything on the card that embeds it: the
`<img src>`, the Download link's `href`, and the `data-url` on Copy URL and Full
screen. The card's reveal class is cleared and `markLoaded` re-applied so the
new image fades in the way a freshly rendered one does rather than appearing
before it has decoded.

### Read success from the flash, not the HTTP status

Every mutation endpoint ends in `back()` — a 302 to the gallery — for both
outcomes, adding a `success` or `error` flash on the way. The fetch follows that
redirect, so the response the script sees is a 200 gallery page either way, and
the status can only distinguish a genuine 4xx/5xx (an unknown category, a
crash). The flash's level is therefore the only signal that the poster actually
changed, and the card is updated only on `alert--success`.

That also settles what a failure should do: it stored nothing, so there is
nothing to re-render. A failed card-local mutation reports its error and leaves
the grid — and the user's position in it — completely alone, which is what the
spec's "operations that store no new image leave the gallery alone" requires.
The grid is re-rendered in exactly one case: the change succeeded but its card
is not on screen to update.

The flash message itself is already scraped from the POST response by
`submitForm()` and shown as a toast; that is unchanged, as is the `is-loading`
busy counter. Removing the nested `load()` simply means the busy count settles
one step earlier.

## Risks / Trade-offs

- **A card-local update can drift from the server's view of the grid.** →
  Bounded by the two facts above: neither sort order nor grid membership depends
  on anything a change writes. If that ever stops being true (a "recently
  changed" sort, say), the card-local path would need to be re-scoped along with
  it — worth a note in the spec's requirement rather than a defensive refetch
  now.
- **The client-side cache-buster is not the server's `?v=`.** → It is only a
  cache key; both spellings resolve to the same file, and the next full render
  reverts to the mtime form. Using a value derived from the current time keeps
  it monotonic so a second change to the same poster in the same session still
  busts.
- **`fetch-from-plex` on a poster Plex has not actually changed** re-downloads
  an identical image into the card. → Harmless and rare; the alternative is
  comparing bytes, which is not worth a round trip.
- **Delete and import still lose the user's place.** → Explicit non-goal, called
  out in the proposal so it is not mistaken for an oversight. This change makes
  the common case correct and leaves the rarer ones no worse.
- **No automated coverage for the browser behaviour.** → The repo has no JS test
  harness, so this is verified by hand on the `:dev` image (change a poster deep
  in a phone-width grid; confirm nothing scrolls and the image updates). PHPUnit
  covers the markup contract the script depends on, which is the part that could
  silently rot.
