## 1. Carry the identifier into the query

- [x] 1.1 Add `?string $tmdbId = null` to `PosterQuery`, documenting that it is
      the show's identifier for a season and that null means "not recorded"
- [x] 1.2 In `ChangePosterController::findPosters()`, pass `$record->tmdbId` into
      the `PosterQuery` it builds

## 2. Send the identifier

- [x] 2.1 In `PosteriaApiPosterSource::params()`, add `tmdb_id` when the query
      carries a sendable identifier — non-empty, all digits, greater than zero —
      and omit it otherwise (Decision 1)
- [x] 2.2 Omit `tmdb_id` unconditionally for `PlexMediaType::Collection`,
      regardless of what is recorded (Decision 2)
- [x] 2.3 Confirm `q` is still sent on every search including identified ones, and
      that `year` is still sent unconditionally (Decision 3); no code change
      expected — record what was checked

## 3. Detect and repair a stale identifier

- [x] 3.1 Add `?string $correctedTmdbId = null` to `PosterSearchResult`, threaded
      through `found()` and defaulted to null on `failed()`
- [x] 3.2 In `PosteriaApiPosterSource::interpret()`, compare `query.tmdb_id`
      against `match.tmdb_id`, accept each only as a positive whole number, and
      report the matched one only when the two differ — the class writes no local
      state (Decision 4)
- [x] 3.3 In `ChangePosterController::findPosters()`, upsert the record with the
      corrected identifier when the result carries one and the item has one to
      replace; do nothing otherwise (Decision 5)
- [x] 3.4 Log the correction at the point of the write, with the old and new
      identifier and the item, and confirm the user-facing JSON response is
      unchanged by it

## 4. Tests

- [x] 4.1 `PosteriaApiPosterSourceTest`: `tmdb_id` sent for a movie and a show;
      omitted with no identifier; omitted for a collection that has one; omitted
      for a non-numeric, empty and zero identifier
- [x] 4.2 `PosteriaApiPosterSourceTest`: a season search sends the identifier
      together with `season=N`, including `season=0` for Specials, and `q` and
      `year` are still present alongside an identifier
- [x] 4.3 `PosteriaApiPosterSourceTest`: `correctedTmdbId` is set only on a
      mismatch; null when either field is absent, non-numeric or zero; a 200
      with `posters: []` still maps to `NoArtwork` and sends no second request
- [x] 4.4 `ChangePosterTest` (functional): a stale recorded identifier is replaced
      by the matched one, an agreeing identifier leaves the record untouched, and
      a search that sent no identifier records none
- [x] 4.5 `ChangePosterTest` (functional): the JSON returned to the browser is the
      same whether or not a correction happened
- [x] 4.6 Extend `FakePosterSource` only as far as these tests need — it must keep
      satisfying `PosterSource` with the widened result

## 5. Gates and docs

- [x] 5.1 `composer test`, `composer stan`, `composer cs` all pass
- [x] 5.2 Check `README.md`, `docs/` and `CLAUDE.md` for staleness. Expected to be
      none — no configuration, UI or user-visible behaviour changes, and the
      poster-sources spec forbids documenting the service's request format — so
      state that explicitly rather than inventing edits

## 6. Live verification (before archiving)

- [ ] 6.1 Against the `:dev` image and a real library: search an item whose title
      does not match upstream (a locally annotated title) and confirm candidates
      come back where they previously did not
- [ ] 6.2 Confirm the parameter is arriving: repeat one search by hand with
      `debug=true` and check `debug.identified_by` reads `tmdb_id`, not `title`
      (Decision 8)
- [x] 6.3 Resolve the open question: confirm `match.tmdb_id` is the **show's**
      identifier, so the repair path cannot write a season-level id into a season
      row — answered from the service side against production rather than by a
      `:dev` run (`q=Breaking Bad&type=season&season=2` → `match.tmdb_id` 1396,
      the show; season carried only as `match.season.number`; no season-level id
      anywhere in the response, on either the title path or the id path). See
      design Decision 9
- [ ] 6.4 Confirm a collection search still succeeds by title and sends no
      identifier, and that an item with no recorded identifier is unchanged
