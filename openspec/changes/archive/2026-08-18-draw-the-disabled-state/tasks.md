## 1. The disabled treatment in the stylesheet

- [x] 1.1 Remove the dead `.is-disabled` rule at `public/assets/app.css:1381`,
      after confirming with a repo-wide grep that nothing applies the class.
- [x] 1.2 Add a `.btn:disabled, .btn[aria-disabled="true"]` rule beside the `.btn`
      block (~`app.css:1016`): `--surface` background, `--border` border,
      `--muted` text, `cursor: not-allowed`. No new literal values — every colour
      comes from the token contract. Placed after `.btn--accent` and `.btn--danger`
      so an emphasised button surrenders its emphasis. Keep `:disabled` in the
      selector even though nothing uses it after this change, so a native
      `<button disabled>` added later is not silently untreated.
- [x] 1.3 Restrict the hover rules inside `@media (hover: hover)`
      (`app.css:1034-1046`) with `:not(:disabled):not([aria-disabled="true"])` —
      `.btn`, `.btn--accent`, and `.btn--danger`. Restrict, do not override: a
      switched-off control must have no hover state declared for it at all,
      rather than one declared and undone.
- [x] 1.4 Restrict `.btn:active` (`app.css:1054`) the same way. Under
      `aria-disabled` this is load-bearing rather than defensive: the element is
      an ordinary enabled button as far as the browser is concerned, so `:active`
      **will** match and the button will visibly depress under a press unless the
      rule excludes it.
- [x] 1.5 Leave `.btn:focus-visible` (`app.css:1058`) unrestricted, and say why in
      a comment: the control is now focusable, so a keyboard user lands on it and
      has to be able to see where they are.
- [x] 1.6 Do **not** use `pointer-events: none` anywhere in this treatment. It
      would stop the click without a handler change, but it also kills the tooltip
      on the Plex Posters tab — the only thing that currently explains why that
      tab is off. Record the rejection in a comment so it is not reintroduced as
      an obvious simplification.
- [x] 1.7 Give the modal tab strip its own treatment
      (`.modal__tabs button[aria-disabled="true"]`), so the Plex Posters tab at
      `templates/gallery.html.twig:157` finally shows the state its own comment
      at line 155 claims it shows. `.btn:disabled` will not reach it — the tab
      strip is styled separately. Suppress its hover and active states to match.
- [x] 1.8 Check the treatment against `.sheet__body form .btn--accent`
      (`app.css:3031`), the tray's full-width variant — its padding and width are
      unchanged, only its colour, and it must not end up looking like a divider.
- [x] 1.9 Comment the new block in the register of the surrounding file: what
      "off" has to communicate, and why the hover suppression rather than the
      fill colour is the substance.

## 2. Reachable, announced, and refused

Order matters here: every binding in 2.2 must land with its guard from 2.3-2.5,
because `aria-disabled` announces without enforcing. A binding switched over
ahead of its guard is a live button that looks dead.

- [x] 2.1 Read the `.btn` treatment from group 1 as done before starting — an
      `aria-disabled` control with no appearance is strictly worse than the
      current state, since it is now pressable as well as indistinguishable.
- [x] 2.2 Switch all five bindings from `:disabled` to `:aria-disabled`:
      `templates/plex.html.twig:86`, `templates/connect.html.twig:88`, and
      `templates/gallery.html.twig:157`, `:285`, `:287`. Alpine renders
      `aria-disabled="false"` rather than dropping the attribute, which is what
      the CSS in 1.2 expects; verify that rather than assuming it.
- [x] 2.3 Guard the Plex Posters tab's inline `@click`
      (`templates/gallery.html.twig:157`) on `change.linked`, so a press on the
      unlinked tab neither switches tab nor fires `loadPlexPosters()`.
- [x] 2.4 Guard Cancel's inline `@click` (`templates/gallery.html.twig:287`) on
      `preview.applying`, so it cannot dismiss the confirm bar out from under a
      change that is already running.
- [x] 2.5 Guard the import form's submit. `@submit="importing = true"` becomes
      conditional on the same expression the button binds, covering the case
      `disabled` was quietly handling on its own: with the default button
      disabled a form does not submit on Enter, and an `aria-disabled` one will.
      Cover both routes — the full page at `/plex` and the tray's intercepted
      submit in `runImport`.
- [x] 2.6 Confirm no guard is needed at `applyPreview()` (`gallery.js:1414`) or
      `signIn()` (`gallery.js:674`) — both already return early on their own
      flag. State this explicitly rather than leaving the reader to check.
- [x] 2.7 Verify the server-side backstop is intact rather than adding one:
      `src/Controller/PlexImportController.php:81` already rejects an empty
      selection with a flash, and `runImport` (`gallery.js:1022`) surfaces it as
      a notice. That is what makes a missed guard an error message instead of a
      bad import; do not weaken it.

## 3. The import form's re-download gate

- [x] 3.1 In `templates/plex.html.twig:80`, change the re-download option's
      `x-show="type"` to `x-show="type && sections.length > 0"` so it appears on
      exactly the condition that makes the Import button usable.
- [x] 3.2 Add a comment at that line recording the invariant: `force` has no
      `x-model`, so hiding it does not clear it, and a hidden-but-checked box
      would still submit `force=1`. The two conditions must stay identical, not
      merely compatible.
- [x] 3.3 Confirm the Import button's own condition
      (`templates/plex.html.twig:86`) is unchanged apart from the binding rename
      in 2.2 — the expression was already correct, and this change is what
      finally draws it. State that explicitly rather than leaving it implied.

## 4. Tripwires

- [x] 4.1 Add `tests/Unit/Asset/DisabledStateTest.php`, following the shape of
      `DesignTokenContractTest` (read `app.css`, strip comments before matching)
      with a docblock in that file's idiom explaining what the test can and
      cannot catch.
- [x] 4.2 Assert the off-state rule exists for `.btn`, covers both `:disabled`
      and `[aria-disabled="true"]`, and draws its colours from tokens rather than
      literals.
- [x] 4.3 Assert every hover rule under `@media (hover: hover)` that targets a
      button excludes both forms, and that `.btn:active` does the same. The
      `:active` assertion is the one that matters most — it is the rule that
      starts matching under `aria-disabled` and changes nothing until pressed.
- [x] 4.4 Assert the treatment declares no `pointer-events: none`, which would
      silence the tooltip that explains the Plex Posters tab.
- [x] 4.5 Assert `.is-disabled` is gone, so it cannot be reintroduced as the
      shortcut this change rejected.
- [x] 4.6 Assert every `:aria-disabled` binding in the templates has a guard
      behind it — for each of the five, that the handler it invokes (or the
      inline expression itself) tests the same flag. This is the one part of the
      `aria-disabled` switch a test can genuinely check, and the failure it
      guards against is a button that looks dead and is not.
- [x] 4.7 Read `templates/plex.html.twig` and assert the re-download option's
      `x-show` condition and the Import button's condition reference the same two
      pieces of state (`type` and `sections`) — the invariant from 3.2 rather
      than a restatement of the markup.
- [x] 4.8 Run `composer test`, `composer stan`, and `composer cs`. All three must
      pass before any commit.

## 5. Documentation

- [x] 5.1 Check whether `README.md` or `docs/` describes the import form's
      behaviour in a way this change makes stale — particularly `README.md:409`
      and `docs/testing.md:323`, both of which mention the re-download option. If
      nothing user-facing changed, say so explicitly rather than inventing edits.
- [x] 5.2 Confirm `CLAUDE.md` needs no change; this adds no setting, no
      environment variable, and no new service.

## 6. Verification against the `:dev` image

- [x] 6.1 Import tray on a phone: with nothing selected the Import button reads
      as off and does not respond to a press; the re-download option is absent.
      Pick a type only — the option is still absent, the button still off. Pick a
      library — both come alive together.
- [x] 6.2 Import page on a desktop: point at the off Import button and confirm it
      does not brighten, does not depress when pressed, and the cursor says it
      will not respond. This is the specific defect; check it directly.
- [x] 6.3 Keyboard pass on `/plex`, which is where the reachability work is
      actually observable: Tab reaches the off Import button, it shows a focus
      ring, Enter and Space do nothing, and pressing Enter on a content-type
      radio does not submit the form.
- [x] 6.4 Keyboard pass on `/connect`: press **Sign in with Plex** by keyboard and
      confirm focus stays on the button as it switches off, rather than returning
      to the top of the document. This is the focus-stranding fix; it is invisible
      unless checked deliberately.
- [x] 6.5 Check `--muted` on `--surface` stays legible in both places the button
      appears — the page and the translucent tray — since the effective
      background differs between them.
- [x] 6.6 Open the change dialog on a poster with no Plex item: the Plex Posters
      tab reads as off, its tooltip still gives the reason on a device with a
      pointer, and pressing it does nothing at all. Note for the record that on
      touch it still explains nothing — known, and out of scope.
- [x] 6.7 Apply a poster change and a Plex sign-in, watching for a flicker as the
      brief `applying` and `busy` states turn the buttons off under their progress
      overlays. Confirm Cancel cannot be pressed while the change is running.
- [x] 6.8 Run an import to completion and confirm the tray rewind still clears the
      re-download option — its enclosing `x-show` now collapses at the same moment
      `_rewindImportForm` unchecks it, and the two must not fight.
- [x] 6.9 Screen reader spot-check on the off Import button: it is announced as a
      button and as unavailable, rather than not being announced at all.
