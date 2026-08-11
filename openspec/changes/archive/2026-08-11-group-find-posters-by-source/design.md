## Context

Find Posters renders every candidate the posteria.app API returns into one flat
`.find-grid`, in the order the service ranked them. The Plex Posters tab beside
it renders its candidates into labelled, counted, separately-scrolling groups.
The two tabs are visually the same kind of thing and behave differently.

Everything needed to close that gap is already in the payload. The service sets
`source` on every candidate it emits, from a closed set defined in one place
(`SOURCE_LABELS` in the posteria.app repo): `tmdb`, `thetvdb`, `fanart.tv`. All
three builders write it unconditionally — there is no path that emits a candidate
without one. `PosteriaApiPosterSource` already parses it into
`PosterCandidate::$source`, and `ChangePosterController` already forwards it to
the browser, where nothing reads it.

Constraints:

- No request change to posteria.app. This is presentation over data already held.
- The service's ranking is deliberate and stable (`marqueeRankPosters`: language
  tier, then score, then resolution, then URL as tie-break). Within a section it
  must survive untouched.
- `PosterCandidate::$source` is typed `?string`. The service never omits it, but
  PHPStan level 10 will not let that assumption go unhandled, and it should not:
  the nullable type is the only thing standing between a service-side change and
  silently dropped posters.
- The existing `poster-sources` spec forbids re-ordering candidates outright.
  Any grouping contradicts it, so the clause is narrowed rather than worked
  around.

## Goals / Non-Goals

**Goals:**

- Split Find Posters results into one labelled section per supplying service.
- Give each section heading a count, presented exactly as the Plex Posters tab
  presents its group counts.
- Fix the section order so it is identical for every item.
- Preserve the service's ranking within each section, exactly.
- Keep a candidate from an unrecognised service visible.

**Non-Goals:**

- A grand total across sections. The Plex Posters tab has none and this needs
  none.
- Any per-service filter, toggle, or preference. Sections are structure, not
  controls.
- Re-ranking within a section, or any client-side scoring. The service has
  signals Marquee does not.
- Unifying the Plex Posters and Find Posters renderers into one component. They
  share CSS after this change; merging their markup would drag the Plex tab's
  payload and spec along for no user-visible gain.
- Reacting to the service's `total` field or the 200-candidate cap.

## Decisions

### 1. Grouping happens server-side, not in Alpine

`ChangePosterController` emits an ordered list of sections instead of a flat
`posters` array:

```json
{
  "sections": [
    { "label": "TMDB",      "posters": [ … ] },
    { "label": "TVDB",      "posters": [ … ] },
    { "label": "fanart.tv", "posters": [ … ] }
  ],
  "partial": false
}
```

Three reasons over grouping in `gallery.js`:

- It is what the sibling tab already does. Plex candidates are grouped in PHP
  (`PlexPosterList::uploaded()` / `offered()`) and the controller emits
  `uploaded` and `available` as separate arrays. Grouping Find Posters in the
  client would make two adjacent tabs solve the same problem on opposite sides
  of the wire.
- It is testable in PHPUnit. Section membership, order, and the unknown-source
  fallback become assertions on a JSON payload rather than behaviour only
  reachable through a browser.
- It matches the project's stated shape — thin controllers over service classes
  and value objects, with no business logic in templates.

The client consequence is the point: the browser never learns a provider name.
It renders `x-for="section in finder.sections"` and prints `section.label`. A
fourth provider is a server-side change with no client edit at all.

`posters` is replaced rather than kept alongside `sections`. Marquee ships the
PHP and the JS in one image, so there is no version skew to bridge, and a
duplicated payload is a second thing to keep correct.

**Alternative considered:** group in `gallery.js` from the existing flat array.
Rejected — cheapest to write, but it puts the ordering rule in the one layer with
no test coverage, and diverges from the tab beside it.

### 2. A backed enum carries the slug, the label, and the order

```
enum PosterProvider: string {
    case Tmdb    = 'tmdb';
    case TheTvdb = 'thetvdb';
    case Fanart  = 'fanart.tv';
}
```

Backing it with the exact wire slug makes `tryFrom()` the whole parse, and puts
the three facts that must agree — what the service calls it, what the user sees,
and where its section sits — in one file. `tryFrom()` returning `null` for an
unrecognised slug is also exactly the signal Decision 4 needs.

This mirrors `PlexPosterOrigin`, which plays the same role for the Plex tab.

The case *name* tracks the wire slug and the label tracks the user, which is why
`TheTvdb` is labelled `TVDB`. Headings are uppercased in CSS and "THETVDB" reads
as a run of letters — the camel case that makes the service's own spelling
legible is exactly what the transform destroys. The footer's attribution still
carries the real brand, as a logo rather than text, so the two forms never appear
as conflicting words on screen.

**Alternative considered:** keep `TheTVDB` and drop the uppercase transform on
headings. Rejected — it restyles the Plex Posters tab's headings too, for a
problem that affects one label.

**Alternative considered:** a `match` in the controller. Rejected — it would
scatter the slug, the label, and the order across three expressions that have to
be kept in step by hand.

### 3. Section order is fixed at TMDB, TVDB, fanart.tv

Fixed, so the tab's shape is identical for every poster. That is the whole
request: a user who learns where fanart.tv sits should find it there next time.

The specific order is not arbitrary. It is the order the provider attribution in
the footer already presents to users (`_attribution.html.twig`, required by
`application-shell`). Two orderings of the same three names in one product should
not disagree.

It also costs the least against the ranking it displaces. TMDB is the only
provider that supplies `score`, so scored candidates sort above unscored ones
within a language tier and the service's top-ranked candidate is usually a TMDB
one. Leading with TMDB keeps "best first" true most of the time for free.

The match to the footer is deliberate and must be written down where the order is
defined. It is **not** shared as a constant: the footer needs logos and links, the
sections need wire slugs, and one string in common does not justify coupling
them. But someone reordering the footer later should be able to find out that
the sections were meant to follow.

**Alternative considered:** order sections by rank, so the section holding the
overall best candidate leads. Rejected — it preserves best-first exactly, but the
sections then move between items, which is the specific thing this change exists
to prevent.

### 4. An unrecognised source gets a trailing section, never the bin

A candidate whose `source` is null or is not one of the three enum cases goes
into a final section rather than being dropped or forced into a real provider's
section.

The set is closed today and the service always populates the field, so this
should never render. It exists because the alternative failure is invisible: if
posteria.app adds a provider, dropping its candidates looks exactly like that
provider having no artwork, and nothing anywhere reports it. A trailing section
degrades to "posters the user can still apply, under a vaguer heading."

The section is labelled `Other`. It is omitted when empty like any other section,
so in practice users never see it.

### 5. The group CSS is renamed to serve both tabs

`.plex-group`, `.plex-groups`, `.plex-group__heading`, and `.plex-group__count`
become `.poster-group*`. The Find Posters sections then reuse the rules rather
than duplicating them, and the name stops claiming the styling is Plex-specific
when both tabs use it.

This carries two hard-won behaviours to the new tab for free:

- **Sticky headings.** The heading pins to the top of the scroller while its own
  section is on screen and leaves with it. This requires the scroll container to
  be the sections *wrapper*, so `.find-grid`'s current `max-height: 62vh` and
  `overflow-y: auto` move up to `.poster-groups`, and the inner grids reset to
  `max-height: none; overflow: visible` — exactly the arrangement
  `.plex-groups .find-grid` already has.
- **Gap-based spacing, never adjacent siblings.** `.poster-group + .poster-group`
  cannot work: Alpine leaves the `<template>` in the DOM and inserts each section
  *after* it, so a `<template>` sits between every pair and the selector matches
  nothing. That mistake shipped twice unnoticed, which is why
  `PlexPosterGroupsTest` exists. It moves to the new selector names in the same
  commit and keeps guarding both tabs.

**Alternative considered:** reuse `.plex-group*` under its existing name in the
Find panel. Rejected — the name would be wrong the moment the second consumer
lands, and the class is the first thing anyone reads when debugging the layout.

**Alternative considered:** a parallel `.find-group*` block. Rejected — two copies
of a sticky-heading arrangement that took two attempts to get right.

### 6. Sections are built by partition, not by sorting

Iterate the candidates once in arrival order and append each to its provider's
bucket. Within-section order is then preserved by construction, and no sort
comparator exists that could be modified later into re-ranking.

## Risks / Trade-offs

- **The service's top-ranked candidate is no longer guaranteed to be the first
  poster on screen** → Accepted, and deliberate — the user chose consistent
  section placement over global rank. Leading with TMDB (Decision 3) keeps the
  common case aligned, and order within every section is untouched.

- **Image loading is unaffected.** Noted because it looks like it should be:
  fanart.tv supplies no `thumb`, so its candidates load at full resolution, and
  grouping gathers them together. But the same candidates load either way — this
  change re-orders cells, it does not add, remove, or resize any. `loading="lazy"`
  and the reserved `.find-item__frame` behave exactly as they do now. If anything
  the fixed order helps slightly: fanart.tv is the last section, so its
  full-resolution images sit below candidates that were previously interleaved
  into the first screenful.

- **The rename in Decision 5 touches the Plex Posters tab, which is not what this
  change is about** → Mechanical, and covered on both sides: `PlexPosterGroupsTest`
  is a shape tripwire over `app.css` and moves with the selectors, and
  `PlexPostersTabTest` covers the tab's behaviour. A missed rename is a dead
  selector, which is precisely what the tripwire was written to catch.

- **Section counts describe what arrived, not what exists.** The service ranks,
  then caps at 200 (`LIMIT_DEFAULT`), and reports the pre-cap figure in `total`,
  which Marquee neither sends a `limit` for nor reads → Left alone. Reaching the
  cap needs a title with more than two hundred de-duplicated posters, and the
  honest fix is a "there are more" affordance, which is a different change.

- **A future fourth provider lands in `Other` rather than its own section** →
  By design (Decision 4). Visible, applicable, and obviously wrong-looking to a
  maintainer, which is the point; adding the enum case is then a one-line change
  with no client edit.

## Migration Plan

No data migration and no schema change. The stored posters, the SQLite database,
and every request Marquee makes to posteria.app are untouched — this is a change
to how one already-fetched response is rendered.

Rollback is reverting the commit. Nothing outside the image holds any state that
would need unwinding.

## Open Questions

None blocking. `TVDB_API_KEY` is confirmed set on posteria.app, so the TVDB
section will carry real candidates rather than being permanently empty.
