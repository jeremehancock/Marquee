## 1. Logo assets

- [x] 1.1 Copy `tmdb.svg`, `tvdb.png` and `fanart.png` from
      `/home/jereme/github/Posteria.app/images/` into a new
      `public/assets/providers/` directory. Do not copy `mediux.svg`.
- [x] 1.2 Record each file's intrinsic dimensions (TMDB 273×36 viewBox,
      TVDB 400×216, fanart.tv 303×61) — they are needed for the `width`/`height`
      attributes in 2.1.

## 2. Attribution markup

- [x] 2.1 Create `templates/partials/_attribution.html.twig`: a
      `.attribution` block containing the `Posters provided by:` label and three
      links — themoviedb.org, thetvdb.com, fanart.tv — each `target="_blank"`
      `rel="noopener"`, with a `title`, an `<img>` carrying `alt` text, intrinsic
      `width`/`height`, a per-provider class, and an `asset()`-wrapped `src`.
      Comment why the partial exists (one list, two footers).
- [x] 2.2 Include the partial in `templates/layout.html.twig`, inside `.footer`
      and above the existing product-name/version line.
- [x] 2.3 Include the partial in `templates/partials/_menu.html.twig`, inside
      `.menu__footer` and above its product-name/version line. Confirm the
      drawer's `@click="menuOpen = false"` behaviour still applies to the new
      links, so tapping a logo dismisses the tray like every other new-tab
      destination in it.

## 3. Styles

- [x] 3.1 In `public/assets/app.css`, add `.attribution` rules near the existing
      `.footer` / `.menu__footer` block: muted label above a centred flex row of
      logos with a gap, `flex-wrap: wrap`, and bottom spacing before the version
      line.
- [x] 3.2 Add per-provider height classes so the three marks read as one row
      despite their differing aspect ratios, with `width: auto`.
- [x] 3.3 Give `.attribution a` the muted-then-full opacity hover treatment and a
      `:focus-visible` outline consistent with the existing `.footer a` rules.
- [x] 3.4 Inside the existing `@media (max-width: 640px)` block, tighten the logo
      gap for the drawer. Confirm no new visibility rule is needed — `.footer`
      is already `display: none` there and `.menu__footer` is mobile-only.

## 4. Tests

- [x] 4.1 In `tests/Functional/ApplicationShellTest.php`, assert a rendered page
      contains `Posters provided by:` and links to `themoviedb.org`,
      `thetvdb.com` and `fanart.tv`.
- [x] 4.2 Assert the same page does not link to `mediux.pro`.
- [x] 4.3 Assert every provider logo `src` is a local `/assets/` path, so no
      logo is fetched from a third-party host.
- [x] 4.4 Assert the page still renders the product name, version and the
      `getmarquee.now` link, so attribution was added alongside rather than in
      place of the existing footer content.

## 5. Verification

- [x] 5.1 Run `composer test`, `composer stan` and `composer cs` — all three must
      pass.
- [x] 5.2 Check the change in a browser at desktop width (logos in the page
      footer, above the version line) and at ≤640px (page footer gone, logos in
      the drawer footer, drawer body still scrolls).
- [x] 5.3 Check the docs: `README.md`, `docs/` and `CLAUDE.md`. Nothing
      user-configurable changed, so state explicitly if no edit is warranted
      rather than inventing one.
