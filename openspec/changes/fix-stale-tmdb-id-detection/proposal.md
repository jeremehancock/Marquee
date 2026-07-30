## Why

Marquee shipped self-healing for stale TMDB identifiers in v1.4.0, and it has
never once fired. The detection compares two fields of the response against each
other, on the documented promise that one of them echoes the identifier Marquee
sent. It does not — it reports the identifier the source *resolved*, which is the
other field. The two are always equal, so a stale identifier is never detected and
never corrected.

The requirement is right and stays as written; only the signal it was built on was
wrong. Nothing is corrupted — the failure is a silent no-op — but an item with a
wrong identifier stays wrong forever, which is exactly what the requirement exists
to prevent.

## What Changes

- Detect a stale identifier by comparing the identifier Marquee **sent** against
  the one the source matched, instead of comparing two response fields. Marquee
  already knows what it sent, so the check no longer depends on the source
  echoing it back.
- Stop reading `query.tmdb_id` altogether.
- Pin the lesson in the spec: detection MUST NOT depend on the source reporting
  the identifier that was sent.

Not breaking, and not user-visible except that the self-heal now works. No
configuration, no schema change, no change to what is sent on a search.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: the existing "stale recorded identifier is corrected"
  requirement gains a scenario fixing which signal detection may rely on. The
  behaviour it requires is unchanged.

## Impact

- `src/Poster/Source/PosteriaApiPosterSource.php` — the comparison, and the
  removal of `query.tmdb_id` from it.
- `tests/Unit/Poster/PosteriaApiPosterSourceTest.php` — the existing correction
  tests encode the same wrong assumption the code did and must be rebuilt around
  responses shaped like production's.
- No controller change: `correctStaleTmdbId()` is correct and stays as it is.
- External: none. The fix holds whatever the source does with `query.tmdb_id`.
