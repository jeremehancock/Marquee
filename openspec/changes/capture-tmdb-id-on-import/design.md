## Context

Find Posters resolves a work by title. `PosteriaApiPosterSource` builds
`?q=<title>&type=<type>` from the stored `plex_items` row, and the service scores
the title against candidates with a deliberately strict floor. That strictness is
load-bearing — it is what stops `The Matrix` returning `The Matrix Reloaded`
artwork — but it leaves a residual class no threshold can reach:

| Stored Plex title | Intended work | Outcome |
| --- | --- | --- |
| `Spider-Noir B&W` | Spider-Noir | no match (a local annotation) |
| `Breaking Bad El Camino` | El Camino | no match (a *distinct* film) |
| `Ready or Not 2 Hear I Come` | Ready or Not: Here I Come | no match |

The first must match and the second must not, and they score identically, so
loosening the search cannot separate them.

A TMDB identifier resolves a work exactly and cannot resolve to the wrong one, so
it removes these items from the matcher's jurisdiction rather than re-tuning it.
Plex already holds that identifier for items matched by a modern agent, and
Marquee currently discards it — not late, but entirely:
`HttpPlexClient::item()` reads five attributes (`ratingKey`, `title`, `year`,
`thumb`, `addedAt`) and never looks at the item's identifiers.

Under the Plex Movie and Plex TV Series agents the `guid` **attribute** is opaque
(`plex://movie/5d7768264de0ee001fcc87e0`) and useless for this. The external
identifiers arrive as child elements:

```xml
<Video ratingKey="10" title="Spider-Noir" year="2026" thumb="/t/10">
  <Guid id="imdb://tt5433138"/>
  <Guid id="tmdb://385128"/>
  <Guid id="tvdb://8856"/>
</Video>
```

Constraints:

- The posteria.app API **does not accept a TMDB identifier yet**. It is being
  added after this change ships, so nothing can consume the stored value during
  this change.
- Existing user databases must keep opening. Marquee is early alpha, but user
  data is not disposable.
- Import cost must not grow. A per-item metadata fetch would turn a 2,000-movie
  library into 2,000 extra requests against the user's server.

## Goals / Non-Goals

**Goals:**

- Record each Plex item's TMDB identifier during import, where one exists.
- Keep the identifier available for seasons in the form the poster service will
  want it: the show's identifier, paired with the season number already stored.
- Add no Plex requests. The identifier must arrive in a request import already
  makes.
- Degrade silently and completely when no identifier is available, for any
  reason.
- Populate the identifier on existing installs through an ordinary re-import,
  with no rebuild and no user action beyond importing.

**Non-Goals:**

- Sending the identifier anywhere. `PosterQuery`, `PosteriaApiPosterSource` and
  `ChangePosterController` are untouched by this change.
- Recording IMDb or TVDB identifiers (Decision 2).
- Supporting libraries on legacy Plex agents (Decision 6).
- Any UI. Nothing about this change is visible to the user in this release.
- Backfilling identifiers for items already in the database without a re-import.
  Import is the only writer of item mappings and stays that way.

## Decisions

### 1. `includeGuids=1` on the library listing, not per-item metadata fetches

The `<Guid>` children are absent from a default listing response. Two ways to get
them:

| Approach | Requests for a 2,000-item library |
| --- | --- |
| `GET /library/metadata/{ratingKey}` per item | 2,000 |
| `GET /library/sections/{k}/all?includeGuids=1` | 1 |

`includeGuids` exists precisely to avoid the first — it was added so that clients
would stop hammering servers item by item. Import already calls
`/library/sections/{k}/all` (`HttpPlexClient::items()`) and
`/library/sections/{k}/collections`, so this is one query parameter on requests
that already happen. **Import's request count is unchanged.**

The per-item approach is rejected outright: it would make import cost scale with
library size for a field that is a hint, not a necessity.

### 2. Store the TMDB identifier only

Plex reports up to three identifiers. Only TMDB is stored.

The poster service's contract is TMDB-only — a TMDB identifier is the sole output
of its resolution step, which is exactly why supplying one skips resolution. An
`imdb_id` or `tvdb_id` column would have no consumer on any roadmap, and a column
is permanent in a way code is not: every future migration, record object and
upsert carries it forever.

If TVDB-only items later prove to be a meaningful share of TV libraries, that is a
separate change with its own evidence behind it. Speculative storage now buys
nothing and cannot be removed cheaply.

### 3. `tmdb_id TEXT`, nullable

TEXT rather than INTEGER: a TMDB identifier is an opaque key, never an operand.
Nothing sums, averages or compares it, and `rating_key` — the other external key
in this table — is already TEXT. Storing it as text also means it goes onto a
query string later without a conversion step.

Nullable rather than defaulted to `''` or `0`: null means "not known", and that
distinction is real here, because "not known" is the *permanent* state for every
collection. This follows `year` and `season_number`, which are nullable for the
same reason, rather than `section_key`/`thumb`, which default to `''`.

The column is added through `Database::ensureColumn()`, the existing idempotent
path that has already introduced five columns. Older databases gain the column on
first boot with no migration step and no rebuild.

### 4. A season stores its show's identifier

A Plex season often has no external identifier of its own, and would not be useful
if it did: the poster service addresses a season as **the show's identifier plus a
season number**, not as a season identifier.

This needs no new plumbing. `HttpPlexClient::seasons(PlexItem $show)` already
copies `parentTitle`, `sectionKey` and `libraryTitle` off `$show` onto each
season; the show's identifier rides the same channel. The season number is already
stored separately, so the pair is complete at the point of storage.

A useful consequence for the follow-up change: this retires the regex in
`PosteriaApiPosterSource::showFromTitle()` that currently recovers a show's name
from the composite `"Show - Season 2"` display title.

### 5. Collections never carry an identifier, permanently

A Plex collection is a local grouping. Its guid is server-generated
(`collection://<uuid>`) and there is no upstream record to point at — user-made
collections like `Christmas Movies` or `Marvel Cinematic Universe` have no TMDB
existence at all.

Collections therefore store a null identifier forever and stay on the title path.
This is recorded as a decision rather than left implicit so that a future reader
does not mistake it for an oversight and go looking for a collection identifier
that cannot exist. The poster service's recent fix for bare collection titles is
the whole answer for this type.

### 6. Legacy-agent identifiers are not parsed

Libraries still on the pre-2021 agents put the identifier in the `guid`
*attribute* (`com.plexapp.agents.themoviedb://1726?lang=en`) and emit no `<Guid>`
children. Supporting them means a second extraction rule with different parsing,
different failure modes and its own tests, for a shrinking population.

Those libraries record no identifier and keep using title matching — exactly what
they do today. One extraction rule, not two. If it turns out users are still on
legacy agents in numbers, adding the attribute path later is additive and breaks
nothing.

### 7. Capture ships before consumption, deliberately

This change stores a value that nothing reads. That is the point.

The alternative — landing capture and consumption together — has a failure mode:
on the day the feature ships, every deployed install has an empty `tmdb_id` column
until its next import, so every user falls back to title matching for exactly as
long as it takes them to re-import. Shipping capture first moves that lag *before*
the feature exists, so the data is already in place when the poster service starts
accepting the parameter.

The cost is one release carrying an unread column. That is cheap, reversible, and
invisible to users.

### 8. No server version detection

Plex ignores query parameters it does not recognise. On a server too old to
support `includeGuids`, the listing comes back without `<Guid>` children, every
item records a null identifier, and behaviour is identical to today.

So there is no version probe, no capability check and no fallback branch. The
degradation path and the collection path are the same code path — "no identifier
found" — which means it is exercised constantly rather than only on old servers.

### 9. Absence is never an error

A missing identifier must not fail an item, warn, or count toward the import's
failure tally. It is the normal outcome for collections, for unmatched media, for
personal media, and for legacy-agent libraries.

This mirrors how `year` and `addedAt` are already handled, and it is asserted
directly in the spec (*"A server that reports no identifiers does not fail the
import"*) so a future refactor cannot quietly turn it into a hard failure.

## Risks / Trade-offs

**[`includeGuids=1` is unsupported or silently ignored on some servers]** → Every
item records a null identifier and Find Posters behaves exactly as it does today.
The feature is additive, so the worst case is no benefit, not a regression
(Decision 8).

**[The listing response grows]** → Three short elements per item. On a large
library this is a modest increase in a response already carrying titles, summaries
and artwork paths, and it replaces nothing. Measured against the alternative it is
negligible: Decision 1's rejected approach multiplies the *request count* by the
item count.

**[A column ships with no reader for one release]** → Accepted, and the reason it
is worth it is Decision 7. If the poster service's parameter never materialises,
the cost of having stored the value is one nullable column.

**[Coverage is unmeasured]** → We know identifiers exist for modern-agent movies
and shows, but not what fraction of a real library carries one. This does not gate
the change: unmeasured coverage means an unknown *size* of win, not an unknown
risk, because the null path is safe by construction. It can be measured directly
once ids are stored, which is another argument for capturing before consuming.

**[The identifier goes stale if Plex re-matches an item]** → Re-import overwrites
the mapping, including the identifier, so a corrected match in Plex propagates on
the next import. It does not self-heal between imports — acceptable, because the
same is already true of `title` and `year`, which Find Posters depends on today.

## Migration Plan

1. `Database::ensureColumn()` adds `tmdb_id TEXT` on first boot after upgrade.
   Idempotent, no downtime, no rebuild. Existing rows have a null identifier.
2. Users import as they normally would. Identifiers populate for every item the
   import touches.
3. `PLEX_AUTO_IMPORT` installs populate on their next scheduled run with no user
   action at all.
4. Until the poster service accepts the parameter, no behaviour changes anywhere.

**Rollback:** revert the code. The extra column is inert — older Marquee builds
select named columns and ignore it, so a downgraded install keeps working with the
column present.

## Open Questions

None blocking. Two things to check with real data once this is running, both
inputs to the follow-up change rather than to this one:

- What fraction of a real library carries a TMDB identifier, and how much of the
  remainder is TVDB-only TV (which would inform whether Decision 2 should be
  revisited).
- Whether seasons in a show library reliably inherit a usable show identifier in
  practice, or whether some shows carry none at all.
