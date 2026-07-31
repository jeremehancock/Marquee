## 1. Re-scan on every open of the orphans tray

- [x] 1.1 In `openOrphans` (`public/assets/gallery.js`), split the already-loaded
  case out of the early return: keep guarding against a scan already in flight,
  but when the tray has been loaded before, re-run the scan instead of doing
  nothing.
- [x] 1.2 Implement the re-scan by resolving the nested `orphansPage` component
  inside `$refs.orphansBody` via `window.Alpine.$data()` and calling its
  `reload()`, driving its `loading` flag around the call exactly as its own
  `init()` does, so the tray shows its loading state on every open.
- [x] 1.3 Handle the component not being resolvable — the first load can fail and
  leave an error message in place of it — by falling back to a full tray load
  rather than throwing.
- [x] 1.4 Make sure a rapid close/reopen cannot start a second scan over an
  in-flight one, the way `orphansLoading` already guards the first load.
- [x] 1.5 Do NOT clear `orphansLoaded` on close. See design.md: re-running
  `_loadTray` re-registers a `window` listener that is never removed, and can
  duplicate a delete. Leave a short comment at the guard saying why the re-scan
  is done this way, so the simpler-looking version is not reintroduced.
- [x] 1.6 Update the comment on `submitDelete`, which justifies not re-scanning
  after a single delete on the grounds that "the next page open scans fresh" —
  that is now true in the tray as well, so the comment should say so rather than
  reading as desktop-only reasoning.

## 2. Leave the import tray as it is

- [x] 2.1 Confirm `openImport` still fetches once and is untouched by this change.
- [x] 2.2 Note at the shared `_loadTray` helper that fetch-once is a property of
  each caller, not of the helper: a configuration form does not go stale, a scan
  result does.

## 3. Tests

- [x] 3.1 Add a tripwire in `tests/Unit/Asset/`, following the existing asset
  tests, asserting the reopen path re-runs the scan.
- [x] 3.2 Assert in the same test that the reopen path does not re-initialise the
  tray — no second `Alpine.initTree` and no re-registration of the orphans
  `gallery:confirmed` listener — which is what guards against the rejected
  alternative in design.md.
- [x] 3.3 Verify the tripwire actually trips by temporarily reverting the fix, as
  was done for `TrayDismissalTest`.
- [x] 3.4 Run `composer test`, `composer stan`, and `composer cs` and fix any
  failures.

## 4. Documentation

- [x] 4.1 Check whether `README.md`, `docs/`, or `CLAUDE.md` describe the orphans
  tray or its refresh behaviour, and update in the same commit if so. If nothing
  user-facing changed, say so explicitly rather than inventing edits.

## 5. Device validation

- [x] 5.1 Build the image and smoke-test `/health` per `docs/docker.md`.
- [ ] 5.2 On a phone: open the orphans tray, close it by backdrop tap and by
  drag-down, and confirm each reopen shows the loading state and then a fresh
  result.
- [ ] 5.3 Confirm a poster whose media has been restored in Plex since the last
  scan is no longer listed after a reopen.
- [ ] 5.4 Open and close the tray several times, then confirm a deletion, and
  check exactly one orphan is deleted.
- [ ] 5.5 Confirm the import tray still opens instantly on reopen and is
  unaffected.
