## Context

Three facts about the code decide the whole shape of this change.

**A poster is named twice, and the two names disagree.** The card caption shows
the title Plex recorded (`plex_items.title`). Search matches `Poster::title()`,
which is reconstructed from the filename. `FilesystemPosterStorage::sanitizeFilename()`
replaces every character outside `[A-Za-z0-9._-]` with `_`, and import appends
the source library, so "Amélie" is stored as `Am_lie_2001_Movies.jpg` and its
searchable text is `am lie 2001 movies`. `PosterSearch::normalize()` folds
diacritics but *keeps the letter*, so the query "Amélie" becomes `amelie` — which
is not a substring of `am lie`. The poster cannot be found by its own name. Any
feature that derives a query from what the user sees walks straight into this.

**A season's show name is not stored anywhere.** `PlexItem::displayTitle()` glues
`parentTitle . ' - ' . $title` into `"Breaking Bad - Season 5"`, and only that
glued string is persisted. `PlexItem::parentTitle` exists in memory during import
and is simply dropped on the floor.

**There is no room for an eighth card action.** The requirement "Poster cards fit
their full action stack" pins the grid's minimum column width to whatever the
seven actions of a linked poster need. An eighth means wider columns and fewer
posters per row.

## Goals / Non-Goals

**Goals:**

- One action on a poster card that shows that poster alongside everything sharing
  its title, for TV *and* for film — a show with its seasons, a movie with its
  sequels and its collection poster.
- Search matches the name the user can see, in one place, for one reason.
- Existing installs gain the season behaviour without a forced re-import.
- The action stack keeps its current height, so the grid does not change.

**Non-Goals:**

- Any notion of relevance, grouping, or promoting the "parent" poster to the top
  of the results. "Search filters without reordering" is an existing requirement
  and this must not undermine it.
- Sort order. `Poster::sortKey()` continues to use the filename-derived title.
  Search decides *which* posters match; sort decides their order; the two are
  already separate and stay that way.
- Any new relation stored between items. See Decision 1.
- Non-Latin scripts. A title made entirely of characters the filename sanitiser
  discards is unsearchable today and remains so for posters with no Plex record.
  With a recorded title it now works, which is a strict improvement, but it is a
  consequence rather than an aim.

## Decisions

### 1. "Related" means *same title*, not *same Plex item tree*

The precise alternative is a real parent/child relation: store
`parent_rating_key` at import and list a show together with the seasons that
point at it. It is exact where this is approximate, and it was rejected because
it is **narrower**, not because it is harder.

Movies and collections have no sibling relation stored anywhere. Under the
relation design, the action has nothing to do for a movie — and a control that is
switched off carrying a reason it cannot speak is the exact trap `CLAUDE.md`
documents at length. The title-search reading is what makes the trilogy case work
at all, and the trilogy case is the headline request.

The cost is that a title search can over-match: two different shows called *The
Office* land in the same result set. This is mitigated by framing rather than by
machinery — see Decision 4.

### 2. Search matches the recorded Plex title, with the filename as fallback

`PosterSearch::filter()` gains a map of recorded titles keyed by category and
filename. A poster with a recorded title is matched on it; a poster without one
falls back to `Poster::title()` exactly as today.

**One title, not both.** Matching either would be the safer-looking option — no
search that works today would stop working. It is rejected because a search
succeeding for reasons invisible on screen is precisely the confusion being
removed. The behaviour that would be preserved is the accidental one: today,
searching "movies" matches every poster in a library named Movies, because the
library token lives in the filename. Nobody asked for that, and nothing on the
card explains it.

This mirrors the principle already stated in `poster-library` for the caption —
*one title serves every place this poster is named.* Search was the one surface
left out.

`PosterLibrary` already has `PlexItemRepository` injected and `paginate()` is the
single call site, so the map is built there and no controller signature moves.
The map must be keyed by category *and* filename, because `browseAll()` merges
all four categories and filenames are unique only within one — the same reason
the gallery template already keys its `plex_titles` map that way.

### 3. Store `parent_title`; do not parse it back out of the display title

Recovering "Breaking Bad" from "Breaking Bad - Season 5" means splitting on
`" - "`, and both directions are wrong for real titles: splitting on the first
separator breaks a show called *Cowboy Bebop - Remastered*; splitting on the last
breaks a season titled *Part 2 - Finale*.

`parentTitle` is already in hand at import. One additive column
(`TEXT NOT NULL DEFAULT ''`) through the existing idempotent `ensureColumn`
migration, following the pattern `season_number` and `tmdb_id` already set. Only
seasons carry a value; everything else records the empty string.

**Backfill is free.** `ImportService::reconcileFacts()` already runs on the
skip-unchanged path and writes only when a recorded fact actually differs. Adding
`parent_title` to its comparison means every existing season row is corrected on
the next ordinary import — one write per season, once, with no poster
re-downloaded and no forced import. A library that has already been reconciled
still writes nothing.

### 4. The action is an ordinary filtered view, and says so

Activation sets the search box to the derived query and calls `switchCategory()`
for the All view. The result is a normal search: the URL is
`/library/all?q=Breaking+Bad`, the summary line reads `12 matches for "Breaking
Bad" in All`, there is exactly one Clear search control, and back/forward work.

This is a deliberate design position, not a shortcut. The interface says *"I
typed this for you"* — it never claims a relation it cannot guarantee. When the
search over-matches (Decision 1), the user sees the query that produced the
result and can edit it in the box. A UI that presented this as a "set" would have
to hide the query, and would then be silently wrong instead of visibly
approximate.

It also means every requirement already written about a filtered view applies
without being restated.

**Why the All view:** a season's siblings live in `tv-seasons` while its show
poster lives in `tv-shows`. Matching a set means seeing both. For a movie, All
additionally picks up the collection poster in `collections`.

**Why `switchCategory()`:** it is the single sanctioned way to change category and
owes seven things — active tab, results, browser title, history entry,
carried-over search, scroll position, and infinite scroll re-armed for the new
grid. `categoryUrl()` reads the live search input, so setting the input's value
first is what carries the query through. A second path would have to agree about
all seven and would eventually stop agreeing.

Setting `.value` programmatically does not fire an `input` event, so the 250 ms
live-search debounce cannot double-fire.

### 5. The derived query is the raw recorded title

- a season → its `parent_title`
- a movie, show, or collection → its recorded `title`
- no Plex record, or a season not yet backfilled → `Poster::title()`

The raw `title`, **not** `captionTitle()`. The caption appends the release year,
and a query carrying "(1999)" would narrow the search back down to the single
poster the user started from — the exact opposite of the point.

### 6. An anchor, not a button

The existing actions split: Download is an `<a>`, the rest are `<button>`s driven
by `data-action`. This one is an anchor, `href="/library/all?q=<derived>"`, marked
with a data attribute for the delegated click handler to intercept.

Without JavaScript it is a working full page load to the correct filtered view.
With it, the no-reload path runs. And it is a real link, so it can be
middle-clicked, opened in a new tab, and copied — which, incidentally, gives back
a more useful "copy" than the URL action being removed.

### 7. Copy URL is the action to remove

`/posters/{category}/{filename}` is behind the session; only `/wall/poster/...`
is public. A copied URL therefore works only in the browser that copied it. Of
the seven actions it is the only one whose result cannot be used anywhere else.

**Full screen was considered and rejected.** On a pointer device it duplicates
the card-image click, so it looks redundant — but on touch, a tap opens the action
sheet, so the button is the *only* route to the fullscreen viewer there. Removing
it would break the phone to tidy the desktop.

**Relocating Copy URL into the fullscreen viewer was rejected.** The viewer
deliberately declares no dialog role because it holds nothing focusable, and a
test pins that. Overturning it to rehome a low-value action is not a trade worth
making.

### 8. The label is "Related posters"

Sentence case: it is an action, not one of the surfaces the interface offers by
name. "Show related" was rejected as ambiguous with the media type TV Show;
"Find related" was rejected because it reads as a sibling of the named surface
Find Posters and is not one.

It renders through the existing `action_body()` macro, so the phone action sheet
— which clones `.card__actions` outerHTML rather than re-rendering it — picks it
up from the same edit. That is the structural defence against a mobile/desktop
divergence and it must stay that way.

A new glyph is needed, reading as "a set of these" and visually distinct from the
existing `collections` icon.

## Risks / Trade-offs

**A title search over-matches.** Two shows called *The Office* appear together.
→ Accepted and made visible: the query is shown in the summary and is editable in
the search box (Decision 4). No silent filtering to go wrong.

**A title search under-matches.** A season Plex names differently from its show
will not be gathered. → Bounded by Decision 3: the query comes from the recorded
`parent_title`, which is the show's actual name, so the sibling seasons agree with
each other by construction.

**Seasons behave narrowly until the first import after upgrade.** A season with
no `parent_title` yet falls back to its own full title and finds mainly itself.
→ Self-healing on the next scheduled or manual import, with no user action. Never
wrong, only narrow. Movies and collections — the headline case — work immediately
with no backfill at all. This window is specced rather than left implicit.

**Changing what search matches changes existing behaviour.** Searching by library
name stops working; some queries that matched a mangled filename may now match
the clean title instead (and vice versa). → This is the point of the change, and
it is a spec-level modification to `search` rather than a silent adjustment. Every
affected behaviour is written into the delta.

**Losing Copy URL removes a capability some user may have a use for.** → Download
still exports the image, and the new action is itself a real link that can be
copied. The removed URL required a logged-in session to resolve, so it had no use
outside the copier's own browser.

**Ordering inside a result group looks slightly odd.** Under A–Z, a show's
seasons currently sort *before* the show's own poster, because `NaturalOrder` pads
digit runs and `-` sorts below `0`. → Left alone deliberately. The group is
contiguous, which is all the feature needs, and special-casing it would breach
"Search filters without reordering".

## Migration Plan

1. The `parent_title` column is added by `Database::migrate()` on first boot after
   the upgrade. `ensureColumn` is idempotent and additive; existing rows take the
   `''` default.
2. Seasons backfill on the next import, scheduled or manual, via the existing
   reconcile-on-skip path. Nothing is re-downloaded and nothing is asked of the
   user.
3. Rollback is the previous Docker tag. The extra column is inert to older code —
   `PlexItemRecord::fromRow()` reads columns by name and ignores unknown ones,
   and `upsert()` names its columns explicitly — so a rolled-back install runs
   against the migrated database unharmed.

## Open Questions

None blocking. The one judgement left for implementation is the exact drawing of
the new glyph, which is settled at the point it is drawn and reviewed.
