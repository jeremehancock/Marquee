## 1. The trigger

- [x] 1.1 In `templates/partials/_nav_macros.html.twig`, emit `data-connect` on
  the `<a class="conn-status">` branch of `connection_status()` only — never on
  the `current == 'connect'` span, which has no href to fall back to. Note beside
  it that the attribute reaches both placements because the macro is one source
  of truth, and that the desktop placement is unaffected because the bridge gates
  on `isTouch()`.
- [x] 1.2 In `public/assets/gallery.js`, add `'data-connect': 'gallery:connect'`
  to `TRAY_LINKS` and `a[data-connect]` to the delegated click handler's
  selector. Update the comment above it to name the connection alongside Import,
  Orphans and Settings.

## 2. The tray

- [x] 2.1 In `templates/gallery.html.twig`, add the connection tray beside the
  settings tray: `.sheet` bound to `connectOpen`, `{{ tx.tray() }}`,
  `@keydown.escape.window="closeConnect()"`, a backdrop calling `closeConnect()`,
  and a `.sheet__panel.sheet__panel--tall` declaring `role="dialog"`,
  `aria-modal="true"`, `aria-label="Plex Connection"` and `tabindex="-1"`.
- [x] 2.2 Give it the shared grip, a `.sheet__head` titled "Plex Connection", and
  a `.sheet__body` carrying `x-ref="connectBody"` and `data-nested-scope`, with
  the `Loading…` line bound to `connectLoading`. Match the settings tray's shape
  exactly rather than inventing a variant.

## 3. The component

- [x] 3.1 In `galleryUI`, add `connectOpen` and `connectLoading` beside the
  settings flags. Add no `connectLoaded` flag, and comment why: the screen
  refreshes the server name from Plex and the connection can drop, so it is
  fetched on every open — the same reason the settings tray is, and the trap
  copying the import tray would walk into.
- [x] 3.2 Add `openConnect()`: set `connectOpen`, guard on `connectLoading`, call
  `_loadTray('/connect', 'connectBody')`, and on failure write the same
  alert-with-a-link fallback the import tray uses, pointing at `/connect`.
- [x] 3.3 Add `closeConnect()`: clear `connectOpen` and empty `connectBody`, so a
  reopen does not flash the previous connection state before the new fetch lands.
- [x] 3.4 Wire `gallery:connect` to `openConnect()` wherever the other three
  `gallery:*` tray events are bound, and confirm the disconnect form is left
  alone by both delegated handlers: it carries no `js-mutate` class, and
  `data-nested-scope` covers the sheet delegation. Note beside `openConnect` that
  the submit navigating is deliberate — disconnecting leaves no usable gallery
  behind the tray.

## 4. Tests

- [x] 4.1 Add `tests/Unit/Asset/ConnectionTrayTest.php`, modelled on
  `SettingsTrayTest`: assert `data-connect` is emitted on the anchor branch and
  not on the current-page span; assert `TRAY_LINKS` carries the entry and the
  selector matches it; assert `openConnect` calls `_loadTray` with `/connect` and
  declares no `connectLoaded` flag; assert `closeConnect` empties the body; assert
  the tray body carries `data-nested-scope`. Head it with the same kind of note
  those tripwires carry — what is being held up and how it gets undone by tidying.
- [x] 4.2 Extend `tests/Unit/Asset/DialogFocusTest.php` to cover the new sheet:
  it declares `role="dialog"` and therefore must declare `tabindex="-1"`.
- [x] 4.3 Extend `tests/Unit/Asset/TrayDismissalTest.php` (and
  `TraySurfaceTest` / `TrayHeadSpacingTest` if they enumerate the trays) so the
  connection tray is covered by the shared grip/backdrop/Escape assertions rather
  than being the one tray nothing pins.
- [x] 4.4 Check whether `tests/Functional/ApplicationShellTest.php` asserts the
  set of tray destinations or the status markup, and extend it if so.

## 5. Gates and docs

- [x] 5.1 Run `composer test`, `composer stan` and `composer cs`; fix anything
  they report rather than committing around it.
- [x] 5.2 Check `README.md`, `docs/` and `CLAUDE.md` for staleness. The likely
  hits are any list of which destinations open as trays on a phone; if nothing
  user-facing changed, say so explicitly rather than inventing edits.

## 6. Validation

- [ ] 6.1 Build and run the `:dev` image and check by hand on a phone: the status
  in the actions tray opens the connection tray over the gallery; the tray
  swipes, backdrops and Escapes away; reopening it re-fetches; Disconnect leaves
  for `/connect` and shows its confirmation.
- [ ] 6.2 Check the fallbacks by hand: on a pointer/desktop screen the status
  still navigates, and on `/plex`, `/orphans` and `/settings` — pages with no
  gallery — it navigates there too.
- [ ] 6.3 Do not archive until the user has validated the `:dev` image.
