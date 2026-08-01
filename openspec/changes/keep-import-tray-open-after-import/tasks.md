## 1. Rewind the loaded import form

- [x] 1.1 In [gallery.js](public/assets/gallery.js), add a `_rewindImportForm`
      helper alongside `_resetImport` that reaches the cached `_importForm`'s
      Alpine component (guarded on `window.Alpine && window.Alpine.$data` and
      wrapped in `try`, as `_rescanOrphans` does) and sets `type = ''`,
      `sections = []`, and `importing = false`.
- [x] 1.2 In the same helper, clear the unbound "Re-download unchanged posters"
      checkbox directly (`input[name="force"]`), since no `x-model` reaches it —
      and do not use `form.reset()`, which `x-model` would immediately undo.
- [x] 1.3 In the same helper, scroll the tray back to the top, so the shortened
      step-1 form is what the user lands on. The scroller is the `.sheet__body`
      *around* `$refs.importBody`, not the ref itself, which is a plain `div`.
- [x] 1.4 Comment the helper with why the form is rewound in place rather than
      refetched (fetch-once form, and re-running `Alpine.initTree` re-binds the
      fragment), matching the commenting density of the surrounding tray code.

## 2. Keep the tray open after an import

- [x] 2.1 In `runImport`'s success path, drop `self.importOpen = false` and the
      `self._resetImport()` call, and call `_rewindImportForm()` instead, keeping
      the existing `notify(...)` of the parsed `.alert` and the
      `dispatch('gallery:refresh', {})`.
- [x] 2.2 Move the clearing of the form's `importing` flag out of the `finally`
      block into the two outcome paths, so success rewinds (which clears it) and
      failure clears it without touching `type`/`sections` — the current
      `finally` guard is dead on success because `_resetImport` had already
      nulled `_importForm`.
- [x] 2.3 Confirm the failure path still leaves `importOpen` true and reports
      "Import failed. Please try again." with the user's selections intact.
- [x] 2.4 Leave `closeImport`/`_resetImport` untouched, so dismissing the tray
      still discards the fragment and a reopen fetches a fresh form.

## 3. Verify nothing else moved

- [x] 3.1 Confirm no change to `openOrphans`, `_rescanOrphans`, `closeOrphans`,
      `openSheet`/`closeSheet`, the sort tray, the menu tray, or the confirm
      dialog — the other trays are working as intended.
- [x] 3.2 Confirm no template, stylesheet, route, or PHP change is needed, and
      that the standalone `/plex` page still POSTs and redirects as before.

## 4. Tests

- [x] 4.1 Add `tests/Unit/Asset/ImportTrayReuseTest.php` following the existing
      asset-tripwire pattern (`OrphansTrayRescanTest`), with a doc block
      explaining that these are shape assertions standing in for a JS test
      runner this repo does not have.
- [x] 4.2 Assert `runImport`'s success path no longer sets `importOpen = false`
      and no longer calls `_resetImport`.
- [x] 4.3 Assert `_rewindImportForm` covers all four pieces of state: `type`,
      `sections`, the `force` checkbox, and `importing`.
- [x] 4.4 Assert `closeImport` still calls `_resetImport`, so tray dismissal
      keeps discarding the loaded form.
- [x] 4.5 Confirm `OrphansTrayRescanTest::testTheImportTrayStillLoadsOnce` and
      `TrayDismissalTest` still pass unchanged.

## 5. Gates and docs

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs` — all three
      must pass.
- [x] 5.2 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness from this
      change; state explicitly if nothing user-facing needs editing. **Nothing
      needed editing:** README's mobile paragraph describes the tray *pattern*
      (menu, action sheet) without claiming anything about what a completed
      import does to its tray, `docs/testing.md` uses the import only as a step
      in live-Plex validation, and `CLAUDE.md` does not describe tray behavior.

## 6. Hand verification on the `:dev` image

- [ ] 6.1 On a phone (or a touch-emulating viewport ≤640px): open the import
      tray, run an import, and confirm the tray stays open, the progress overlay
      lifts, the result toast is readable above the tray, and the form shows only
      step 1 with nothing selected.
- [ ] 6.2 Run a second import of a different content type from the same open
      tray without reopening it, and confirm it reports its own result.
- [ ] 6.3 Confirm the tray still dismisses by drag-down, backdrop tap, and
      Escape, and that reopening it lands on a fresh step-1 form.
- [ ] 6.4 Spot-check the orphans tray, a poster's action tray, and a
      confirmation raised from it, to confirm they are unchanged.
