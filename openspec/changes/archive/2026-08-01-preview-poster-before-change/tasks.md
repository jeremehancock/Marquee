## 1. Lift the preview out of Find Posters

- [x] 1.1 In `public/assets/gallery.js`, add a `preview` object to `galleryUI`
      alongside `finder` — `{ open, src, loaded, confirming, applying, source,
      file }` — and reduce `finder` to `{ loading, error, notice, results }` in
      every literal that rebuilds it (`galleryUI` init, `openChange`,
      `findPosters` x2, its `.catch`), updating the comment that warns about
      repeating `applying`.
- [x] 1.2 Replace `openFinderPreview` / `closeFinderPreview` with
      `openPreview(src, source, file)` / `closePreview()`, keeping the existing
      "clear `loaded` before the src" behavior; `closePreview` revokes an object
      URL held for an upload preview, and `openPreview` revokes any previous one
      before replacing it.
- [x] 1.3 Rename `applyFinderSelection` to `applyPreview` and build its request
      from `preview.source`: `filename` + `url` posted to `/change/url` for
      `find` and `url`, `filename` + `poster` (the captured `File`) posted to
      `/change/upload` for `upload`. Leave the response handling (`!r.ok`
      throw, `posterStored`, `refreshCard` / `gallery:refresh`, the flash toast,
      closing the preview and the dialog, clearing `applying` in `finally`)
      unchanged.
- [x] 1.4 Add `openUploadPreview()` and `openUrlPreview()`, reading the file
      from the `changeFile` ref (object URL, capture the `File`) and the URL
      from the `changeUrl` ref, each calling `openPreview` with its source.

## 2. Rework the change dialog markup

- [x] 2.1 In `templates/gallery.html.twig`, relabel the tab submit controls for
      how each supplies its image: "Upload poster" and "Fetch poster".
- [x] 2.2 Convert both forms to `@submit.prevent` handlers that open the
      preview, and strip `js-mutate`, `data-refresh`, `:data-category` and all
      four `data-confirm*` attributes from them; keep the form elements, the
      `required` / `type="url"` constraints, the hidden `filename`, and the
      `x-ref`s. Rewrite the comment block above them to describe the preview
      path.
- [x] 2.3 Point the preview overlay at the shared state (`preview.open`,
      `preview.src`, `preview.loaded`, `preview.confirming`, `preview.applying`)
      and `closePreview()` / `applyPreview()`, keeping the ask line short and
      static ("Change the poster to this one?") so a long title cannot wrap it
      and shift the image above.
- [x] 2.4 Extend the change dialog's Escape guard to
      `if (!confirm.open && !preview.open) change.open = false`, and update the
      comment above it to say what the guard now protects.

## 3. Clean up what the preview replaced

- [x] 3.1 Rename `.viewer--finder` to `.viewer--preview` in
      `public/assets/app.css` and the template, and reword its comment for the
      three sources it now serves.
- [x] 3.2 Confirm nothing else in `gallery.js` still references the removed
      `finder.preview` / `finder.applying` fields or the old handler names, and
      that `submitForm`'s `data-refresh="card"` path is still reached by Fetch
      from Plex.
- [x] 3.3 Re-read the delegated `submit` handler and the shared confirm dialog
      to confirm Send, Fetch and Delete are untouched by 2.2.

## 4. Tests

- [x] 4.1 Rewrite `testChangePosterTabsConfirmBeforeTheyReplace` in
      `tests/Functional/GalleryTest.php` as a preview test: both tabs read
      "Upload poster", neither form carries `data-confirm*` / `js-mutate`, the
      preview's confirm still reads "Change poster" and binds the title, and
      "Update poster" is still absent. Keep the "Image URL" label and no-Mediux
      assertions and the `x-ref` assertions that guard the input clearing.
- [x] 4.2 Update the Escape-guard assertion to the new expression, with a
      comment covering both the confirm dialog and the preview.
- [x] 4.3 Adjust the "Change poster" button count and the
      `data-confirm-tone="danger"` / `confirmMessages()` expectations to the new
      markup, and check the placeholder/`is-loaded` binding test still matches
      the renamed preview overlay.
- [x] 4.4 Assert the preview overlay is not Find-Posters-scoped: it is bound to
      `preview.*` and reachable from all three tabs.

## 5. Gates and docs

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      green before any commit.
- [x] 5.2 Check `README.md` and `docs/testing.md` against the new flow (they
      describe Change poster by its three sources, which still holds) and state
      explicitly whether an edit was needed.
- [ ] 5.3 Verify by hand in the running app: pick a file and preview it, paste a
      URL and preview it, dismiss each with Escape / backdrop / Close and see
      the input survive, confirm one of each and see the card update, and check
      Find Posters still behaves as before.
