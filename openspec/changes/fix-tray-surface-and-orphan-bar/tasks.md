## 1. Complete the tray's panel reset

- [x] 1.1 Add `box-shadow: none` to `.sheet__body .panel` in `public/assets/app.css`
      (in the `@media (max-width: 640px)` block, currently at line 2502).
- [x] 1.2 Extend that rule's comment to say why: the rule flattens a panel so the
      tray's surface shows through, and a shadow left behind then traces a
      rectangle that is not drawn. Removing the line looks like tidying, so the
      comment has to carry the reason.

## 2. Give the orphan bar its own class

- [x] 2.1 Update both `.toolbar` selectors in `public/assets/gallery.js` — line
      457 (`reload`, reads `data-count`) and line 599 (`removeOrphanCard`, writes
      it back) — to the new class. Do this **before** the template rename; missed,
      `count` falls to 0 and the delete-all confirmation offers to delete "0
      orphaned posters" while still deleting all of them.
- [x] 2.2 Rename `class="toolbar"` to `class="orphans__bar"` on the count /
      delete-all bar in `templates/orphans/_results.html.twig` (line 11). Leave
      `data-count` and the `data-action="delete-all"` button as they are.
- [x] 2.3 Add the `.orphans__bar` rule to the **base** stylesheet in
      `public/assets/app.css` (near `.toolbar`, around line 849): `display: flex;
      flex-wrap: wrap; gap: 12px; align-items: center; justify-content:
      space-between; margin-bottom: 8px`. No `position`, no `z-index`, no
      `background`, no negative margin — at any width.
- [x] 2.4 Comment the rule with what its absences buy: unpositioned, it cannot
      outrank `.sheet .overlay` (z-index 5), which is what stopped it painting
      over the orphan-scan spinner on a reopen; unbled, it spans the tray without
      having to match `.sheet__body`'s 16px padding against `.container`'s 14px;
      and it is deliberately not pinned, because the list has nothing to keep
      reachable and delete-all is destructive.
- [x] 2.5 Confirm nothing else references the orphan bar by class —
      `grep -rn '\.toolbar\|class="toolbar' templates/ public/assets/` should
      leave only `templates/gallery.html.twig:60` and the gallery's own CSS.

## 3. Tripwires

- [x] 3.1 Add `tests/Unit/Asset/TraySurfaceTest.php` in the suite's house style —
      regex over the stylesheet source, `baseBlock()` / `mobileBlock()` helpers as
      in `StickyToolbarTest`, and a docblock carrying the reasoning rather than
      restating the assertions.
- [x] 3.2 Assert the panel reset is complete: `.sheet__body .panel` in the mobile
      block clears `background`, `border` **and** `box-shadow`. Word the failure
      message around the halo, so the next reader knows what removing the line
      looks like on screen.
- [x] 3.3 Assert the orphan bar is inert: the `.orphans__bar` rule declares no
      `position`, no `z-index` and no `background`, in the base block and in the
      mobile block alike.
- [x] 3.4 Assert the consequence directly — nothing in a tray body outranks the
      tray's own progress overlay — by checking the orphan bar declares no
      stacking order at all against `.sheet .overlay`'s `z-index: 5`. This is the
      tripwire that matters: the layering fault only appears on a *reopen* after a
      scan has resolved, which is the path a quick manual check skips.
- [x] 3.5 Confirm `tests/Unit/Asset/StickyToolbarTest.php` is untouched and still
      passes — the gallery's `.toolbar` rule is deliberately not part of this
      change.

## 4. Gates

- [x] 4.1 `composer test` — full PHPUnit suite green, including
      `OrphanTest`, `StickyToolbarTest` and `TrayDismissalTest`.
- [x] 4.2 `composer stan` and `composer cs` clean.
- [x] 4.3 Docs check: this change alters no user-facing behaviour, no
      configuration and no route, so `README.md`, `docs/` and `CLAUDE.md` stay as
      they are. Record that explicitly in the commit rather than inventing edits.

## 5. Validation on a phone

- [ ] 5.1 On the `:dev` image, open the Import from Plex tray on a phone: the form
      sits flat on the tray surface with no shadow rectangle around it. Confirm
      the standalone `/plex` page on desktop still shows the panel with its
      border, background and elevation.
- [ ] 5.2 Open the Orphans tray with no orphans present: the "No orphaned posters
      found" panel and its Re-check button sit flat, with no halo.
- [ ] 5.3 With orphans present, confirm the count / delete-all bar spans the tray
      edge to edge, on the tray's own surface, with no darker band and no strip of
      surface beside it.
- [ ] 5.4 The reported reopen path: open the Orphans tray, wait for the scan,
      close it, reopen it. While "Checking Plex for orphans…" is up, the previous
      count bar must be dimmed and blurred beneath the spinner like the grid
      below it — not drawn over it.
- [ ] 5.5 Delete a single orphan from the tray and confirm the count line and the
      delete-all confirmation both still report the right number — the check that
      task 2.1 landed.
