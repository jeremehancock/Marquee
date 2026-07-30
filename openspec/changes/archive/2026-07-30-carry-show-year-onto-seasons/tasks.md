## 1. Carry the show's year onto its seasons

- [x] 1.1 In `src/Plex/HttpPlexClient.php`, change `seasons()` to construct each
      `PlexItem` with `year: $show->year` instead of `year: null`. Replace the
      comment justifying the null with one stating why the value is the show's
      year and not the season's air year: a season search resolves the show first,
      so the show's year is what identifies the work.
- [x] 1.2 Verify nothing downstream changes behaviour when a season now carries a
      year — confirm `ImportService::deriveFilename()` still appends a year for
      movies only, so no season filename changes, and confirm no Twig template
      reads the field.

## 2. Backfill the year on the skip path

- [x] 2.1 In `src/Plex/Import/ImportService.php`, extend the skip path's backfill
      so it also writes a release year that is absent from the stored mapping
      while Plex now reports one. Keep the existing null → known constraint: an
      already-recorded year is never rewritten, because the skip path exists to
      cost almost nothing on every scheduled import.
- [x] 2.2 Make the backfill write both facts in a single upsert when both are
      missing, rather than two writes for one skipped item, and rename the method
      to reflect that it backfills more than the identifier.
- [x] 2.3 Confirm the full-import path needs no change — the repository upsert
      already writes `year = excluded.year`.

## 3. Refuse an undisambiguated self-heal

- [x] 3.1 In `src/Controller/ChangePosterController.php`, make
      `correctStaleTmdbId()` decline to write when the search carried nothing to
      distinguish works sharing a title — currently, when no release year was sent.
      Determine this from what Marquee sent, never from the source reporting that
      it fell back to the title.
- [x] 3.2 Log the refusal at a level that makes it findable, recording the item,
      the stale identifier, and the identifier that was not written. A refusal is
      the case worth explaining, since the alternative is a silent permanent error.
- [x] 3.3 Rewrite the method's docblock: the existing "a known-bad id cannot get
      worse" argument holds only when the replacement is well-founded, and this
      guard restores that precondition rather than adding a new principle.
- [x] 3.4 Confirm the user-facing result is untouched on the refusal path — the
      candidates shown are still the ones the source returned, with no message
      about the refusal.

## 4. Tests

- [x] 4.1 In `tests/Unit/Plex/HttpPlexClientTest.php`, assert `seasons()` carries
      the show's year onto every season, and that a show with no year yields
      seasons with no year rather than failing.
- [x] 4.2 In `tests/Unit/Plex/ImportServiceTest.php`, assert a skipped item with no
      stored year gains one without a poster download and is still counted as
      skipped; assert a skipped item that already has a year is not written to;
      assert a skipped item missing both year and identifier gains both.
- [x] 4.3 In `tests/Functional/ChangePosterTest.php`, assert a correction is
      recorded when a year was sent, refused when no year was sent, and that the
      refused case leaves the stored identifier unchanged so the mismatch is
      detected again on a later search.
- [x] 4.4 Assert the refusal does not alter the response the user sees — same
      candidates, same absence of any correction message.
- [x] 4.5 In `tests/Unit/Poster/PosteriaApiPosterSourceTest.php`, assert a season
      search puts the recorded year on the wire alongside `season=N` and the
      show's identifier, confirming the year is not suppressed when an identifier
      is present.

## 5. Gates and docs

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs` — all three must
      pass.
- [x] 5.2 Run `openspec validate carry-show-year-onto-seasons --strict`.
- [x] 5.3 Check whether `README.md`, `docs/`, or `CLAUDE.md` are made stale by this
      change. It is internal to import and search behaviour with no user-facing
      surface, so state explicitly that nothing needed editing if that is the
      finding, rather than inventing edits.
