## Context

Sort state today is a single string that travels through three carriers: the
`?sort=` query parameter, the `sort_order` session key, and the `DEFAULT_SORT`
environment variable. `SortOrder` has two cases and `SortOrder::fromSlug()`
parses all three carriers. Direction is not modelled at all — it is baked into
the comparators in `PosterLibrary::paginate()`, where Alphabetical is always
ascending and Date added always descending.

Three properties of the existing code shape this design:

- **The toolbar is never re-rendered by the no-reload path.** It lives outside
  `#results`, and search, paging, and tab switches replace only `#results`. Any
  design where an action silently changes the sort state would leave the control
  lying about the listing.
- **Sort clicks already force a full navigation.** `gallery.js` intercepts
  `a[data-sort]` and calls `window.location.assign()` with a URL rebuilt from the
  live pathname plus the link's `data-sort` value, precisely so the toolbar's
  active state re-renders.
- **Search ignores sort entirely.** `PosterLibrary::paginate()` branches to
  `PosterSearch::filter()` when a query is present, and that method ranks by
  match position with a hardcoded ascending `NaturalOrder` tie-break. The branch
  means a query and a sort order are alternatives rather than composable.

## Goals / Non-Goals

**Goals:**

- Four effective orders, reached by toggling either of two buttons.
- Direction remembered per field for the session.
- The sort control states the current order unambiguously, in text and by glyph.
- The sort control does something visible while a search is active.
- Zero migration: existing `DEFAULT_SORT` values, bookmarks, and live sessions
  keep working.

**Non-Goals:**

- A separate "Relevance" sort mode. It would only be selectable while searching,
  and the toolbar cannot re-render to show it being auto-selected.
- Sorting on any field other than title and date added.
- Persisting sort choice beyond the session.
- Changing which posters a query matches. Only the ordering of the matches
  changes; the matching rule itself is untouched.

## Decisions

### Direction lives inside the sort slug, not beside it

`SortOrder` grows from two cases to four:

| Case | Slug | Field | Direction |
| --- | --- | --- | --- |
| `Alphabetical` | `alphabetical` | title | ascending |
| `AlphabeticalDesc` | `alphabetical_desc` | title | descending |
| `DateAdded` | `date_added` | date added | descending |
| `DateAddedAsc` | `date_added_asc` | date added | ascending |

The enum gains `field()`, `direction()`, and `flipped()`.

**Why the existing two slugs keep their spelling and become the default
directions:** `alphabetical` already means A–Z and `date_added` already means
newest first. Naming the new cases rather than renaming the old ones means
`DEFAULT_SORT`, old bookmarks, and sessions already holding `alphabetical` or
`date_added` all continue to resolve correctly, with no migration code and no
documentation change to `README.md`.

**Alternative rejected — a separate `?dir=` parameter and session key.** It is
conceptually cleaner (field and direction are genuinely two axes) but it makes
every URL builder in Twig and `gallery.js` carry a second parameter, gives the
session two keys that can desync, and forces `DEFAULT_SORT` to either grow a
companion variable or learn compound parsing. Conflating the two axes in the wire
format while separating them behind `field()` / `direction()` gets the clean
model where it matters without the plumbing.

### `SortPreference` resolves to a `SortState`, not a bare `SortOrder`

Remembering direction per field means the session must hold more than the current
order: rendering the *inactive* button needs the other field's remembered
direction, which the current slug does not contain.

```
session
  sort_order                   → the active order's slug (existing key, existing meaning)
  sort_direction_alphabetical  → 'asc' | 'desc'
  sort_direction_date_added    → 'asc' | 'desc'

SortPreference::resolve(session, query, default) → SortState
    current    : SortOrder   the comparator, and what gets carried in URLs
    toggled    : SortOrder   current->flipped()   → the active button's href
    alternate  : SortOrder   the other field at its remembered direction
                             → the inactive button's href
```

A valid `?sort=` still wins and is stored, and it now also updates that field's
remembered direction. Absent direction keys resolve to the field's default
direction, so a session carrying only a legacy `sort_order` string upgrades
silently.

Templates then compute nothing: they read three orders and two labels off the
state. This keeps the toggle arithmetic out of Twig, which matters because the
control is rendered twice (toolbar and phone tray).

### The active button's label describes the present; its href describes the future

```
 active field = alphabetical descending,  date added remembered as descending

 ┌────────────────────────────────┬────────────────────────────────┐
 │  [bars]  Z–A   ⌃    ACTIVE     │  [cal]  Date added  ⌄          │
 │  label = current order         │  label = remembered direction  │
 │  href  = ?sort=alphabetical    │  href = ?sort=date_added       │
 │          (flipped)             │         (matches its own label)│
 └────────────────────────────────┴────────────────────────────────┘
         label ≠ href                        label = href
```

This asymmetry is inherent to "the label shows current state" plus "clicking
toggles", and is the same contract a sortable table header has. It is called out
explicitly because it is the part most likely to be implemented backwards.

### `gallery.js` needs no changes

The server renders the *target* slug into `data-sort` — already flipped for the
active button, already at the remembered direction for the inactive one. The
existing handler reads that attribute and navigates. The whole toggle is
server-rendered; no client-side state, no new listener.

### One comparator factory shared by listing and search

`PosterLibrary::paginate()` and `PosterSearch::filter()` currently hand-roll
`usort` callbacks that duplicate the ordering rules. A single factory keyed by
`SortOrder` returns the comparator both use, so a direction applies identically
whether or not a search is active.

The comparator keeps the existing deterministic tie-breaks (category order, then
the digit-aware title key for the alphabetical field; category order for date
added). **Direction reverses the primary field only** — the tie-breaks stay
ascending, so reversing direction does not scramble the ordering of posters that
compare equal on the field the user actually chose.

`PosterSearch::filter()` gains the active `SortOrder` and the `addedAt` map as
parameters. `PosterLibrary::paginate()` already has both in scope.

### Search: a filter and nothing more

`PosterSearch` scored by the position of the earliest matching term and broke
ties on an ascending `NaturalOrder` key. It now only decides *whether* a poster
matches; the gallery sorts what survives.

```
  before   filter → rank by score → tie-break by title
  after    filter                                        ← PosterSearch
           sort by the active order                      ← PosterLibrary
```

**This was tried the other way first and was wrong.** Keeping score as the lead
and letting the sort order break ties looks conservative — it extends the
existing "Results ranked by match position" requirement instead of contradicting
it — but it fails the case the sort control exists for:

```
  search "alien", newest first, where The Alien Movie is by far the newest

  score-leads    Alien Covenant · Aliens · Alien · The Alien Movie
                                                   └── newest, listed last
  sort-leads     The Alien Movie · Alien Covenant · Aliens · Alien
```

Because the three titles *beginning* with the query form one relevance group, the
sort only rearranges within it, and the one mid-string match is stranded below
them however the user asked for the list to be ordered. Worse, it looks correct
whenever the matches happen to score equally — so the control appears
intermittently broken rather than deliberately overridden.

The deciding argument is that **there is no state in which no sort is selected.**
An implicit relevance ranking can therefore only ever contradict an explicit
choice, never fill a gap. So the `search` capability's ranking requirement is
REMOVED rather than modified.

The cost is real but small: a mid-string match is no longer pushed below titles
that begin with the query. Searching "star" under A–Z now lists *A Star Is Born*
first. That is predictable, which is what the control promises.

This also simplifies both classes rather than complicating them. `PosterSearch`
loses its scoring, its `usort`, and the `SortComparator` dependency the tie-break
would have required; `PosterLibrary::paginate()` loses its branch, because
filtering and sorting are now sequential steps rather than alternatives:

```php
if ($query is present) {
    $posters = $this->search->filter($posters, $query);
}
usort($posters, $this->comparator->forOrder($sort, $addedAt));
```

### Icons: field glyph, label, direction arrow

Each button is three atoms, with identical direction grammar on both so the
indicator reads the same way in both places:

```
  as it normally runs  [▂▄▆]  A–Z  ↓      [▦]  Date added  ↓   ← newest first
  reversed             [▂▄▆]  Z–A  ↑      [▦]  Date added  ↑   ← oldest first
                        │      │     │
                        │      │     └─ one arrow path, rotated 180° when reversed
                        │      └─ field name; A–Z/Z–A also encodes direction
                        └─ field glyph, carrying no arrow of its own
```

**The arrow reports reversal, not ascending versus descending.** Those two words
do not read alike across the two fields: A–Z is ascending and newest-first is
descending, yet each is plainly the ordinary way to read its own field. An arrow
keyed to the direction would therefore point two different ways at two orders
that are equally unremarkable, and the resting state of the control would look
like a disagreement between its buttons.

Keyed to reversal instead, both buttons rest pointing down, and an arrow that has
turned over always means the one thing: this field is running backwards. The
difference is only visible on the title field — `isReversed()` and "is
descending" agree on dates and disagree on titles — which is exactly where a
direction-keyed arrow would have contradicted the `A–Z` label sitting beside it.

The date button is what the indicator actually has to serve. Its label never
changes, so the arrow is the only thing left to carry direction; on the title
button the arrow merely restates what `A–Z` and `Z–A` already say.

**The direction indicator is one half of the `sort` glyph**, not a new mark. That
glyph opens the phone tray and so has to name sorting as a whole rather than any
one direction, which it does by drawing a down arrow beside an up arrow — and its
two halves are the same shape at different x:

```
  sort (existing)   M7 4v15  M7 19l-3-3  M7 19l3-3     ← down half
                    M17 20V5 M17 5l-3 3  M17 5l3 3     ← up half

  sort-direction    M12 4v15 M12 19l-3-3 M12 19l3-3    ← the down half, centred
                    └─ rotated 180° by CSS, this *is* the up half
```

So the tray's trigger and the direction on the rows inside it are literally the
same mark, whole and halved — the same derive-don't-redraw rule `_icons.html.twig`
already applies to `send` and `import`. Rotating one path rather than drawing two
also means the two directions cannot drift apart.

A bare chevron was the first attempt and was the wrong mark twice over: it shares
nothing with the trigger, and it is mostly empty space, so at the 14px a button
allows it came out as a hairline. Keeping the shaft keeps the weight.

Two further glyphs join `_icons.html.twig` — bars of increasing length for title
order, a calendar for date added — drawn in the house style (24 viewBox, no fill,
`currentColor` stroke at 1.7, round caps and joins). The field glyph deliberately
contains no arrow, so there is exactly one direction indicator per button.

That 1.7 stroke is tuned for the ~22px the tabs draw at, where it lands at 1.56px
on screen. The sort control draws smaller to fit inside a button, so its marks
take a heavier nominal stroke by CSS to hold the same weight — and a further step
on the active button, whose label is set in 600.

An arrow is not announced by assistive technology, so each button carries an
`aria-label` naming field and direction in words, and the existing `data-tooltip`
attribute takes the same string.

**That wording cannot be one sentence on the active button.** It shows one order
and applies another, so a single "Sort by title, A to Z" describes the state in
the grammar of an instruction — hover it and you are told to sort the way the
gallery is already sorted, when activating it reverses:

```
  active     "Sorted by title, A to Z — activate for Z to A"
              └─ what it is ─────────┘   └─ what it does ─┘

  inactive   "Sort by date added, newest first"
              └─ both, the two being the same order ─┘
```

So `SortOrder` exposes the pieces — `stateLabel()`, `actionLabel()`, and the bare
`directionPhrase()` — and `SortButton` assembles whichever sentence its state
calls for. Dropping the state half would leave the current order announced by
nothing at all, since the arrow is silent and the date button's visible label
never changes.

The verb differs between the two attributes, which is the one place they part:

| | verb | why |
| --- | --- | --- |
| `data-tooltip` | *click for Z to A* | a tooltip appears only on hover, so its reader is holding a pointer |
| `aria-label` | *activate for Z to A* | a name is read wherever the control is, pointer or not — ARIA's own verb, for that reason |

`SortButton::tooltip()` and `::description()` are therefore the same private
sentence built with a different verb, rather than two strings to keep in step. On
the inactive button there is no instruction to phrase, so both return the same
text and cannot drift at all.

### The sort control becomes a Twig macro

The control is currently duplicated between the desktop toolbar and the phone
tray. With per-button toggle hrefs, active/inactive labelling, glyphs, and
direction arrows, duplicating it invites the two copies to diverge. One macro
taking the `SortState` renders both.

### The URL-carry rule stops hardcoding `date_added`

Two templates decide whether to append `&sort=` by testing `sort == 'date_added'`
— pagination links in `gallery_results.html.twig` and tab links in
`gallery.html.twig`. With four slugs that test silently drops `alphabetical_desc`
and `date_added_asc`, resetting the order on paging or a tab switch. Both become
"carry whatever the current order is".

## Risks / Trade-offs

- **Button density on desktop.** `.btn--small` now carries glyph + label +
  arrow in a toolbar that also holds the search box. → Check visually during
  implementation; the phone tray uses full-width `.btn` and has room. If the
  desktop row is tight, the field glyph is the atom to drop first — but only
  after seeing it rendered, not pre-emptively. *(Checked at 1280px: comfortable,
  nothing dropped.)*
- **The active button's label and its action differ.** A user may read "Z–A" as
  what clicking will produce rather than what is on screen. → The button is
  marked active and the `aria-label` states the current order in words; this
  matches the near-universal sortable-table convention.
- **Reversing direction is not a pure reversal of the listing.** Tie-breaks stay
  ascending, so equal-comparing posters keep their relative order rather than
  flipping. → Intended: it keeps seasons in numeric order under Z–A. Covered by a
  scenario so it is not later mistaken for a defect.
- **Titles beginning with the query are no longer surfaced first.** Searching
  "star" under A–Z lists *A Star Is Born* above *Star Wars*. → Accepted, and the
  reason the ranking was dropped: the sort control is an explicit instruction and
  an implicit ranking could only override it. A–Z already groups titles beginning
  with the query together, so most of the old grouping survives anyway.
- **Session shape changes.** Two new session keys join `sort_order`. → Absent
  keys resolve to the field's default direction, so existing sessions degrade to
  exactly today's behavior rather than erroring.

## Open Questions

None. Behavior, labelling, icon treatment, and search interaction are settled;
the one deferred judgement is the desktop button density check above, which needs
rendered output rather than a decision.
