## 1. Storage: a rename that cannot clobber

- [x] 1.1 Add `rename(PosterCategory $category, string $currentFilename, string $desiredFilename): string` to `App\Poster\PosterStorage`, documented as returning the filename actually used, which may differ from the one requested.
- [x] 1.2 Implement it in `FilesystemPosterStorage`: reject unsafe current/desired names, sanitise the desired name, resolve collisions through the existing `uniqueFilename()` helper, move the file, and return the settled name.
- [x] 1.3 Make a rename to the name the file already has a no-op that returns that name, so an unchanged name never produces a `-1` suffix.
- [x] 1.4 Handle the case-only rename on a case-insensitive filesystem: a collision with the file being renamed is not a collision. Verify the target resolves to a different file before suffixing.
- [x] 1.5 Update any other `PosterStorage` implementations or test doubles to satisfy the widened interface. (`FilesystemPosterStorage` is the only implementation; no test doubles exist.)

## 2. Import: reconcile recorded facts

- [x] 2.1 Rename `ImportService::backfillMissingFacts()` to reflect that it now reconciles rather than backfills, and rewrite its docblock: the null-only rule is gone, the zero-writes-in-steady-state rule stays. (Now `reconcileFacts()`.)
- [x] 2.2 Change the merge rule per fact to: take Plex's value when it differs and Plex reports one; keep the recorded value when Plex reports nothing; write nothing when they match. Apply it to `title`, `year` and `tmdbId`.
- [x] 2.3 Keep the early return when nothing differs, so an unchanged library still performs zero writes.
- [x] 2.4 Move the call so it runs for every item that has an existing mapping, ahead of the branch that decides whether to download — not only inside the skip branch. (Achieved differently: the fact merge is *shared* by both branches via `mergedTitle()` and `?? $existing?->…`, so one rule governs both without either running ahead of the branch. Hoisting the work above the branch is what caused the defect in group 5 — see 5.1.)
- [x] 2.5 Verify the download branch no longer double-writes: the reconciliation and the post-download `upsert` must not both fire for one item. (`reconcileFacts()` is called only on the skip path; the download path carries the same merge rule in its single existing upsert.)

## 3. Import: keep the filename in step with the item

- [x] 3.1 Derive the name the item would be given today from `deriveFilename()`'s title/year/library rule, reusing the stored file's existing extension rather than inspecting bytes, so the skip path can compute it without a download. (Extracted `deriveBaseName()`, shared by first import and rename.)
- [x] 3.2 When the derived name differs from the stored one, call `PosterStorage::rename()` and carry the returned filename into the same upsert that writes the corrected facts, so the mapping is never briefly pointing at a path that does not exist. (Tightened in 5.2: the rename must be the *last* step before that upsert, with nothing that can fail in between.)
- [x] 3.3 Skip the rename entirely when the derived name matches the stored one. (Decided inside the storage, which owns sanitisation — the derived name is raw and only its sanitised form is comparable to disk.)
- [x] 3.4 Catch a failed rename, keep the existing filename in the mapping, and let the fact reconciliation still complete — a rename failure must not fail the item's import.
- [x] 3.5 Confirm a skipped item that was renamed is still counted as skipped, not imported.

## 4. Tests

- [x] 4.1 `ImportServiceTest`: a re-imported item whose title, year and TMDB id all changed has all three corrected in its mapping. (`testCorrectedMatchRenamesThePosterToTheNewTitle`)
- [x] 4.2 `ImportServiceTest`: the same correction happens when the artwork is unchanged and the download is skipped, and the item is still reported as skipped. (`testCorrectedMatchRenamesEvenWhenTheArtworkIsUnchanged`, `testSkippedItemWithAChangedTmdbIdIsCorrected`, `testSkippedItemWithAChangedYearIsCorrected`)
- [x] 4.3 `ImportServiceTest`: an item whose facts all match performs no write and no rename (assert via `FakePlexClient::$downloads` and an unchanged `updated_at`). (`testSkippedItemWithUnchangedFactsIsNotRewritten`, using a backdated sentinel rather than `sleep()`)
- [x] 4.4 `ImportServiceTest`: a recorded year or TMDB id is kept, not cleared, when Plex reports none. (`testSkippedItemKeepsRecordedFactsPlexNoLongerReports`)
- [x] 4.5 `ImportServiceTest`: a title change renames the poster file and the mapping records the new filename; the old file is gone.
- [x] 4.6 `ImportServiceTest`: the rename happens on the skip path too, and the image bytes are unchanged.
- [x] 4.7 `ImportServiceTest`: a rename whose derived name collides with an unrelated poster in the same category takes a unique name and leaves the other poster untouched. (`testRenameDoesNotOverwriteAnUnrelatedPoster`)
- [x] 4.8 `FilesystemPosterStorageTest`: `rename()` rejects unsafe names, is a no-op for an unchanged name, and handles a case-only rename without suffixing.
- [x] 4.9 End-to-end regression for the reported bug: import a show under a wrong title, re-import it under the corrected title, then assert the gallery sorts it under the new title and a search for the new title finds it. This is the assertion that would have caught the original report. (`testACorrectedMatchIsSortedAndFoundUnderItsNewTitle`; verified load-bearing by disabling the rename — it and 4.5/4.6 fail without the fix.)
- [x] 4.10 Confirm the existing skip-unchanged and force-re-import tests still pass unmodified. (They do. Two *other* tests — `testSkippedItemWithAnExistingTmdbIdIsNotRewritten` and `…YearIsNotRewritten` — asserted the null-only rule this change replaces, and were rewritten into 4.3/4.4 above.)

## 5. Fix: the rename must be the last step before the mapping is written

Found on `:dev`. A show whose poster had been customised and sent to Plex came
back captioned "The Shield TV Shows" — the filename-derived fallback, meaning the
gallery found no mapping for that file.

`ChangePosterService` replaces a poster in place without updating the recorded
`thumb`, so after Change poster + Send to Plex the recorded thumb is stale and
the next import takes the *download* path, not the skip path. The rename was
running ahead of the download, so a failed fetch — routine right after a
corrected match, while Plex regenerates artwork — left the file renamed and the
mapping still on the old name. Permanently: the mapping addressed a name that no
longer existed, so no later import could reconcile it.

- [x] 5.1 Move the rename out of the pre-branch position: on the skip path it runs inside the branch, immediately before `reconcileFacts()`.
- [x] 5.2 On the download path, `replace()` through the filename the mapping still holds, and rename only after that has succeeded — so the rename is the last operation before the upsert that records its result.
- [x] 5.3 Rewrite the `renamedToMatch()` docblock to state the caller's obligation: record the returned name immediately, with nothing that can fail in between.
- [x] 5.4 `ImportServiceTest::testAFailedDownloadLeavesTheFileAndTheMappingAgreeing` — after a failed fetch the mapping still addresses a file that exists, and a later successful import still heals the name and facts. (Verified load-bearing by restoring the old ordering: fails with "the mapping must address a file that exists".)
- [x] 5.5 Add the ordering constraint to the `Safe, unique filenames` requirement and a scenario for the failed download, so the rule is specified rather than only commented.

## 6. Gates and docs

- [x] 6.1 `composer test`, `composer stan`, `composer cs` all pass.
- [x] 6.2 Check whether `README.md`, `docs/` or `CLAUDE.md` describe import as write-once or otherwise go stale; update in the same commit, or state explicitly that nothing user-facing changed. (Added a README FAQ entry for correcting a match in Plex, placed ahead of the "Can I start over?" answer — deleting `/config` was the workaround this change removes the need for. `docs/` and `CLAUDE.md` make no claims that went stale.)
