## Context

`PosterWallService::randomPosters()` builds its pool by looping
`PosterCategory::all()` — all four cases — and shuffling the result. Season and
collection posters are therefore in the rotation, and the pool is unweighted, so
each category's share of the wall is simply its file count. Libraries hold
several seasons per show, so on a show-heavy library season art crowds out the
shows it belongs to.

The wall is a display surface for the titles in the library. A season is a
subdivision of a title; a collection is a grouping of titles. Neither is a title,
so neither belongs on the wall.

The now-playing takeover shares the wall page but not this code path, and it is
already correct: `HttpPlexClient::session()` reads `grandparentThumb` for an
episode session, which is the show's poster. `parentThumb` — the season poster —
is read nowhere in the codebase, and there is no fallback that could reach it;
a missing show thumb falls through to the bundled placeholder. Collections cannot
stream. Nothing in this change touches that path.

## Goals / Non-Goals

**Goals:**

- The wall's random rotation draws only from Movies and TV Shows.
- The rule is recorded in the spec with its reasoning, so the narrowing reads as
  a decision rather than an oversight.
- The wall's behavior is defined so that a poster category added later is off the
  wall by default.

**Non-Goals:**

- Changing the now-playing takeover in any way.
- Making the pool configurable. Which categories are "works" is definitional
  here, not a per-installation preference.
- Weighting the pool, deduplicating, or otherwise changing how posters are
  selected within the categories that remain.
- Any change to the gallery, search, import, export, or orphan detection.
  Seasons and collections remain first-class everywhere else.

## Decisions

### The pool is an allow-list of works, not a list of exclusions

The wall selects from `[Movies, TvShows]` rather than iterating every category
and skipping `TvSeasons` and `Collections`.

Two reasons. First, half the enum is now excluded, so the negative form is the
harder one to read. Second, and more important, the two forms disagree about
what happens to a category added later: an exclusion list puts a new category on
the wall automatically and someone has to notice, whereas an allow-list keeps it
off until someone argues it is a work. The second default matches the principle
the change is built on.

*Alternative considered:* keep `PosterCategory::all()` and filter with
`in_array()`. Rejected for the default-behavior reason above.

### The policy lives in `PosterWallService`, not on `PosterCategory`

An `appearsOnWall()` method on the enum would read well at the call site, but
`PosterCategory` is shared by the gallery, search, import, export, and orphan
detection — none of which care about the wall. Putting wall policy on it leaks
one feature's taste into a type that everything depends on. The wall owns the
definition of its own pool.

*Alternative considered:* a `PosterCategory::works()` static returning the two
cases. Rejected for the same reason: "which categories are works" is a statement
about the wall's editorial rule, not a fact about the category enum. If a second
surface ever needs the same grouping, promoting it then is cheap; guessing now is
not.

### The empty-state message is left alone

A library holding only seasons and collections now yields an empty wall showing
*"No posters to show yet. Import some from Plex first."* — technically untrue,
since posters were imported. A second bespoke message was considered and
rejected: the situation is rare, one empty state is easier to reason about than
two, and the existing message points at the action that resolves it. The
behavior gets a scenario so it is deliberate rather than accidental.

### No configuration knob

`AutoImportConfig` establishes a per-type env-toggle vocabulary
(`AUTO_IMPORT_SEASONS` and friends), so a `WALL_INCLUDE_SEASONS` would have
precedent. It is still the wrong shape here: import toggles exist because what
you want in your library genuinely varies per installation, whereas the wall's
rule follows from what the wall *is*. Four env vars would be four knobs
answering one definitional question, and every one of them is a combination that
has to keep working.

## Risks / Trade-offs

- **The wall's pool shrinks, possibly by more than half** → Intended, but it is
  the first thing that will be noticed on the `:dev` image. Validate against a
  library that has seasons and collections imported, not just movies.

- **A future reader sees a two-element allow-list next to a four-case enum and
  "fixes" it back** → The spec carries the rule and its reasoning as a
  requirement, and the service documents why the enum is not iterated.

- **Someone who liked seeing collection art on the wall loses it with no way to
  get it back** → Accepted deliberately: the change is a decision about what the
  wall is for, and reintroducing choice is a later change if the rule turns out
  to be wrong. Nothing is deleted — the art stays in the gallery.

- **The main spec's Purpose paragraph says "every category" and is not a
  requirement, so no delta covers it** → Edited directly in
  `openspec/specs/poster-wall/spec.md` as an explicit task, and re-checked after
  archive so the merge did not leave it stale.

## Migration Plan

None. No configuration, database, on-disk, or API change; the batch endpoint's
shape is unchanged and returns fewer distinct posters. Rolling back is reverting
the commit.

## Open Questions

None.
