## 1. Unify the head spacing

- [x] 1.1 In `public/assets/app.css`, inside the `@media (max-width: 640px)`
      block, change `.modal__head`'s `padding-top` from `2px` to `14px` so it
      matches `.sheet__head`. Leave the horizontal and bottom values alone.
- [x] 1.2 Update the comment on that rule if it explains the old value, and add
      a line recording that the padding is deliberately the same as
      `.sheet__head`'s — the two rules are 400 lines apart and nothing else
      connects them.

## 2. Unify the title type

- [x] 2.1 In `.sheet__title`, add `font-size: 1.1rem` and remove the
      `line-height: 1.3` override so the title inherits the body's 1.6,
      matching `.modal__head h2`. Keep the element a `<span>` and leave
      `font-weight: 600` in place.
- [x] 2.2 Add a comment recording *why* the type is shared: the handle-to-title
      distance includes the title's half-leading, so two heads with identical
      padding but different line heights still present the title at different
      distances. This is the part a future edit is most likely to undo.

## 3. Stop the Support mark from sizing its head

- [x] 3.1 Add a `.support-ask__head` rule inside the `@media (max-width: 640px)`
      block declaring `font-size: 1.1rem` and `grid-template-rows: 1lh`. Both
      are required together — see design.md decision 3 for why `1lh` computes
      the wrong number without the `font-size`.
- [x] 3.2 Leave the base `.support-ask__head` rule, `.support-ask__mark`'s 40px
      dimensions, and the `1fr auto 1fr` tracks unchanged. The mark is meant to
      overflow the row, not shrink to it.
- [x] 3.3 Comment the new rule with the arithmetic that makes it correct: a
      28.2px row under a 40px tile overhangs 5.9px, which is less than the
      head's 14px padding, so the tile clears the handle by 8.1px instead of
      the 2px it had.

## 4. Pin the contract

- [x] 4.1 Add `tests/Unit/Asset/TrayHeadSpacingTest.php` in the shape of
      `AlertGlyphClearanceTest` — read `public/assets/app.css`, strip comments,
      match rules by regex, assert relationships rather than pixel positions.
- [x] 4.2 Give it a helper that slices the `@media (max-width: 640px)` block
      before matching. `.modal__head` and `.support-ask__head` each have a rule
      in both scopes, and matching the first occurrence silently tests the
      wrong one.
- [x] 4.3 Assert `.sheet__head` and the mobile `.modal__head` declare the same
      `padding-top`.
- [x] 4.4 Assert `.sheet__title` and `.modal__head h2` declare the same
      `font-size`, and that `.sheet__title` declares no `line-height` of its
      own, so both inherit the body's.
- [x] 4.5 Assert the mobile `.support-ask__head` declares both a `font-size`
      and a `grid-template-rows` expressed in `lh`, so the row stays derived
      from the title's type rather than pinned to a number.
- [x] 4.6 Assert the clearance: the head's `padding-top` exceeds half the
      difference between `.support-ask__mark`'s height and one line of the
      head's type. This is the assertion that fails if the mark grows, the
      padding shrinks, or the type gets smaller.
- [x] 4.7 Write the class docblock in the voice of its neighbours — what the
      two lineages are, why equal padding alone does not produce equal spacing,
      and why the mark sizing its own row is the failure that cannot be seen
      from inside the head that causes it.

## 5. Verify

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs`. All three
      must pass before committing.
- [x] 5.2 Confirm the new test actually fails against the old CSS — revert one
      of the three rules locally, watch it go red, restore it. A tripwire that
      cannot fail is worse than none.
- [x] 5.3 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness. Expected
      outcome is that nothing user-facing changed and no edit is needed; say so
      explicitly rather than inventing one.
- [ ] 5.4 Build the `:dev` image and open every tray in sequence on a phone —
      Sort, Import from Plex, Orphaned posters, Settings, Actions, Poster
      actions, Change poster, a confirmation, and Support development. The
      titles should land in the same place each time, and the heart should no
      longer crowd the handle. **Do not archive before this passes.**
