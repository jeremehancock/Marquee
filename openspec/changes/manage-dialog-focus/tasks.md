## 1. Declare the dialogs

- [x] 1.1 Add `role="dialog" aria-modal="true" aria-label="Preview poster"` and
      `tabindex="-1"` to the preview overlay in `templates/gallery.html.twig`
      (`.viewer.viewer--preview`, ~line 334). It is the overlay root and the
      dialog panel at once — see design Decision 2.
- [x] 1.2 Add `tabindex="-1"` to the five dialog panels in
      `templates/gallery.html.twig`: the change dialog `.modal__panel` and the
      sort, import, orphans and settings `.sheet__panel`s.
- [x] 1.3 Add `tabindex="-1"` to the confirm panel in
      `templates/orphans.html.twig`.
- [x] 1.4 Add `tabindex="-1"` to the actions tray panel in
      `templates/partials/_menu.html.twig`.
- [x] 1.5 Add `tabindex="-1"` to the confirm and poster-actions panels in
      `templates/partials/_overlays.html.twig`. Leave the plain viewer at line 61
      untouched and undeclared — design Decision 8.
- [x] 1.6 Confirm the count: eleven overlays, ten now carrying
      `role="dialog"`, each with `tabindex="-1"`, and one deliberately without.
- [x] 1.7 Suppress the focus ring on the dialog panel itself in
      `public/assets/app.css`. `app.css` carries no global focus rule — every
      indicator is scoped to a control — so a panel focused programmatically
      would draw the browser's default ring instead. A panel is announced, not
      operated, and the existing "Keyboard focus is as visible as hover"
      requirement is about interactive elements. Scope the rule to
      `[role="dialog"][tabindex="-1"]` so it cannot reach a control.

## 2. The focus manager

- [x] 2.1 Add a new self-invoking block to `public/assets/gallery.js`, sited
      after the page scroll lock, with a header comment stating that it is the
      second consumer of the same DOM signal and why it does not share the
      lock's observer (design Decision 10).
- [x] 2.2 Implement overlay discovery: for each `.sheet, .modal, .viewer` root
      that is displayed and not `overlay-closing`, resolve its
      descendant-or-self `[role="dialog"]`. A root resolving to none is not
      managed.
- [x] 2.3 Implement the stack: push on an overlay becoming visible, pop on it
      ceasing to be visible, with the top of the stack holding focus. Order of
      arrival only — not document order, not z-index (design Decision 3).
- [x] 2.4 On push, snapshot the origin: `document.activeElement` together with
      its ancestor chain, so a removed origin can resolve to the nearest
      surviving container (design Decision 5).
- [x] 2.5 On push, move focus to the panel with `{ preventScroll: true }`.
- [x] 2.6 On pop, restore focus to the first entry of the saved chain still in
      the document, with `{ preventScroll: true }`. Restore is driven by the same
      `overlay-closing` signal the scroll lock releases on, so it is not delayed
      by the exit animation (design Decision 6).
- [x] 2.7 Implement Tab and Shift+Tab wrapping within the top overlay,
      recomputing focusable descendants at press time — tray bodies arrive over
      the network and the find/Plex grids are rendered by Alpine long after open.
- [x] 2.8 Implement the fell-out backstop: while the stack is non-empty, focus
      landing outside the top overlay is returned to its panel (design
      Decision 9).
- [x] 2.9 Verify by reading that the manager never holds state surviving an empty
      stack, so any failure to pop degrades to today's behaviour rather than to a
      page with focus locked inside a dismissed overlay.

## 3. The capability map

- [x] 3.1 Amend the `visual-design` line in `openspec/config.yaml` so its gloss
      names keyboard reachability and focus alongside the visual vocabulary,
      replacing "how things look, not what they do" (design Decision 11).

## 4. Tests

- [x] 4.1 Add `tests/Unit/Asset/DialogFocusTest.php`, a source-shape tripwire in
      the manner of `DisabledStateTest`, opening with a docblock that states
      plainly what it can and cannot catch.
- [x] 4.2 Assert every `role="dialog"` panel across all four templates carries
      `tabindex="-1"`, and that the counts of the two agree — so a dialog added
      without its attribute fails.
- [x] 4.3 Assert every `role="dialog"` carries `aria-modal="true"` and an
      accessible name.
- [x] 4.4 Assert the preview overlay carries its role, modal flag and name — the
      surface that had none, and the reason two switched-off controls were
      unreachable.
- [x] 4.5 Assert the manager is keyed on `[role="dialog"]` and contains no
      hard-coded list of overlay names or ids, so Decision 2 cannot be quietly
      reverted into a registry.
- [x] 4.6 Assert both focus moves pass `preventScroll`, since a regression there
      is invisible except on a scrolled page.
- [x] 4.7 Assert the plain viewer in `_overlays.html.twig` declares no dialog
      role, pinning Decision 8 as a decision rather than an oversight.
- [x] 4.8 Assert the focus-ring suppression from 1.7 is scoped to a dialog panel
      — both attributes together — so it can never be widened into a rule that
      silences a control's focus indicator.

## 5. Gates and validation

- [x] 5.1 `composer test`, `composer stan`, `composer cs` — all three green.
- [x] 5.2 Check whether `README.md`, `docs/` or `CLAUDE.md` are made stale by
      this change and fix in the same commit; if nothing user-facing changed,
      say so explicitly rather than inventing edits. **Done:** `README.md` and
      `docs/` needed nothing — no setting, screen or configurable behaviour
      changed, and the one mention of dialogs there is a blurb about animation.
      `CLAUDE.md` gained a conventions bullet: an overlay is managed for focus
      only because it declares the role, which no test can enforce for an
      overlay that declares nothing.
- [x] 5.3 `openspec validate manage-dialog-focus --strict`.
- [ ] 5.4 **Keyboard pass against the `:dev` image — the only step that proves
      any of this works, since the suite cannot.** On the gallery: Tab to a
      poster, open Change poster, confirm focus lands in the dialog and its name
      is announced; Tab past the last control and confirm it wraps; Escape and
      confirm focus returns to the card.
- [ ] 5.5 `:dev` pass, nesting: from the change dialog open a preview, press
      "Use this poster", and confirm focus is not thrown to the top of the
      document when the action row is replaced; Escape and confirm focus returns
      into the change dialog with it still open.
- [ ] 5.6 `:dev` pass, close paths: dismiss overlays by backdrop, by close
      button, and by swipe on a phone, and confirm focus returns each time.
- [ ] 5.7 `:dev` pass, removed origin: delete a poster from the actions tray and
      confirm focus lands in the results region rather than at the top of the
      document.
- [ ] 5.8 `:dev` pass, the trays: open import, orphans and settings and confirm
      focus lands in each while its body is still loading, and that the fetched
      contents are reachable by tabbing forward once they arrive.
