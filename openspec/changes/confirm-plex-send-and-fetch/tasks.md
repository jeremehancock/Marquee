## 1. Generalise the shared confirmation

- [x] 1.1 In `public/assets/gallery.js`, add a `tone` field to
      `overlayComponent().confirm` (default `'danger'`) and have `askConfirm`
      fill it from the detail, keeping `'danger'` when the caller omits it, so
      `orphansPage` needs no change.
- [x] 1.2 In `templates/partials/_overlays.html.twig`, bind the confirm dialog's
      action button class from `confirm.tone` (`btn btn--danger` /
      `btn btn--accent`) instead of hardcoding the destructive class.
- [x] 1.3 In the gallery's delegated `submit` handler in `gallery.js`, read
      `data-confirm-title`, `data-confirm-label`, and `data-confirm-tone` off the
      form, falling back to `'Delete poster?'` / `'Delete'` / `'danger'` so the
      existing Delete form keeps its current wording with no template change.

## 2. Confirm the two Plex actions

- [x] 2.1 In `templates/partials/gallery_results.html.twig`, add `data-confirm`,
      `data-confirm-title` ("Send to Plex?"), `data-confirm-label`
      ("Send to Plex"), and `data-confirm-tone` (`accent`) to the send-to-plex
      form, with a message naming the poster via `caption_title` and stating that
      the artwork on Plex will be replaced and locked.
- [x] 2.2 Add the equivalent attributes to the fetch-from-plex form
      ("Fetch from Plex?" / "Fetch from Plex" / `accent`), with a message naming
      the poster and stating that Marquee's stored poster will be overwritten.
- [x] 2.3 Verify each form's existing `data-refresh` contract is untouched
      (`none` for send, `card` plus `data-category` for fetch), so a confirmed
      operation refreshes exactly as it did before.

## 3. Tests

- [x] 3.1 In `tests/Functional/GalleryTest.php`, assert the rendered send-to-plex
      form carries the confirmation attributes with the poster's caption title in
      the message.
- [x] 3.2 Assert the same for the fetch-from-plex form, and that the two messages
      differ so the actions are distinguishable.
- [x] 3.3 Assert the Delete form still renders its `data-confirm` message
      unchanged, guarding the fallback wording in the script.

## 4. Verification

- [x] 4.1 Run `composer test`, `composer stan`, and `composer cs`; all three must
      pass.
- [ ] 4.2 Manually check on a pointer device: Send and Fetch each open the
      dialog with their own heading and a non-red confirm button; Cancel,
      backdrop click, and Escape each leave the poster and Plex untouched; only
      confirming runs the operation and reports it.
- [ ] 4.3 Manually check on a small screen: the same two actions taken from the
      poster action tray raise the confirmation above the tray, drag-to-dismiss
      declines it, and confirming runs the operation.
- [x] 4.4 Check whether `README.md`, `docs/`, or `CLAUDE.md` describe the Send or
      Fetch flow; update in the same commit, or state explicitly that nothing
      user-facing is documented there.
