## 1. Persist the facts a search needs

- [x] 1.1 Add nullable `year` and `season_number` columns to `plex_items` via
      `Database::ensureColumn()`, with comments matching the existing style
- [x] 1.2 Add `?int $year` and `?int $seasonNumber` to `PlexItemRecord`, including
      `fromRow()` mapping that preserves `null` (do not coerce a missing value to `0`)
- [x] 1.3 Add a nullable-int scalar helper if `Support\Scalar` lacks one, so
      `fromRow()` stays consistent with the existing conversions
- [x] 1.4 Persist both fields in `PlexItemRepository::upsert()`, including the
      `ON CONFLICT` update list so existing rows gain them on re-import
- [x] 1.5 Capture Plex's season `index` attribute in `HttpPlexClient::seasons()` and
      carry it on `PlexItem` as a nullable season number
- [x] 1.6 Pass `year` and the season number through `ImportService` into the record

## 2. Model the new contract

- [x] 2.1 Add `PosterQuery` (title, `PlexMediaType`, nullable season number, nullable
      year)
- [x] 2.2 Add `PosterCandidate` with `url` and nullable `thumb`, `source`, `width`,
      `height`, `language`, `score`; expose a `displayUrl()` resolving `thumb ?? url`
- [x] 2.3 Add `PosterSearchOutcome` enum: `Ok`, `Partial`, `NoMatch`, `NoArtwork`,
      `RateLimited`, `Unavailable`
- [x] 2.4 Add `PosterSearchResult` holding an outcome and a `list<PosterCandidate>`
- [x] 2.5 Change the `PosterSource` interface to `find(PosterQuery): PosterSearchResult`

## 3. Rewrite the API client

- [x] 3.1 Build the request against `POSTER_SOURCE_URL . '/marquee/api/v1/posters'` with
      `q`, `type`, and `season` / `year` only when non-null — `type` uses
      `PlexMediaType->value` (`movie`/`show`/`season`/`collection`) directly
- [x] 3.2 Send `X-Client-Info` as base64 of
      `{name: "Marquee", version: <app version>, ts: <now ms>}`, taking the version from
      the existing bootstrap helper
- [x] 3.3 Delete `timeOffset()`, the `timeOffset` property, and the `/api/time.php`
      request
- [x] 3.4 Stop sending `User-Agent: Posteria/1.0`; do not send `?key=`
- [x] 3.5 Parse `posters[]` into `PosterCandidate` objects, treating every optional
      field as absent-not-null, and preserving the server's order
- [x] 3.6 Remove the client-side URL de-duplication
- [x] 3.7 Map responses onto outcomes per design Decision 2, using `success` and HTTP
      status — never the presence of `code`
- [x] 3.8 Log the real status, `code` and `providers` map on every non-`Ok` outcome so
      failures are diagnosable
- [x] 3.9 Add the transitional season-number fallback (design Decision 7) for records
      predating this change, handling `Specials` and `S00` as season 0, marked clearly
      as removable

## 4. Wire up the controller

- [x] 4.1 Build a `PosterQuery` from the stored record, passing the real season number
      and year rather than `null`
- [x] 4.2 Use `!== null` for the season number everywhere — season 0 is a real value
- [x] 4.3 Return the outcome alongside candidates in the JSON response, with a distinct
      user-facing message per outcome and a flag for `Partial`
- [x] 4.4 Serialize candidates as objects (`url`, `thumb`, `source`) instead of bare
      URL strings

## 5. Frontend

- [x] 5.1 Render the candidate grid from `thumb ?? url` per candidate
- [x] 5.2 Use the full-resolution `url` for the full-screen preview and for the apply
      request
- [x] 5.3 Surface the distinct outcome messages, including an "incomplete results"
      notice for `Partial`
- [x] 5.4 Keep the server's ordering; add no client-side sort or filter
- [x] 5.5 Verify no request-building code can emit `season` as falsy-omitted for
      Specials — the browser sends only `filename`; the season number is read from
      the stored record server-side, so no JS truthiness test can reach it

## 6. Remove Mediux as a source

- [x] 6.1 Update the source list in the `PosteriaApiPosterSource` class docblock to
      TMDB / fanart.tv / TheTVDB
- [x] 6.2 Update `openspec/config.yaml` — the external-dependency note and the
      `poster-sources` line in the capability map (also the project-context line, and
      dropped the now-false "provides a server time-sync endpoint")
- [x] 6.3 Update the Purpose section of `openspec/specs/poster-sources/spec.md`
- [x] 6.4 Leave the "also supports Mediux URLs" hint in `gallery.html.twig` untouched —
      it describes change-by-URL, not a poster source (design Decision 11)

## 7. Tests

- [x] 7.1 Rewrite `PosteriaApiPosterSourceTest` against the new response shape: request
      URL and query string, `X-Client-Info` payload, and that no time-sync request is
      made
- [x] 7.2 Cover every outcome mapping: `Ok`, `Partial` (200 + `code: partial`),
      `NoArtwork` (200 + empty), `NoMatch` (404), `RateLimited` (429), `Unavailable`
      (503, 401, transport failure, unparseable body)
- [x] 7.3 Cover candidates with fields absent and assert `displayUrl()` falls back to
      `url`. Verified live: fanart.tv supplies **no `thumb`, `width` or `height`**;
      TheTVDB supplies no `score`; and even TMDB omits `score` on ~40% and `language` on
      ~35% of its own posters. Absence is the common case, not an edge case
- [x] 7.4 Assert server order is preserved and duplicate URLs are not removed
- [x] 7.5 Test season 0: query building sends `season=0`, and the transitional fallback
      maps `Specials` and `S00` to 0
- [x] 7.6 Test the repository round-trip for `year` and `season_number`, including
      `null` and `0`, and that re-upsert updates them on an existing row
- [x] 7.7 Test that season import captures Plex's `index`, including `index="0"` — in
      `HttpPlexClientTest` (parsing) and `ImportServiceTest` (reaches the stored record)
- [x] 7.8 Add a controller test asserting a distinct message per outcome and that a
      failure leaves the poster file unchanged

## 8. Docs

- [x] 8.1 Update the `POSTER_SOURCE_URL` row in the README configuration table if its
      description no longer reads accurately — no edit needed: it still describes a base
      URL with the same default, which Decision 5 deliberately preserved
- [x] 8.2 Check the README's Find Posters and FAQ sections for anything made stale;
      confirm posteria.app is still named as the service and no endpoint or response
      detail is documented (existing spec requirement) — re-checked, all four mentions
      (lines 13, 26, 243, 321) name only the service, so nothing went stale
- [x] 8.3 Confirm the README does not present Mediux as a poster source. Verified at
      proposal time that README.md and docs/ contain **no** Mediux mention, so the
      expected outcome is no edit — re-check rather than assume, and do not invent one
      — re-checked with a case-insensitive grep over README.md and docs/: no match, no
      edit made
- [x] 8.4 State explicitly in the change summary if no user-facing documentation needed
      updating — **no user-facing documentation needed updating.** The internal source
      lists in `openspec/config.yaml` and the `poster-sources` spec Purpose did change
      (tasks 6.2, 6.3)

## 9. Gates

- [x] 9.1 `composer test` passes — 254 tests, 654 assertions
- [x] 9.2 `composer stan` passes at level 10, including the widened nullable contracts
- [x] 9.3 `composer cs` passes — 0 of 109 files need fixing
- [x] 9.4 `openspec validate rewrite-find-posters-marquee-api --strict` passes
- [x] 9.5 Live check against the deployed endpoint once available: a movie, a show, a
      season, Specials, and a deliberately bogus title returning `NoMatch` — run through
      `PosteriaApiPosterSource` itself against the deployed endpoint. All `Ok` with
      counts matching the handoff notes exactly: The Matrix 191, Breaking Bad 178,
      season 2 → 46, Specials 10; collection 44; bogus title → `NoMatch` with the 404
      logged. TheTVDB contributed throughout, so `TVDB_API_KEY` is now set on
      posteria.app. `thumb` presence varies by source as documented (154/191 on the
      movie), and the grid image resolved to TMDB `w342` rather than `original`
