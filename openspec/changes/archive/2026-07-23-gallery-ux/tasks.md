## 1. Backend

- [x] 1.1 `GalleryController::show` records the viewed category in the session.
- [x] 1.2 `OrphanController::show` + `PlexImportController::show` read it into a
      `back_url` for the back-to-library link (default category when unset).

## 2. Frontend — presentation

- [x] 2.1 Move the title to a `.card__caption` below the poster; overlay holds
      actions only.
- [x] 2.2 Larger grid columns so the overlay action stack fits.
- [x] 2.3 Lazy-load shimmer + fade-in on image load.
- [x] 2.4 Always show Send/Fetch to Plex for linked posters.
- [x] 2.5 Poster Wall opens in a new tab.

## 3. Frontend — interactions

- [x] 3.1 `gallery.js`: live search (debounced, auto-clear) + AJAX pagination.
- [x] 3.2 `gallery.js`: AJAX poster mutations refreshing only the grid + toast.
- [x] 3.3 Confirm modals for delete (gallery) and delete-all (orphans).
- [x] 3.4 Found-poster results: choose or open full screen.

## 4. Verify

- [x] 4.1 Functional: remembered section produces the right back link.
- [x] 4.2 Gallery renders caption + data-attributes; graceful no-JS fallback intact.
- [x] 4.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 4.4 `openspec validate gallery-ux --strict` passes.
