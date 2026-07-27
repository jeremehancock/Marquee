## 1. Backend: single-orphan delete

- [x] 1.1 Add `OrphanService::delete(PosterCategory $category, string $filename): bool` that resolves the record via `PlexItemRepository::findByFilename()`, verifies it is a true orphan (its rating key absent from Plex), and deletes the poster file (`PosterStorage::delete`) and mapping (`deleteByRatingKey`); returns false (no-op) when the target is not an orphan.
- [x] 1.2 Add `OrphanController::delete` action: read `category` + `filename` from the parsed body, resolve the category (404 on invalid), call `OrphanService::delete`, add a success/error flash, and redirect 302 to `/orphans`.
- [x] 1.3 Register `POST /orphans/delete` → `[OrphanController::class, 'delete']` in `src/Routes.php`.

## 2. Frontend: shared overlays

- [x] 2.1 Extract the reusable overlay state/methods (`sheet`, `confirm`, `toast`, `view`, `openSheet`, `closeSheet`, `askConfirm`, `doConfirm`, `notify`) from `galleryUI` into a shared factory in `public/assets/gallery.js`, and spread it into both `galleryUI` and `orphansPage` so both expose the same shape.
- [x] 2.2 Move the action-tray, confirm, and toast markup into a shared Twig partial (e.g. `templates/partials/_overlays.html.twig`); include it from `templates/gallery.html.twig` in place of the inline markup.
- [x] 2.3 Include the shared overlay partial in `templates/orphans.html.twig` and add the matching `@gallery:*` window bindings to the orphans root so the tray/confirm/toast respond there too.

## 3. Frontend: per-orphan cards and interactions

- [x] 3.1 In `templates/orphans/_results.html.twig`, render each orphan card with the `.card__frame > .card__overlay > .card__actions` structure containing a Download link (`href="/posters/{category}/{filename}" download`) and a `js-mutate` Delete form posting to `/orphans/delete` with hidden `category` + `filename` and a `data-confirm` message.
- [x] 3.2 In `gallery.js`, generalize the card-frame tap handler and the `js-mutate` submit/confirm handler so they also bind on the orphans root (open the tray on touch, run the confirm flow on delete).
- [x] 3.3 Wire single-orphan delete to refresh in place: on a successful `/orphans/delete` POST, re-fetch `GET /orphans/list`, re-run the fragment wiring (image fade-in, delete-all button, `count`), and show a toast — no full page reload.

## 4. Verification

- [x] 4.1 Add/extend `tests/Unit/Plex/OrphanServiceTest.php`: deleting a single orphan removes only that file + mapping; a non-orphan (present in Plex) request is a no-op and preserves the poster.
- [x] 4.2 Add/extend `tests/Functional/OrphanTest.php`: `POST /orphans/delete` deletes one orphan and redirects with a success flash; an invalid category 404s; a non-orphan filename leaves the poster in place.
- [x] 4.3 Run `composer` checks (PHP-CS-Fixer, PHPStan max, PHPUnit) and fix any failures.
- [x] 4.4 Manual pass: desktop hover overlay shows Download/Delete on an orphan card; mobile tap opens the tray; deleting one orphan removes its card, updates the count, toasts, and leaves other orphans and the "Delete all" control intact; verify the library page's overlay/tray/confirm/toast still work after the refactor.
