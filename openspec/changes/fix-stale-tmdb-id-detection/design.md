## Context

`send-tmdb-id-in-poster-search` (v1.4.0) built stale-identifier detection on this
line of the service's relayed contract:

> Response `query` gains `tmdb_id`: the value you sent, or null.

`PosteriaApiPosterSource::correctedTmdbId()` therefore compares `query.tmdb_id`
against `match.tmdb_id` and reports a correction when they differ. Production says
they never do. Probed against the deployed service:

| Request | `query.tmdb_id` | `match.tmdb_id` | `debug.identified_by` |
| --- | --- | --- | --- |
| `q=The Matrix`, no id sent | **603** | 603 | `title` |
| `q=The Matrix&tmdb_id=999999999` | **603** | 603 | `tmdb_id_unknown_then_title` |
| `q=<nonsense>&tmdb_id=603` | 603 | 603 | `tmdb_id` |

Row 1 is decisive: no identifier was sent and `query.tmdb_id` still came back
`603`. The field reports the identifier the service **resolved**, not the one it
was given. So the two halves of the comparison are the same number in every case,
`correctedTmdbId` is always null, and the repair path in
`ChangePosterController::correctStaleTmdbId()` is unreachable.

The unit tests pass because they were written from the same wrong sentence: they
construct responses where `query.tmdb_id` and `match.tmdb_id` differ, which is a
shape the service never emits.

Everything else from that change is verified working in production — the identifier
is sent, `identified_by` reads `tmdb_id`, seasons round-trip, collections resolve
by title. This is a defect in one comparison, not in the feature.

## Goals / Non-Goals

**Goals:**

- Make the specced self-heal actually happen.
- Depend on nothing the service might change its mind about.
- Leave what Marquee *sends* exactly as it is — that part is verified.

**Non-Goals:**

- Sending `debug=true` to read `identified_by`. It is the most direct signal and
  still the wrong one to build on — see Decision 2.
- Waiting for a service-side fix. See Decision 3.
- Any change to `correctStaleTmdbId()`, which is correct as written.
- Re-opening whether a missing identifier should be filled in. It should not.

## Decisions

### 1. Compare against what Marquee sent, not against what the response echoes

`correctedTmdbId()` takes the `PosterQuery` and derives the sent identifier from
`sendableTmdbId($query)` — the same method that decided what to put on the wire.
A correction is reported when that is non-null and differs from `match.tmdb_id`.
`query.tmdb_id` is not read at all.

This is what the original design rejected, and the reason it gave was sound but
solved the wrong way. The worry was collections: their recorded identifier is
deliberately withheld (it is never sent), so comparing `match.tmdb_id` against the
*stored* identifier would read "matched something other than what we withheld" as
a correction and write a title-resolved identifier into a collection row.

The fix for that was never to read both halves from the response — it was to
compare against what was **sent**, which is precisely what `sendableTmdbId()`
returns: null for a collection, null for an unusable value, null when nothing is
recorded. Every case the original design was protecting against is already
excluded by the same method that excludes it from the request. Reaching for the
response was solving a problem that the sending rule had already solved.

The result no longer depends on the service echoing anything, which is the
property that failed here.

### 2. Not `debug.identified_by`, even though it says exactly the right thing

`tmdb_id_unknown_then_title` names the stale case precisely and unambiguously. It
is still the wrong thing to build on: it means sending `debug=true` on every user
search, which turns a troubleshooting flag into a load-bearing production
parameter and invites the service to treat Marquee's ordinary traffic as debug
traffic. A debug channel is not a contract. Decision 1 needs no extra parameter
and no extra trust.

### 3. Fix in Marquee rather than wait for the service

The service's documented contract and its behaviour disagree, and that is worth
reporting — but it does not need to be resolved before this is fixed. Decision 1
is correct whichever way the service goes: if `query.tmdb_id` starts echoing the
sent identifier, Marquee is unaffected, because it stopped reading the field.

Fixing this by asking the service to match its documentation would leave Marquee's
correctness resting on a remote field's semantics for the second time.

### 4. The spec pins the signal, not just the outcome

The existing requirement says a stale identifier is corrected. That was already
true on paper while being false in practice, because nothing in it constrained
*how* the staleness is noticed. A scenario is added stating that detection holds
even when the source reports the identifier it resolved rather than the one it was
sent.

That is unusually close to implementation for a spec, and it earns its place: it
is the exact assumption that failed, it is testable against a response shaped like
production's, and without it the next reader is free to reintroduce the same
comparison.

## Risks / Trade-offs

- **The rewritten tests could encode a new wrong assumption about the response.**
  → Every correction test is now built from a response shaped like the probes
  recorded above — `query.tmdb_id` equal to `match.tmdb_id` in all of them,
  including the stale case. A test that needs those two to differ is testing a
  response the service does not emit.

- **A wrong-but-valid identifier is still undetectable.** If a stored identifier
  is known to TMDB but points at the wrong work, the service resolves it happily
  and nothing in the response reveals the error. → Out of scope and unchanged;
  this is the residue that [[custom-poster-search-title]] addresses by letting the
  user search a title of their own.

- **The self-heal has never run in the field.** Any item whose identifier is stale
  has stayed stale since v1.4.0. → No accumulation problem: the correction is
  driven by searches, not by a batch, so each affected item repairs itself the
  next time a user searches for it.

## Migration Plan

Ship on `dev`, validate against the live service — specifically that a search
carrying a deliberately wrong identifier now rewrites the stored one — then
release as a patch. No data migration; no stored identifier was ever written
incorrectly, because the broken path never wrote at all.
