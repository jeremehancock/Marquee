## 1. Storage

- [x] 1.1 Add the `tmdb_id` column to `plex_items` in `Database::migrate()` via the
  existing `ensureColumn()` call sequence, typed `TEXT` and nullable, with a
  comment explaining that null means "not known" and is permanent for collections
  (design Decision 3)
- [x] 1.2 Add a nullable `?string $tmdbId = null` property to `PlexItemRecord`,
  positioned last in the constructor so existing call sites keep working, and read
  it in `fromRow()` with `Scalar::stringOrNull()` — adding that helper to `Scalar`
  if it does not already exist
- [x] 1.3 Carry `tmdb_id` through `PlexItemRepository::upsert()`: the INSERT column
  list, the VALUES list, the `ON CONFLICT … DO UPDATE SET` clause, and the bound
  parameter array

## 2. Reading the identifier from Plex

- [x] 2.1 Add a nullable `?string $tmdbId = null` property to `PlexItem`,
  positioned last in the constructor
- [x] 2.2 Add a private helper to `HttpPlexClient` that reads an element's `<Guid>`
  children and returns the numeric part of the `tmdb://` entry, or null when there
  is no such child — ignoring the opaque `guid` attribute entirely (design
  Decision 1) and not parsing legacy `com.plexapp.agents.*` values (design
  Decision 6)
- [x] 2.3 Append `includeGuids=1` to the `/library/sections/{key}/all` request in
  `items()` and the `/library/sections/{key}/collections` request in
  `collections()`
- [x] 2.4 Populate `tmdbId` in `HttpPlexClient::item()` from the new helper, so
  movies and shows carry their own identifier and collections receive whatever the
  listing reports (in practice none)
- [x] 2.5 Pass `$show->tmdbId` into each `PlexItem` built by
  `HttpPlexClient::seasons()`, alongside the `parentTitle`/`sectionKey` it already
  copies, so a season carries its show's identifier (design Decision 4)

## 3. Threading through import

- [x] 3.1 Pass `$item->tmdbId` into the `PlexItemRecord` constructed in
  `ImportService`, so the identifier reaches storage for every imported item
- [x] 3.2 Confirm no import path treats a null identifier as a failure — no
  warning, no skip, no increment of the failed count (design Decision 9)

## 4. Tests

- [x] 4.1 `HttpPlexClientTest`: a movie listing whose `<Video>` carries
  `<Guid id="tmdb://…"/>` alongside imdb and tvdb entries records only the TMDB
  identifier
- [x] 4.2 `HttpPlexClientTest`: an item with `<Guid>` children but no `tmdb://`
  entry, and an item with no `<Guid>` children at all, both record a null
  identifier
- [x] 4.3 `HttpPlexClientTest`: the opaque `guid="plex://movie/…"` attribute is
  never mistaken for an identifier, including when it is the only guid present
- [x] 4.4 `HttpPlexClientTest`: seasons inherit their show's identifier, and a
  season of a show with no identifier records null
- [x] 4.5 `HttpPlexClientTest`: the listing requests for items and collections both
  carry `includeGuids=1`
- [x] 4.6 `PlexItemRepositoryTest`: a record round-trips its identifier through
  `upsert()`, and a re-`upsert()` of the same rating key updates a previously null
  identifier to a real one (the "existing mappings gain the new facts" scenario)
- [x] 4.7 `ImportServiceTest`: an import stores the identifier for movies, shows
  and seasons; and an import in which no item reports an identifier completes with
  no failures
- [x] 4.8 Confirm a database created before this change opens and imports cleanly
  with the column added by `ensureColumn()`

## 5. Docs and gates

- [x] 5.1 Check whether `README.md`, `docs/` or `CLAUDE.md` are made stale by this
  change and update in the same commit; if nothing user-facing changed, say so
  explicitly rather than inventing edits
- [x] 5.2 Run `composer test`, `composer stan` and `composer cs` — all three must
  pass before commit
- [x] 5.3 Run `openspec validate capture-tmdb-id-on-import --strict`

## 6. Backfill on skip

Added after `:dev` validation showed ~99.8% coverage on a fresh import, which
made it clear the skip path would strand existing installs at nearly 0% until
their artwork changed (design Decision 10).

- [x] 6.1 In `ImportService::importItem()`, before the skip branch records the
  skip, write the mapping when the stored identifier is null and the Plex item
  now reports one — no poster download, and the item is still counted as skipped
- [x] 6.2 Keep the condition narrow (null → known only), so a scheduled import
  over an unchanged library still writes nothing
- [x] 6.3 `ImportServiceTest`: a skipped item whose stored mapping has no
  identifier gains one, is still counted as skipped, and does not re-download
  the poster (assert via `FakePlexClient::$downloads`)
- [x] 6.4 `ImportServiceTest`: a skipped item that already has an identifier is
  not rewritten, and a skipped item for which Plex reports none stays null
- [x] 6.5 Re-run `composer test`, `composer stan`, `composer cs` and
  `openspec validate --strict`
