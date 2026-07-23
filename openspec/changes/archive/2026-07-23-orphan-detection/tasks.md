## 1. Detection

- [x] 1.1 `PlexItemRepository::distinctMediaTypes()` and `deleteByRatingKey()`.
- [x] 1.2 `Plex\Orphan\OrphanService::findOrphans()` (bounded Plex scan + compare).
- [x] 1.3 `Plex\Orphan\OrphanService::deleteAll()`.

## 2. HTTP & UI

- [x] 2.1 `Controller\OrphanController` (show + delete-all); routes `/orphans`, `/orphans/delete-all`.
- [x] 2.2 `templates/orphans.html.twig`; link from the gallery toolbar.

## 3. Verify

- [x] 3.1 Unit: orphan detection (mapped-but-gone is an orphan, present is not,
      manual upload is not), delete-all removes files + mappings, unconfigured throws.
- [x] 3.2 Functional: `/orphans` lists orphans; delete-all clears them.
- [x] 3.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 3.4 `openspec validate orphan-detection` passes.
