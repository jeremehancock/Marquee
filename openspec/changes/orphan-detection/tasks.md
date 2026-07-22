## 1. Detection

- [ ] 1.1 `PlexItemRepository::distinctMediaTypes()` and `deleteByRatingKey()`.
- [ ] 1.2 `Plex\Orphan\OrphanService::findOrphans()` (bounded Plex scan + compare).
- [ ] 1.3 `Plex\Orphan\OrphanService::deleteAll()`.

## 2. HTTP & UI

- [ ] 2.1 `Controller\OrphanController` (show + delete-all); routes `/orphans`, `/orphans/delete-all`.
- [ ] 2.2 `templates/orphans.html.twig`; link from the gallery toolbar.

## 3. Verify

- [ ] 3.1 Unit: orphan detection (mapped-but-gone is an orphan, present is not,
      manual upload is not), delete-all removes files + mappings, unconfigured throws.
- [ ] 3.2 Functional: `/orphans` lists orphans; delete-all clears them.
- [ ] 3.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 3.4 `openspec validate orphan-detection` passes.
