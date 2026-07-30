## 1. Fix the comparison

- [x] 1.1 Change `PosteriaApiPosterSource::correctedTmdbId()` to take the
      `PosterQuery` and derive the sent identifier from `sendableTmdbId($query)`,
      reporting a correction when it is non-null and differs from
      `match.tmdb_id` (Decision 1)
- [x] 1.2 Remove every read of `query.tmdb_id`; nothing may depend on the source
      echoing what it was sent
- [x] 1.3 Confirm `correctStaleTmdbId()` in `ChangePosterController` needs no
      change, and that what is sent on a search is untouched

## 2. Tests

- [x] 2.1 Rebuild the correction tests around production-shaped responses, where
      `query.tmdb_id` equals `match.tmdb_id` in every case including the stale
      one — the old fixtures encode the assumption that caused this
- [x] 2.2 The case that failed in the field: a sent identifier the source does not
      recognise, response reporting the resolved identifier throughout, is
      detected and reported as a correction
- [x] 2.3 A collection whose recorded identifier was withheld reports no
      correction, even though `match.tmdb_id` differs from what is stored
- [x] 2.4 No correction when nothing was sent, when the sent identifier agrees,
      when `match.tmdb_id` is absent or unusable, and on a failed search
- [x] 2.5 Confirm the functional tests in `ChangePosterTest` still pass unchanged
      — they drive `correctedTmdbId` directly and are unaffected by how it is
      derived

## 3. Gates and docs

- [x] 3.1 `composer test`, `composer stan`, `composer cs` all pass
- [x] 3.2 Docs gate: no configuration, UI or documented behaviour changes, and the
      spec forbids documenting the service's request format — state that rather
      than inventing edits

## 4. Live verification (before archiving)

- [x] 4.1 Against the `:dev` image: search an item after deliberately storing a
      wrong TMDB identifier for it, and confirm the stored identifier is rewritten
      to the one the service matched and the correction is logged
- [x] 4.2 Confirm a normal search with a correct identifier still writes nothing,
      and that a collection search writes nothing
