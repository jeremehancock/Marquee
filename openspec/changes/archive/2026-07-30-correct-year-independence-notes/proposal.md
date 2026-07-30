## Why

Four places in this repository state that the poster source ignores `year` when a
TMDB identifier is supplied. That is false, and it is the kind of false that costs
nothing today and something later: the code correctly sends both, so a reader who
believes the note will find a parameter that "has no effect" being sent anyway, and
remove it.

The accurate statement is that `year` has no effect *while a supplied identifier
resolves*. When the identifier is unknown upstream the source falls back to
resolving the title, and `year` feeds that fallback — the provider search, the
scoring, and the resolution cache key. An exact title match scores 60; the year
adjusts it by +20 exact, +10 off-by-one, −10 wider. Two works sharing a title score
identically without it, leaving a popularity tie-break to decide.

So removing `year` would not break the common path. It would degrade precisely the
cases the fallback exists to rescue — which is why the note is worth correcting even
though nothing is broken.

## What Changes

- Correct the wording in the code comment and the test assertion message that
  assert `year` is ignored.
- State in the spec that `year` and the TMDB identifier are sent independently, and
  why — the spec is what gets read when this code is next changed, and it currently
  says nothing about the two interacting.
- Append a dated correction to the archived `send-tmdb-id-in-poster-search` design
  rather than rewriting its prose, so the record of what that change believed stays
  intact while the false claim stops being quotable.

No behaviour change. `year` is already sent correctly for movies and shows,
independently of the identifier, and stays exactly as it is.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: the search requirement gains an explicit statement that the
  release year is sent regardless of whether an identifier is also sent.

## Impact

- `src/Poster/Source/PosteriaApiPosterSource.php` — one comment.
- `tests/Unit/Poster/PosteriaApiPosterSourceTest.php` — one assertion message.
- `openspec/changes/archive/2026-07-30-send-tmdb-id-in-poster-search/design.md` — a
  correction note.
- No `README.md` or `docs/` impact: neither documents the service's request format,
  which the `poster-sources` spec forbids.

**Out of scope, deliberately.** Seasons carry no year at all — import records one
only for movies and shows — so a season falling back to title cannot be
disambiguated by year, and the self-heal can then write a wrong-but-valid identifier
into the row permanently. That is a real defect with a data-corruption path, needs an
import change and a `plex-import` spec change, and is not a wording fix. Tracked
separately.
