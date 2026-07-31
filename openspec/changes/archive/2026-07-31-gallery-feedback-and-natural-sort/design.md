## Context

Two unrelated defects, bundled because both are small, both are gallery-facing,
and both are entirely contained.

**Find Posters apply.** The Change poster dialog has three ways out. Upload and
From URL are `<form class="js-mutate">` and go through `submitForm()` in
`public/assets/gallery.js`, which calls `beginBusy()`. Find Posters does not:
tapping a candidate opens a full-screen `.viewer--finder` preview whose confirm
button calls `applyFinderSelection()`, an Alpine method that fires a bare
`fetch()`. It has no access to `beginBusy()` — that lives in the vanilla
DOMContentLoaded scope — and no busy state of its own.

The wait is real. `POST /library/{category}/change/url` runs
`ChangePosterService::replaceAndPush()`, which downloads the full-resolution
image from TMDB / fanart.tv / TheTVDB over Guzzle (20s timeout, 10s connect),
validates it, replaces the file, then uploads the bytes to Plex and locks the
poster. Two third-party round trips on a multi-megabyte image.

Worth noting for scoping the risk: `PosterStorage::replace()` overwrites in
place and does not go through `uniqueFilename()`, so a double submit cannot
produce a duplicate poster file. The cost of a second tap is a wasted download,
a second Plex upload, and two toasts — wasteful and confusing, not corrupting.

**A–Z ordering.** A season poster reaches `sortKey()` like this:

```
Plex "Breaking Bad" + "Season 10"
  → PlexItem::displayTitle()          "Breaking Bad - Season 10"
  → ImportService::deriveFilename()   + " [TV Shows]"
  → sanitizeFilename()                [^A-Za-z0-9._-] → _
                                      Breaking_Bad_-_Season_10__TV_Shows_.jpg
  → Poster::title()                   [._]+ → space
  → Poster::sortKey()                 "breaking bad - season 10 tv shows"
```

`PosterLibrary::paginate()` then compares `[sortKey, category]` pairs with
`<=>`. Against Season 1 the strings diverge at a space (0x20) versus "0"
(0x30), so Season 1 leads; against Season 2 they diverge at "1" versus "2", so
Season 10 precedes Season 2. Hence 1, 10, 11 … 19, 2, 20 …

`PosterSearch::filter()` has the same bug in a different key. It ranks by match
position and breaks ties on its own `normalize()`d title — a richer
normalization that folds accents and flattens punctuation — compared with the
same plain `<=>`.

## Goals / Non-Goals

**Goals:**

- Applying a found poster is visibly in progress for as long as it runs, and
  cannot be started twice.
- Titles order by numeric value where they contain numbers, in the gallery and
  in search-result tie-breaks alike.
- Reuse what already exists. The overlay/spinner treatment, its reduced-motion
  opt-out, and its stacking are all in place; this should add markup and a flag,
  not CSS.

**Non-Goals:**

- Progress feedback for the Upload and From URL tabs. They already reach
  `beginBusy()`. Their indication is arguably too subtle — the dim lands on
  `#results`, behind the open modal — but that is a separate observation and a
  separate change.
- Progress feedback for the card actions (Send to Plex, Fetch from Plex,
  Delete). No modal covers them, so the existing dim is visible.
- Changing what any poster displays. This change touches sort keys only.
- Punctuation handling in sort order. `M*A*S*H` sorting ahead of `Mad Men` is a
  real oddity with a real cause (punctuation and spaces both sort below
  letters), and it is untouched here.
- Sourcing the sort key from the Plex-recorded title rather than the filename —
  see the decision below.

## Decisions

### Show the overlay immediately, with no grace period

The gallery's `beginBusy()` defers its indication by `LOADING_GRACE_MS` (200ms)
and holds it for `LOADING_MIN_MS` (300ms), and the *Deferred loading indication
for in-place view changes* requirement in `poster-library` codifies that. The
reason is specific: a tab switch or a search may resolve from cache in
milliseconds, and a dim that appeared and vanished would read as a flicker.

Applying a found poster has no fast path — it is always a remote download plus
a Plex upload. Deferring would only mean 200ms of the exact silence being fixed.
Both existing mutation overlays behave this way already: the Plex import
overlay and the orphan scan/delete overlays show the moment their flag flips.

*Alternative considered:* reuse `beginBusy()` by exposing it to the Alpine
component. Rejected — it would either inherit the grace period or need a bypass
parameter, and the two indications are different things (a dim on the results
grid versus a blocking modal spinner) driven from different scopes.

The spec makes the distinction explicit so the two rules do not later read as
contradictory: the deferral governs *view changes*, not *mutations*.

### Full-screen overlay, not one scoped to the modal panel

The Find Posters confirm happens inside `.viewer--finder`, a fixed full-screen
element that sits *above* the change-poster modal. An overlay scoped to the
modal panel would be underneath the thing the user is looking at.

The existing stacking already resolves this with no CSS change:

```
z-index 100   .overlay          ← the progress overlay
z-index  80   .toast
z-index  60   .viewer            ← the Find Posters preview
z-index  50   .sheet
```

`.overlay`, `.overlay__box`, and `.spinner` are defined once in `app.css` and
already covered by the `prefers-reduced-motion` block that disables the spin.

### Guard re-entrancy in the function, not only in the UI

A full-screen overlay physically intercepts the second tap, but only once it has
painted. A guard at the top of `applyFinderSelection()` that returns early when
a change is already in flight costs a line and does not depend on paint timing.
Both mechanisms stay: the disabled button and the overlay communicate, the
guard enforces.

### Pad digit runs in the sort key rather than swapping in a comparator

Two ways to sort naturally:

| | `strnatcasecmp` comparator | Padded key *(chosen)* |
| --- | --- | --- |
| Call sites | Rewrite the `[a,b] <=> [c,d]` comparison in `PosterLibrary` and the one in `PosterSearch`, each with a hand-written tie-break | Both comparisons untouched; only the key changes |
| Testable | Only through the collaborators | Directly on `Poster::sortKey()` |
| Shared with search | A second comparator to keep in sync | The same transform, one behaviour |
| Multibyte | Byte-based | Byte-based (unchanged from today) |

The padded key keeps `PosterLibrary::paginate()` and `PosterSearch::filter()`
exactly as they are, including the category tie-break that makes the All view
deterministic. It is also the shape the existing tests can assert against.

The transform pads each run of digits to a fixed width:

```
"breaking bad - season 1 tv shows"   →  "… season 000000000001 tv shows"
"breaking bad - season 10 tv shows"  →  "… season 000000000010 tv shows"
```

**Width 12.** Comfortably beyond any real title while staying short enough to
read in a failing test's diff. A run longer than the pad is left alone, so it
compares lexicographically exactly as it does today — the pre-existing
behaviour, not a new failure mode.

**Leading zeros.** "Season 01" and "Season 1" pad to the same key and become a
genuine tie. In the All view the category tie-break may separate them; within
one category nothing would, leaving `usort()` free to order them arbitrarily.
A final tie-break on the unpadded title makes it deterministic.

**Composition with `IGNORE_ARTICLES_IN_SORT`.** Article stripping is a
prefix operation and padding is a digit operation, so they commute; applying
padding after the existing article logic keeps `sortKey()` a single readable
expression.

The transform is used by both `Poster::sortKey()` and the `PosterSearch`
tie-break. Those operate on differently normalized strings — `sortKey()`
lowercases, `PosterSearch::normalize()` also folds accents and flattens
punctuation — so the shared piece is the padding step alone, applied to each
key after its own normalization.

### Leave the sort key sourced from the filename

Sorting reads `Poster::title()` (derived from the filename); captions read
`Poster::captionTitle()` (from the Plex record). Moving sorting onto the Plex
title looks like it would fix the visible oddities. It would not.

The motivating case is punctuation: `M*A*S*H` sorts ahead of `Mad Men`, where a
person alphabetising `MASH` against `Mad Men` puts Mad Men first. But the
filename flattens `*` to a space (0x20) and the Plex title keeps `*` (0x2A) —
both below `a` (0x61). The order is identical either way. The cause is
punctuation participating in the sort, which is a third change with its own
decision to make.

What switching *would* fix is narrow — the trailing ` [Library]` in every key,
and apostrophes flattening to spaces — and it only shows up on near-ties.
Against that it carries a genuine cost: `captionTitle()` falls back to
`title()` when a poster has no Plex record, so the library would sort under two
key schemes simultaneously, which misorders visibly.

### Check the response status while the function is open

`applyFinderSelection()` currently does `.then(r => r.text())` with no `r.ok`
check, parses whatever came back as HTML, and scrapes it for `.alert`. A 500
happens to half-work because the error page carries one. Since the `finally`
block is being added anyway, checking the status so a failure reports as a
failure is a few lines in the same function. Called out separately in the
proposal so it can be struck without disturbing the rest.

## Risks / Trade-offs

- **Every poster's sort key changes, so the whole gallery reorders.** → That is
  the point, and it is confined to ordering: no filename, caption, database row,
  or file on disk is touched. The blast radius is one page render.
- **A digit run longer than 12 is not padded and mis-sorts.** → It mis-sorts
  today too; this is unchanged behaviour, not a regression. Spec'd explicitly
  so it is a known limit rather than a surprise, and covered by a test.
- **"Season 01" and "Season 1" become a tie.** → Resolved by a final tie-break
  on the unpadded title, so ordering stays deterministic across runs.
- **The overlay could strand the user if a code path misses the reset.** →
  Clearing the flag in a `finally` covers the success path, the failure path,
  and a rejected fetch alike. Both a success and a failure are tested.
- **Adding a status check turns previously "successful" 500s into visible
  errors.** → That is the correct behaviour; the change was already failing, it
  was just reported as a success.
- **`.overlay` is `position: fixed` inside a component that also renders inside
  trays**, where `app.css` scopes `.sheet .overlay` to the panel. → The Find
  Posters preview is not inside a tray, so the unscoped rule applies. To be
  confirmed on a phone during validation rather than assumed.
