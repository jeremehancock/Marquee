## 1. Implementation

- [x] 1.1 In `src/Poster/Wall/PosterWallService.php`, replace the
  `PosterCategory::all()` loop in `randomPosters()` with an allow-list of the
  work categories (`Movies`, `TvShows`), declared as a private constant on the
  service rather than as a method on `PosterCategory`.
- [x] 1.2 Document on the allow-list why the enum is not iterated — a season is
  part of a work and a collection is a set of works — so a future reader does
  not restore `PosterCategory::all()` as a fix.

## 2. Tests

- [x] 2.1 In `tests/Unit/Poster/PosterWallServiceTest.php`, extend `setUp()` and
  `seed()` to create `tv-seasons` and `collections` directories with poster
  fixtures. Neither exists today, so the current test passes against both the
  old and new behavior.
- [x] 2.2 Assert that a season poster is never returned, and that a collection
  poster is never returned.
- [x] 2.3 Assert that movie and TV show posters are still returned.
- [x] 2.4 Add a test that a library containing only season and collection
  posters returns no posters.
- [x] 2.5 Update `testReturnsAllWhenCountExceedsLibrary` — its count assertion
  is now the work total, not every file on disk.
- [x] 2.6 Check `tests/Functional/PosterWallTest.php` for any assertion that
  depends on seasons or collections reaching the batch endpoint, and update it
  if so.

## 3. Specs and docs

- [x] 3.1 Update the `## Purpose` paragraph in
  `openspec/specs/poster-wall/spec.md` — it says posters are "drawn at random
  from every category". Purpose is not a requirement, so no delta covers it and
  archiving will not fix it.
- [x] 3.2 Confirm no documentation change is needed: `README.md` describes the
  wall without enumerating categories. Record the check rather than inventing an
  edit.

## 4. Gates

- [x] 4.1 Run `composer test`, `composer stan`, and `composer cs`; all three must
  pass before committing.
- [x] 4.2 Validate the change: `openspec validate limit-wall-to-works --strict`.

## 5. Validation on the `:dev` image

- [ ] 5.1 Open the Poster Wall against a library that has seasons and
  collections imported, and confirm the rotation shows only movie and show
  posters. Expect a visibly smaller pool — that is the intended outcome.
- [ ] 5.2 Confirm the now-playing takeover is unchanged: start a TV episode and
  verify the wall shows the show's poster with its overlays intact.
- [ ] 5.3 Confirm seasons and collections are still listed and editable in the
  gallery.
- [ ] 5.4 After archiving, re-read `openspec/specs/poster-wall/spec.md` and
  confirm the merged Purpose and requirements all describe the works-only wall.
