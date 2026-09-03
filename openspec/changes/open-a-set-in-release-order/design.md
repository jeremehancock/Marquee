## Context

Five facts about the code decide the shape of this change.

**A set is not a search, structurally.** `PosterLibrary::paginate()` branches:
`$setKey` narrows by membership, `$query` narrows by matching, and the caller
sends exactly one of them. They are alternatives, not layers. Everything the
`search` capability says about a query therefore has to be *checked* against a
set rather than assumed to cover it — which is the question item 1 asks and which
this design answers explicitly in both specs.

**The sort control is the escape hatch out of a set today, by accident.** The
`a[data-sort]` branch of the click handler rebuilds the URL as
`pathname + '?sort=' + … + ('&q=' + …)`. It never carries `?set=`, so activating
any sort button silently drops the set. Likewise `categoryUrl(pathname, setKey)`
only carries a set when one is passed in, and the tab-tap path
(`switchCategory(tabLink.pathname)`) passes nothing. Meanwhile the query lives in
the search input, which every path reads. That asymmetry is the whole of the
user-visible inconsistency: a set is request state, a query is page state.

**Release order needs no new import.** `year` is on every row and
`season_number` on every season. A season stores its *show's* year — Plex reports
none on a season node — which sounds like a defect and is exactly what makes a
show's set order correctly: show and seasons tie on year, and season number
breaks the tie with the show (no season number) leading.

**A set can be nameless.** `titleForRatingKey()` reads `plex_items` by rating key,
and a collection whose own poster was never imported has no row there at all.
The set view already degrades to "in this set". Anything that wants to *name a
second set* in a sentence needs a name more reliably than that.

**A set view does not know where it came from.** `?set=<key>` is all the address
carries. That is why it cannot say which of a poster's collections the user meant,
and why it cannot derive a title to offer a broader search from. One optional
parameter fixes both.

## Goals / Non-Goals

**Goals:**

- A set reads in the order it was released, from the facts already recorded, with
  no import and no new column.
- Related posters behaves the same way whether it finds a set or falls back to a
  search — the same things survive a tab change and a sort change in both cases.
- A poster in more than one set says so, by name, where the user can act on it.
- A set that is narrower than the library can show says so, on the same terms the
  typed search already uses: offered with a count, never applied.
- One read per category per render, with the improvement measured rather than
  claimed.

**Non-Goals:**

- Inferring a set from titles, or from anything but what Plex reported. That
  model died on real data once already.
- Grouping, ranking, or promoting a "primary" member. A set is a flat list under
  an order.
- A chooser between a poster's sets before the view opens. A film in one
  collection is the overwhelmingly common case and must not pay an extra step for
  the rare one.
- Recording a second copy of any fact. Set *names* are recorded because no copy
  exists today, not because the existing one is inconvenient.
- Making the gallery fast in general. This change removes the reads it added and
  the three that were already there, measures the result, and stops.

## Decisions

### 1. Release is a real sort field, and a set selects it by default

**A set does not override the sort — it defaults it.** Opening a set selects
Release the way it already selects the All view: a starting point, not a
restriction. The sort control stays visible and live, shows *Release* as the
active field, and activating another field re-orders the set without leaving it.

Three options were weighed.

| | What it does | Why not |
| --- | --- | --- |
| Override | The set ignores the active sort and is always release-ordered | The sort control then reads "A–Z" over a grid that is not in A–Z. It is a lie on screen, and it leaves no honest thing for the control to do |
| Hide the control in a set | No lie, no conflict | Removes a pinned control mid-session and takes the phone sort tray with it. A control that vanishes is worse than one that changes |
| **Third field, defaulted by the set** | Release joins Alphabetical and Date added; a set opens on it | Chosen |

The third option is more code and less special-casing, which is the right trade
here. It also makes the answer to "does the set override the sort" a plain no,
which is a much easier sentence to write into a spec than a conditional override.

**Offered everywhere, not only inside a set.** A control that renders two buttons
in the library and three in a set reshuffles itself — precisely what
`SortState::buttons()` exists to prevent. Sorting a library by release year is
also a reasonable thing to want on its own. One field, offered uniformly, is both
less conditional logic and more capability.

`SortField::other()` returns one field and `SortState` holds one `alternate`;
both are two-field shapes. They become a per-field map of remembered directions.
This is mechanical but it is the largest single edit in the change.

#### 1a. The order itself, and why nulls sort first

Ascending, in order:

1. **Recorded release year**, with *no recorded year first*.
2. **Season number**, with *no season number first*.
3. Category order (Movies, TV Shows, TV Seasons, Collections).
4. The existing article-aware sort key.

Direction reverses (1) only. Tie-breaks always run forwards — the rule
`SortComparator` already states — so under Release descending a show's seasons
still read 1, 2, 3 rather than scrambling.

**Unknown-year-first is chosen because it is correct whichever way Plex answers.**
A collection's `year` is whatever `<Directory type="collection" year=…>` carried,
and that cannot be verified from here. If Plex reports none, the collection's own
poster leads its films, which is how a set should read. If Plex reports the
earliest member's year, the collection sorts among the earliest films, which also
reads correctly. Sorting unknowns *last* is only correct in the second case. The
rule that survives both is the one to write.

This deliberately introduces no set-specific ordering rule. "The poster that
names the set comes first" was considered and rejected: it is correct only under
Release, so it would have to switch off when the user picks A–Z inside a set, and
a rule that applies under one sort and not another is exactly the kind of hidden
conditional this change is removing.

**Verify against the real library before the comparator is finalised** (task 1.1):
how many rows have a null year, whether collections carry one, and whether any
season is missing its number. The rule above is designed to survive every answer,
but a surprise here — say, seasons with no recorded year at all — is worth seeing
before the tests are written around the mechanism.

#### 1b. A set's default is a default, not a recorded choice

Precedence when resolving the sort: an explicit `?sort=` wins; otherwise, if a set
is active, Release; otherwise the session's stored choice; otherwise
`DEFAULT_SORT`. The set's rung sits *above* the stored preference — otherwise a
user who once picked Date added never sees a set in release order — and *below*
an explicit request, so the control still works.

**Nothing a set view resolves is written to the session, including an explicit
choice made while a set is open.** `SortPreference::resolve()` records on every
valid `?sort=`, which would mean sorting a set by Date added quietly re-sorts the
whole library once the set is cleared. The user asked a question about a set; the
answer should not outlive it. The URL already carries the choice, so paging and
tab-switching inside the set keep it without the session's help.

### 2. The address carries the poster a set was opened from

`/library/all?set=<key>&from=<category>/<filename>`

`from` is **optional and inert**. A set URL without it — bookmarked, shared,
typed — renders exactly as it does today. With it, the view knows one more thing
and can offer two more:

- the *other* sets that poster belongs to, by name, as links (item 2);
- a broader search derived from that poster's related title (item 3).

The alternative the brief names — the link carries the poster *instead of* a set
key, and the destination offers a choice — was rejected. It puts a chooser in
front of the common case (one set) to serve the rare one, and it makes the
destination's identity depend on a poster that may later be deleted. Carrying the
set *and* the origin keeps the destination stable and the extra knowledge purely
additive.

A stale `from` — the poster deleted, the filename renamed by a later import —
resolves to nothing and the view falls back to what it renders today. It is never
allowed to affect *which* posters the set holds; membership is decided by `set`
alone.

#### 2a. Naming the other sets, and where the name comes from

"Godzilla vs. Kong is also in MonsterVerse", with MonsterVerse a link to that set
carrying the same `from`, so the user can hop between a film's collections.

The note is about the **origin poster's** other sets — not the union of every
member's. The union is unbounded (a member of the MCU collection is plausibly also
in Avengers, Iron Man, and Phase One) and it answers a question nobody asked. The
origin poster's list is short, precise, and answers the actual confusion: *I
clicked this film and got King Kong; where did MonsterVerse go?*

Naming it needs a name, and `plex_items` has one only if the collection's own
poster was imported. So: **a small `plex_sets` table, rating key → title**,
written at the two points that already know both — `collectionMembership()` has
`$collection->title` in hand beside the `fillMissingSetKey()` call it already
makes, and the show branch has `$show->title`. One upsert each, no new request, no
new walk.

This also repairs the existing case: a set view whose naming poster was never
imported reads "in this set" today and can now read "in MonsterVerse".

Rejected: a `set_titles` column parallel to `set_keys`. Titles contain commas, so
the comma-joined encoding would have to become JSON; a parallel array has to be
kept in step with its partner on every write; and it would store a collection's
name once per member film. A two-column table is smaller and normal.

A set that still has no name — one whose walk has not run since the upgrade — is
described rather than named ("also in another collection"), not omitted. The link
is the useful part; the name is the courtesy.

### 3. A set may be offered a broader search, on one condition

The search spec currently says an exact set is offered nothing "because it is
exact and there is nothing to widen". That reasoning conflates two things:
membership is exact, and the *collection* is complete. Plex's answer is exactly
what the user typed into Plex, including the film they forgot.

The offer, computed only when `from` is present:

1. Take the origin poster's **related title** — the same value Related posters
   would have searched had there been no set.
2. Consider that title and the shorter forms `BroaderQuery` already produces.
3. Run each over the unfiltered listing and keep the one finding the most.
4. Offer it **only if it finds more posters than the set holds**, with the count.

That single condition does all the suppression, and it suppresses in exactly the
right places:

| Opened from | Set holds | Candidate finds | Offered |
| --- | --- | --- | --- |
| Jackass: Best and Last | 8 | "Jackass" → 9 | **yes** |
| Breaking Bad - Season 5 | 6 (show + 5) | "Breaking Bad" → 6 | no |
| Iron Man (MCU) | 30 | "Iron Man" → 3 | no |
| A film alone in its collection | 1 | its title → 1 | no |
| Jackass Forever | 8 | nothing to cut | no |

The last row is a real limit and not an oversight. `BroaderQuery` cuts at a
subtitle separator or a trailing instalment number and does nothing else, so a
title with neither reaches no candidate at all — the same collection opened from
*Jackass: Best and Last* offers its series, and opened from *Jackass Forever*
offers nothing. Narrow rather than wrong: the set shown is correct either way.
Widening `BroaderQuery` to drop any last word would change the typed search's
offer too, which is a decision about a different feature.

The MCU row is the important one: a set whose members share no words is never
told it is missing something, because no title query can find more of it. Nothing
had to be special-cased for that; the count comparison already knows.

The wording differs from the typed search's ("Looking for the rest of a series?"),
because the question differs — that one asks *did you mean something broader*,
this one asks *is something missing from this collection*. Both stay offers with
counts attached, and neither is ever applied on its own.

Cost: one filter pass per candidate over the unfiltered listing, which is what the
search path already pays, and only when `from` is present.

### 4. A set is page state, like a query

`categoryUrl()` gains the active set from `window.location.search` rather than
from a caller passing it, which is what makes every path carry it without each
path being taught to. `applyView()` pushes the URL, so location is authoritative
after a no-reload update.

The rules, stated once:

| Gesture | The set |
| --- | --- |
| Tab tap, swipe commit | carried |
| Sort button | carried |
| Pagination | carried (already, being URL-derived) |
| Typing in the search box | **dropped** — a typed query is a new intent |
| Clear | dropped (the clear link is a bare pathname already) |

Switching to Movies with a collection's set active shows that collection's films
and nothing else; switching to TV Shows shows an empty filtered view. Both are
what an active query does in the same place, and sameness is the point.

**The neighbour cache must learn about the set.** `cachedView()` compares the
held copy's `query` and `mutation` counter. A set is a third thing that stales a
copy, and its absence is exactly the failure the cache's own comment warns about:
open a set, swipe, and a held *unfiltered* grid is trusted — "a wrong library that
looks like a working one". `primeNeighbours()` stamps the set alongside the
query, and `cachedView()` compares it.

### 5. One read per category, and a measurement to go with it

`titlesForCategory`, `yearsForCategory`, `filenamesForCategory`,
`relatedTitlesForCategory`, `setKeysForCategory` and (under a date sort)
`addedAtForCategory` each scan one category. `PosterLibrary::paginate()` then
re-reads titles when a query is active and set keys when a set is active. A
filtered All view is 24 scans of the same six columns.

They become **one** `SELECT filename, title, year, season_number, parent_title,
set_keys, added_at FROM plex_items WHERE category = :category` per category,
returned as a typed per-poster value object. Four scans in the All view, one in a
category view, whatever the sort and whatever the filter.

**The omission semantics are the whole risk.** Each existing method omits rows
deliberately, and callers depend on it: an empty title is omitted so the caption
falls back to the filename-derived title; a null year is omitted so no year is
shown; `set_keys = ''` is omitted so Related posters falls back to a title search;
`added_at = 0` is omitted so the date sort falls back to the file's mtime;
`filenamesForCategory` is only consulted when Plex is configured, so every poster
reads as unlinked when it is not. In a combined read every row comes back and
**absence has to be encoded in the value object rather than in the map's keys.**
That is where a regression would hide, so each of those five fallbacks gets a test
asserting both the present and the absent case.

The facts are read once in the controller and *passed into* `PosterLibrary`,
replacing its `array $addedAt` parameter and its two private lazy reads. That
makes "read once per render" structural rather than incidental — there is no
longer a repository call inside `paginate()` to drift.

**Measure, do not assume.** A read-only `bin/bench-gallery.php`, in the shape of
the existing `bin/diagnose-sets.php`, points at the configured data and posters
directories and reports, over several iterations: total query count and wall time
for the per-category reads, and wall time for a full `browseAll()` render path,
for the unfiltered All view, a filtered All view, and a set view. Run before the
change and after, against the ~3900-poster library the previous change was
validated on, and put both numbers in the PR. If the combined read does not win,
that is a finding to report, not a result to bury — the change is still worth
making for the drift-proofing, but the claim must match the measurement.

## Risks / Trade-offs

**A third sort button crowds the desktop toolbar.** The group is search + label +
three buttons, each an icon, a label and an arrow. → The phone hides `.sort`
entirely and uses a tray, where a third row is free. The desktop needs a check at
the narrow end of the pointer breakpoint; the label text is the part that can
shrink first. Flagged as a visual-design task rather than left to discover.

**Release order is only as good as the recorded years.** A library imported before
years were recorded, or one Plex has no year for, sorts into the unknown block. →
Unknown-first is stable and stated, not arbitrary, and a year arrives on the next
ordinary import through the existing reconcile-on-skip path. Narrow, never wrong.

**A set surviving a tab switch can produce an empty view.** A movie collection's
set with TV Shows selected shows nothing. → Identical to an active query that
matches nothing in a view, which the gallery already indicates as a filtered empty
state rather than an empty library. Sameness with the search path is the goal, and
this is a place where it costs something.

**Not recording a sort chosen inside a set is a second rule about sort
persistence.** Someone reading `SortPreference` will find one path that records
and one that does not. → It is one condition with a stated reason, and the
alternative is worse: a set silently re-sorting the library the user returns to.

**`from` is a URL parameter that can go stale.** A shared set link naming a poster
someone else deleted. → It is inert by construction: it decides nothing about
membership and, unresolvable, renders as the set view already renders. It is never
used to look up posters by anything but category and filename, both of which are
already validated on every other poster route.

**The "also in" note could be long.** A film in five collections. → The list is
the origin poster's own sets, which real libraries keep small; the unbounded
option (the union over all members) was rejected for exactly this reason. If a
real library shows otherwise (task 1.1 reports the distribution), a cap is a
one-line addition.

**A set can now be told it is missing something when it is not.** A collection
that deliberately excludes a film the title search finds. → The offer states a
count and is never applied; the set shown is unchanged. This is the same bargain
the typed search's offer already makes, and the same reason it is safe: the reader
decides.

**The combined read might not be faster.** Six columns over every row instead of
one or two, five times. → That is why it is measured on both sides rather than
asserted. The read count falls from 24 to 4 regardless, and the structural win —
no repository call inside `paginate()` to drift out of step — stands either way.

**Three designs died on real data last time.** Title-based sets, one set per
poster, membership that could only be added. Each passed tests built around the
mechanism. → Two defences here. Task 1.1 reports the real library's shape (null
years, collections per film, seasons without numbers) *before* the ordering rule
and the "also in" note are finalised. And every new rule is tested in both
directions: a set that gains and loses a member, a poster whose second set is
shown and one where nothing must be shown, an offer made and an offer suppressed,
a fact present and the same fact absent.

## Migration Plan

1. `plex_sets` is created by `Database::migrate()` on first boot after the
   upgrade, through the existing idempotent path. Empty until an import runs.
2. Set names fill in on the next scheduled or manual import, via the collection
   walk and show loop that already run. Nothing is re-downloaded and nothing is
   asked of the user. Until then a set is named exactly as well as it is today —
   from `plex_items` when its own poster was imported, and "this set" otherwise.
3. Release order works immediately: it reads columns that are already populated.
4. Rollback is the previous Docker tag. The new table is inert to older code,
   which never queries it, and the `?set=`/`from=` parameters degrade to a plain
   set view and then to an unfiltered view.

## Open Questions

None blocking. Two judgements are settled at the point they are made and
reviewed: the exact glyph for the Release sort field, and the wording of the set's
broader-search offer. Task 1.1's report on the real library may tighten the "also
in" list with a cap; the design works without one.
