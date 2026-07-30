## 1. Correct the wording

- [x] 1.1 In `PosteriaApiPosterSource::params()`, fix the `year` comment: keep its
      argument for why no branch is worth adding, replace the false premise that
      the endpoint ignores the year with the accurate version — it has no effect
      only while a supplied identifier resolves, and feeds the title fallback
      otherwise (Decision 2)
- [x] 1.2 In `PosteriaApiPosterSourceTest::testTitleIsStillSentAlongsideAnId()`,
      fix the assertion message, which currently states the endpoint ignores the
      year when an id is sent
- [x] 1.3 Grep the repository for any remaining "year is ignored" / "no effect"
      claim and correct or report each — the four known are listed in the design,
      but the check is the point, not the list

## 2. State it where it will be read

- [x] 2.1 Apply the delta to `poster-sources`: the paragraph on year/identifier
      independence and the scenario pinning that both are sent (Decision 1)
- [x] 2.2 Add the test that scenario implies, if the existing coverage does not
      already assert it — `testTitleIsStillSentAlongsideAnId()` looks sufficient;
      confirm rather than duplicate

## 3. Annotate the archive

- [x] 3.1 Append a dated correction note to
      `openspec/changes/archive/2026-07-30-send-tmdb-id-in-poster-search/design.md`
      stating that its "year is ignored when an identifier is supplied" claim —
      in the Context list and in Decision 3 — was wrong, giving the accurate
      version and pointing at this change. Do **not** rewrite the original prose
      (Decision 3)

## 4. Gates and docs

- [x] 4.1 `composer test`, `composer stan`, `composer cs` all pass
- [x] 4.2 Docs gate: no `README.md` or `docs/` impact — neither documents the
      service's request format, which the `poster-sources` spec forbids. State
      that rather than inventing edits
- [x] 4.3 No live verification needed: nothing about the request changes, so
      there is nothing a `:dev` run could show that the test suite does not.
      Say so explicitly when shipping rather than skipping the question
