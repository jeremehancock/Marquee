## 1. Service & HTTP

- [ ] 1.1 `Poster\Wall\PosterWallService::randomPosters(int $count)`.
- [ ] 1.2 `Controller\PosterWallController` (page + JSON batch); routes `/wall`, `/wall/posters`.

## 2. UI

- [ ] 2.1 `templates/wall.html.twig` (standalone full-screen page).
- [ ] 2.2 `public/assets/wall.css` and `public/assets/wall.js` (queue, preload, cross-fade, refill).
- [ ] 2.3 Link to the wall from the gallery toolbar.

## 3. Verify

- [ ] 3.1 Unit: random posters span categories, respect the count, empty library returns none.
- [ ] 3.2 Functional: `/wall` renders; `/wall/posters` returns JSON poster URLs; auth is required.
- [ ] 3.3 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 3.4 `openspec validate poster-wall` passes.
