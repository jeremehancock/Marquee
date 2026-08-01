## 1. Dialog labels

- [x] 1.1 In `templates/gallery.html.twig`, change the Upload form's submit
      button text from "Update poster" to "Change poster".
- [x] 1.2 In the same file, change the From URL form's submit button text from
      "Update poster" to "Change poster".
- [x] 1.3 Change the `change-url` field label from "Image URL (also supports
      Mediux URLs)" to "Image URL".

## 2. Confirmation

- [x] 2.1 On the Upload form, add `:data-confirm` bound to
      `'Replace the poster for “' + change.title + '” with the selected image?'`,
      plus `data-confirm-title="Change poster?"`,
      `data-confirm-label="Change poster"`, and `data-confirm-tone="accent"`.
- [x] 2.2 On the From URL form, add the same three static attributes and a
      `:data-confirm` whose source phrase reads "with the image at that URL?".
- [x] 2.3 Guard the change dialog's Escape handler on the confirmation being
      closed (`@keydown.escape.window="if (!confirm.open) change.open = false"`)
      so dismissing the confirmation unwinds one layer and keeps the user's
      input.

## 3. Reopening the dialog

- [x] 3.1 Give the file and URL inputs `x-ref="changeFile"` / `x-ref="changeUrl"`
      in `templates/gallery.html.twig`.
- [x] 3.2 In `openChange()`, clear both refs' values after rebuilding `change`
      and `finder`, so every dismissal path opens clean. Do not use
      `form.reset()` — it would blank the hidden filename.
- [x] 3.3 Assert in `tests/Functional/GalleryTest.php` that both inputs carry
      their refs, so the reset cannot silently stop finding them.

## 4. Declining leaves the raising tray open

- [x] 4.1 In `public/assets/gallery.js`, move the `gallery:sheet-close` dispatch
      in the delegated submit handler past the `data-confirm` branch, and add it
      to the `gallery:confirmed` handler so the tray closes only when the action
      actually runs.
- [x] 4.2 Make the same move in `orphansPage`'s own submit handler, which carries
      a second copy of that logic.
- [x] 4.3 Guard the action tray's Escape handler in
      `templates/partials/_overlays.html.twig` on the confirmation being closed,
      matching the change dialog.
- [x] 4.4 Assert the guarded tray handler in `tests/Functional/GalleryTest.php`.

## 5. Manual verification

- [ ] 5.1 Desktop: on each tab, submit and confirm — the poster changes, the
      card updates in place, the dialog closes, and the toast reports the result.
- [ ] 5.2 Desktop: on each tab, submit and cancel (button, backdrop, Escape,
      close control) — nothing is requested, the change dialog is still open on
      the same tab, and the chosen file / typed URL is still there.
- [ ] 5.3 Phone width: confirm that the confirmation is presented as a tray over
      the change tray, matching Send to Plex, and that both dismiss cleanly with
      no stuck scroll lock.
- [ ] 5.4 Confirm Find Posters still applies through its own inline confirm step,
      and Send/Fetch/Delete confirmations are unchanged.
- [ ] 5.5 Pick a file and type a URL, dismiss the dialog without changing the
      poster, then reopen it — for the same poster and for a different one. Both
      fields are empty, the Upload tab is active, and the change still applies to
      the poster that was opened.
- [ ] 5.6 Phone width: from a poster's action tray choose Delete, then Send to
      Plex, then Fetch from Plex, cancelling each — the action tray is still open
      every time. Confirm one and check the tray closes. Repeat for Delete inside
      the orphans tray.

## 6. Tests, gates, docs

- [x] 6.1 In `tests/Functional/GalleryTest.php`, assert the rendered gallery
      contains "Change poster" as both submit labels, the plain "Image URL"
      label, and no "Mediux" text.
- [x] 6.2 In the same test file, assert both change forms carry
      `data-confirm-title`, `data-confirm-label`, and `data-confirm-tone`, and
      that the URL form carries the bound `:data-confirm` attribute.
- [x] 6.3 Run `composer test`, `composer stan`, and `composer cs`; fix anything
      they report.
- [x] 6.4 Confirm `README.md`, `docs/`, and `CLAUDE.md` describe none of these
      labels and need no edit; state that explicitly in the commit rather than
      inventing doc changes.
