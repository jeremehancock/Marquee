## 1. Repository: delete mappings by file

- [x] 1.1 Add `deleteByCategoryAndFilename(string $category, string $filename): void` to `src/Database/PlexItemRepository.php` that deletes all `plex_items` rows matching the pair.

## 2. Symmetric gallery delete

- [x] 2.1 Update `PosterLibrary::delete()` in `src/Poster/PosterLibrary.php` to clear the mapping via `deleteByCategoryAndFilename` after the file is removed, only on successful file deletion; inject `PlexItemRepository` if not already available.
- [x] 2.2 Verify `src/Controller/PosterController.php` still calls the same `PosterLibrary::delete` path and needs no behavior change beyond what 2.1 provides.
- [x] 2.3 Confirm wiring/DI in `src/bootstrap.php` (or the relevant container config) provides the repository to `PosterLibrary`. (PHP-DI autowires `PosterLibrary`/`PlexItemRepository`; no explicit definition needed.)

## 3. Self-healing orphan detection

- [x] 3.1 In `src/Plex/Orphan/OrphanService.php` `findOrphans()`, prune mappings whose file is missing (via `deleteByRatingKey`) instead of silently skipping, and collapse multiple mappings that resolve to the same existing file into a single orphan entry — never orphaning a file that a live mapping still backs.

## 4. Tests

- [x] 4.1 Add a unit test in `tests/Unit/Plex/OrphanServiceTest.php`: a record whose file is missing is pruned and not returned as an orphan; records with existing files are untouched.
- [x] 4.2 Add coverage for `PosterLibrary::delete` clearing the mapping (file removed → matching `plex_items` rows deleted), including the duplicate case where two rows share one filename.
- [x] 4.3 Add/extend a functional test in `tests/Functional/OrphanTest.php` reproducing the end-to-end scenario: import → regular delete → recreate (new rating key) → re-import → delete from Plex → Orphans page shows a single entry, not two.

## 5. Verify

- [x] 5.1 Run the test suite, PHPStan (max level), and PHP-CS-Fixer; ensure all pass.
- [x] 5.2 Run `openspec validate --change fix-duplicate-orphans` and confirm it passes.
