## 1. Give mobile modals the real tray skeleton

- [x] 1.1 Add the tray skeleton (grab handle element, head, scrolling body) to the
  Change Poster modal in `templates/gallery.html.twig`, keeping the tabs, the
  three tab panes, and the Find Posters grid inside the scrolling body.
- [x] 1.2 Add the same skeleton to the shared confirmation modal in
  `templates/partials/_overlays.html.twig`.
- [x] 1.3 Add the same skeleton to the delete-all confirmation modal in
  `templates/orphans.html.twig`.
- [x] 1.4 In the `@media (max-width: 640px)` block of `public/assets/app.css`,
  restyle the modals through the sheet rules: panel becomes `overflow: hidden`,
  the new body becomes the scroller, and the grab handle and head carry
  `touch-action: none`. Remove the `.modal__panel::before` pseudo-element handle
  it replaces.
- [x] 1.5 Verify the desktop presentation above 640px is unchanged — centred
  dialog, visible `×`, no grab handle, no separate scrolling body.
- [x] 1.6 Confirm the drag handler in `public/assets/gallery.js` needs no change
  now that a real `.sheet__grip`/`.sheet__head` exists on these overlays. If the
  `e.target.classList.contains('modal__panel')` fallback at the top of the
  gesture block is now dead, remove it and its explanatory comment.

## 2. Keep the backdrop a usable target

- [x] 2.1 Bring the mobile modal panel height to parity with a sheet (`85vh`
  rather than `90vh`) so every tray leaves the same expanse of backdrop above it.
- [x] 2.2 Re-size `.find-grid` against the smaller panel so the Find Posters
  results still fit without pushing the tray taller, and check it on the smallest
  supported viewport with a full set of results.
- [x] 2.3 Leave `.modal__close { display: none }` in place on small screens — no
  tray gains a close button — and confirm the desktop `×` is unaffected.
- [x] 2.4 Confirm backdrop-tap and Escape still dismiss each of the seven trays
  (poster actions, menu, sort, import, orphans, Change Poster, confirmations).

## 3. Contain scrolling inside trays

- [x] 3.1 Add `overscroll-behavior: contain` to the tray scrolling body.
- [x] 3.2 Add `overscroll-behavior: contain` to `.find-grid`, which is a scroller
  nested inside the Change Poster tray.
- [x] 3.3 Audit `public/assets/app.css` for any other `overflow-y: auto` region
  that can appear inside an overlay and contain it too.

## 4. Layer dialogs above trays

- [x] 4.1 Put the tray and dialog presentations on one ordered z-index scale so a
  confirmation always renders above an open tray, replacing the current
  `.sheet: 55` / `.modal: 50` inversion.
- [x] 4.2 Verify the fullscreen viewer still sits above both.
- [x] 4.3 Verify a confirmation raised from inside the orphans tray — whose markup
  `_loadTray` injects via `innerHTML` — is displayed above that tray.

## 5. Lock the page behind an open overlay (droppable — see design.md)

- [x] 5.1 Add a scope-agnostic observer in `public/assets/gallery.js` that watches
  the inline `display` `x-show` writes on `.sheet, .modal, .viewer` and toggles a
  lock class on `<html>` when any is visible.
- [x] 5.2 Implement the lock as `position: fixed` with the captured scroll offset,
  restoring the exact position on unlock — `overflow: hidden` and document-level
  `overscroll-behavior` are unreliable on iOS Safari.
- [ ] 5.3 Verify the restore is exact by opening and closing a tray partway down a
  long gallery and confirming the infinite-scroll sentinel did not append posters.
- [ ] 5.4 Test the iOS on-screen keyboard case: focus the Change Poster URL field,
  dismiss the keyboard, close the tray, and confirm the page is not left offset.
- [ ] 5.5 If 5.4 cannot be made reliable, revert this task group and remove the
  "The page behind an open overlay does not scroll" requirement from the delta
  spec, leaving groups 1–4 intact.

## 6. Tests

- [x] 6.1 Extend the gallery and orphans functional tests to assert each overlay
  renders the tray skeleton (handle, head, scrolling body), and that no close
  button is introduced into the tray head.
- [x] 6.2 Add a `tests/Unit/Asset` test, following
  `GalleryLoadingIndicationTest`, asserting the stylesheet keeps `touch-action:
  none` on the tray drag region, `overscroll-behavior: contain` on tray
  scrollers, and dialogs layered above trays.
- [x] 6.3 If task group 5 is kept, assert the lock observer and the exact scroll
  restore are present in `gallery.js`.
- [x] 6.4 Run `composer test`, `composer stan`, and `composer cs` and fix any
  failures.

## 7. Documentation

- [x] 7.1 Check whether `README.md`, `docs/`, or `CLAUDE.md` describe tray or
  modal behaviour that this change makes stale, and update in the same commit.
  If nothing user-facing changed, say so explicitly rather than inventing edits.

## 8. Device validation

- [x] 8.1 Build the image and smoke-test `/health` per `docs/docker.md`.
- [ ] 8.2 On a real iOS device: open Change Poster from the poster action tray and
  confirm it closes both by dragging its handle and by tapping the backdrop, on
  all three tabs including Find Posters with results loaded.
- [ ] 8.3 On a real Android device: confirm a downward drag on a tray handle never
  triggers pull-to-refresh with the gallery scrolled to the top.
- [ ] 8.4 Confirm scrolling the Find Posters grid to its end does not scroll the
  gallery behind it.
