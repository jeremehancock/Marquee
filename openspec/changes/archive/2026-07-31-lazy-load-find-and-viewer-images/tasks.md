## 1. Shared loading treatment in CSS

- [x] 1.1 In `public/assets/app.css`, extend the shimmer rule (`.card__frame::before`)
      and its `:has(.is-loaded)` stop rule to also cover `.find-item__frame` and the
      viewers' placeholder, keeping the existing comments accurate for the wider set
      of callers.
- [x] 1.2 Extend the opacity/`is-loaded` fade pair to `.find-item__img` and the
      viewer images, so `is-loaded` stays the one shared "resolved" marker.
- [x] 1.3 Add `.find-item__frame` rules: move `aspect-ratio: 2 / 3`, the border and
      the radius off `.find-item__img` onto the frame, leaving the image filling it
      (`width/height: 100%`, `object-fit: cover`) with its `cursor: zoom-in`.
- [x] 1.4 Add `.viewer__stage` (`flex: 1 1 auto; min-height: 0`, centring its
      contents) and a `.viewer__placeholder` sized at poster proportions; remove the
      now-redundant `max-height: calc(100% - 132px)` on `.viewer--finder img` and
      note in the comment that the stage is what holds the bar's position.

## 2. Find Posters candidate grid

- [x] 2.1 In `templates/gallery.html.twig`, wrap each `x-for` candidate image in
      `<div class="find-item__frame">` and give the `figure.find-item` its own
      `x-data="{ loaded: false }"`.
- [x] 2.2 Bind the image: `:class="loaded ? 'is-loaded' : ''"` with
      `@load="loaded = true"` and `@error="loaded = true"`, keeping the existing
      `loading="lazy"`, `@click="openFinderPreview(...)"` and tooltip attributes
      intact.

## 3. Full-screen viewers

- [x] 3.1 In `public/assets/gallery.js`, add `viewerLoaded: false` to
      `overlayComponent()` and reset it in `view(url)` alongside `this.viewer = url`;
      comment why the reset lives in the open method (the viewer image is reused, so
      a resolved flag must not carry over to the next poster).
- [x] 3.2 In `templates/partials/_overlays.html.twig`, wrap the viewer image in
      `.viewer__stage`, add the placeholder element shown while `!viewerLoaded`, and
      bind `:class` / `@load` / `@error` on the image.
- [x] 3.3 Add `finder.previewLoaded` to the gallery's Alpine root, resetting it in
      `openFinderPreview()` (and leaving `closeFinderPreview()` to clear the preview
      as it does today).
- [x] 3.4 Apply the same stage, placeholder and bindings to the `viewer--finder`
      markup in `templates/gallery.html.twig`, leaving the action bar markup
      unchanged.

## 4. Tests

- [x] 4.1 Add `tests/Unit/Asset/LazyImagePresentationTest.php` — a shape tripwire in
      the style of `GalleryLoadingIndicationTest`, with a docblock stating what it
      does and does not prove: assert the shimmer and fade rules in `app.css` name
      the candidate-cell and viewer selectors as well as the card ones.
- [x] 4.2 Assert in the same test that `gallery.js` resets the viewer's loaded flag
      wherever the viewer's source is assigned, so a future edit cannot set the
      source without clearing the flag.
- [x] 4.3 Extend `tests/Functional/GalleryTest.php` with an assertion that the
      rendered gallery markup carries the candidate frame, the viewer stage and the
      placeholder, so the templates cannot lose them silently.

## 5. Gates and docs

- [x] 5.1 Run `composer test`, `composer stan`, `composer cs` — all three green
      before any commit.
- [x] 5.2 Check `README.md`, `docs/` and `CLAUDE.md` for staleness; this is
      presentation-only with no user-facing setting, so record explicitly that no
      docs change is needed if that is the finding.
- [x] 5.3 Validate by hand against the `:dev` image: candidate cells shimmer then
      fade; scrolling the results fetches images as they approach the visible area
      (network panel); both viewers show the placeholder then fade in; reopening on a
      second poster starts unresolved; the finder bar does not move between loading,
      loaded, and the confirm step. Throttle the connection — the states that only
      exist mid-download are the ones worth looking at.
- [x] 5.4 Raised by that validation: the placeholder was shoved sideways by the
      loading image, which claims its box as soon as its dimensions are known, well
      before it arrives. Take the placeholder out of flow, record the "nothing moves
      while waiting" requirement in both delta specs, and add a tripwire for it.
- [x] 5.5 Also raised by validation: on a phone the placeholder sat above where the
      poster landed. Both block insets are set, but the height resolves from the
      aspect ratio once max-width clamps the box, and the over-constrained result
      honours `top`. Centre it with auto block margins, verified against the real
      stylesheet in headless Chromium across five viewports.
