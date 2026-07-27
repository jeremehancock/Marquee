## 1. Badge palette

- [x] 1.1 In [public/assets/app.css](public/assets/app.css) retint the four
      `.card__badge--movies|tv-shows|tv-seasons|collections` fills to the muted,
      on-theme hues from the design (gold / teal / blue / violet), keeping white
      text and the existing subtle border and hover/touch behavior.
- [ ] 1.2 Visually confirm the four badges are mutually distinguishable and stay
      legible over both light and dark poster art in the All view. _(needs a
      running instance with imported posters — for the user to confirm.)_

## 2. Overlay action-button hover/focus states

- [x] 2.1 Add `:hover` and `:focus-visible` rules scoped to `.card__actions .btn`
      for the base button (brighten toward accent), `.btn--accent` (deepen to
      `--accent-dim`), and `.btn--danger` (tint background + stronger border),
      with a short transition, per the design.
- [ ] 2.2 Verify each overlay action shows a clear hover state on pointer devices
      and a visible keyboard-focus state when tabbing through the overlay.
      _(needs a running instance — for the user to confirm.)_

## 3. Caption drops the baked-in library (and sheet keeps it in parens)

- [x] 3.1 Add `PlexItemRepository::librariesForCategory()` returning
      `filename => library_title`, and build a `plex_libraries` map per category in
      [GalleryController](src/Controller/GalleryController.php), passed to the template.
- [x] 3.2 In [src/Poster/Poster.php](src/Poster/Poster.php) make
      `captionTitle(?string $library)` normalise the library name the way the
      filename was and strip it only as the exact trailing token; add
      `sheetTitle(?string $library)` that instead rewrites the token to ` (Library)`.
      Leave `title()` unchanged.
- [x] 3.3 In [gallery_results.html.twig](templates/partials/gallery_results.html.twig)
      resolve each poster's library, render `poster.captionTitle(lib)` as the caption
      text and `data-tooltip`, and emit `data-sheet-title="{{ poster.sheetTitle(lib) }}"`.
- [x] 3.4 In [gallery.js](public/assets/gallery.js) make the mobile sheet prefer
      `data-sheet-title` (library in parentheses), falling back to the caption text.
- [x] 3.5 Unit-test `captionTitle()`/`sheetTitle()`: single- and multi-word library
      tokens, no-library (full title kept), and a library word that is not the
      trailing token.

## 4. Import screen presentation

- [x] 4.1 In [templates/plex.html.twig](templates/plex.html.twig) wrap the
      library type label in parentheses, e.g. `({{ library.isMovie ? 'Movies' : 'TV' }})`.
- [x] 4.2 In [public/assets/app.css](public/assets/app.css) center the Step 1
      pill label text via flex centering on `.choice span`
      (`display:flex; align-items:center; justify-content:center; text-align:center;`).

## 5. Single-line caption with ellipsis

- [x] 5.1 In [public/assets/app.css](public/assets/app.css) change `.card__caption`
      from the two-line `-webkit-line-clamp` to a single-line ellipsis
      (`white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%`)
      so a long caption truncates instead of wrapping and never widens past the card.

## 6. Shared custom tooltip

- [x] 6.1 Add a `.tooltip` component to [public/assets/app.css](public/assets/app.css)
      styled from theme tokens (surface bg, border, ink text, shadow, small font),
      hidden by default with an opacity transition and `pointer-events:none`.
- [x] 6.2 Add a delegated tooltip controller to [public/assets/app.js](public/assets/app.js):
      one shared `body`-level element shown on `pointerover`/`focusin` for
      `[data-tooltip]` targets (ignoring touch), positioned above the target with
      below-flip and horizontal viewport clamping, hidden on
      `pointerout`/`focusout`/`scroll`/`Escape`.
- [x] 6.3 Convert native `title=` tooltips to `data-tooltip=` in
      [gallery_results.html.twig](templates/partials/gallery_results.html.twig)
      (caption + the four pagination arrows),
      [orphans/_results.html.twig](templates/orphans/_results.html.twig) (caption),
      and [gallery.html.twig](templates/gallery.html.twig) (preview image). The
      caption's `data-tooltip` uses the library-stripped title.

## 7. Poster Wall shows only posters

- [x] 7.1 Remove the exit control from [wall.html.twig](templates/wall.html.twig)
      and delete the `.wall__exit` (and hover) rules from
      [public/assets/wall.css](public/assets/wall.css); the wall keeps loading no
      tooltip assets since it now has no tooltip.

## 8. Verification

- [x] 8.1 Run `composer` checks (PHP-CS-Fixer, PHPStan, PHPUnit) and confirm they
      pass. _(178 tests pass; PHPStan clean; CS clean.)_
- [ ] 8.2 Load the All view, import screen, orphans, Find Posters, and the wall on
      a pointer and a touch viewport: captions truncate and drop the library, the
      tooltip matches, the mobile sheet shows the library in parentheses, and the
      wall has no exit control.
