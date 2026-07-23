## 1. Service & HTTP

- [x] 1.1 `Poster\Wall\PosterWallService::randomPosters(int $count)`.
- [x] 1.2 `Controller\PosterWallController` (page + JSON batch); routes `/wall`, `/wall/posters`.

## 2. UI

- [x] 2.1 `templates/wall.html.twig` (standalone full-screen page).
- [x] 2.2 `public/assets/wall.css` and `public/assets/wall.js` (queue, preload, cross-fade, refill).
- [x] 2.3 Link to the wall from the gallery toolbar.

## 3. Verify

- [x] 3.1 Unit: random posters span categories, respect the count, empty library returns none.
- [x] 3.2 Functional: `/wall` renders; `/wall/posters` returns JSON poster URLs; auth is required.
- [x] 3.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [x] 3.4 `openspec validate poster-wall` passes.
