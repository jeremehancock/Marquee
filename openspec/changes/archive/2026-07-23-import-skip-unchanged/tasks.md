## 1. Backend

- [x] 1.1 Add a `thumb` column to `plex_items` (create + idempotent migration).
- [x] 1.2 Carry `thumb` on `PlexItemRecord` and through `PlexItemRepository`.
- [x] 1.3 `ImportService`: skip download when unchanged + file present; re-download
      on change, missing file, or force; store the thumb; add a `force` flag.
- [x] 1.4 `ImportResult`: track and summarise skipped posters.
- [x] 1.5 `PlexImportController`: accept the force flag; treat all-skipped as success.

## 2. Frontend

- [x] 2.1 Add a "Re-download unchanged posters" option to the import screen.

## 3. Verify

- [x] 3.1 Unit: skip unchanged, re-import on change, re-import on missing file,
      force re-import (asserting the download actually happened or not).
- [x] 3.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 3.3 `openspec validate import-skip-unchanged --strict` passes.
