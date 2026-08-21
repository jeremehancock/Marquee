## 1. Hand the scroll up

- [x] 1.1 In `public/assets/app.css`, add a `.poster-groups` rule inside the
  existing `@media (max-width: 640px)` block resetting `max-height: none` and
  `overflow-y: visible`, placed beside the `.find-grid` reset it mirrors.
- [x] 1.2 Comment the new rule with the reason and the lineage: the scroll moved
  from `.find-grid` up to `.poster-groups` when the grouped stack was
  introduced, and the mobile reset did not move with it. Name the base rule it
  overrides so the pair is findable from either end.
- [x] 1.3 Confirm the desktop rules are untouched — `.poster-groups` keeps
  `max-height: 62vh` and `overflow-y: auto` in its base rule, and
  `.modal__body` keeps its only `overflow` declaration inside the mobile block.

## 2. Verify in the running tray

- [ ] 2.1 Open Change Poster → Find Posters at a phone width for a title with
  enough candidates to overflow, and confirm one scrollbar, one scroller, and
  the last row reachable and tappable.
- [ ] 2.2 Repeat on Plex Posters for an item whose offered artwork runs long,
  and confirm the end of the last group is reachable.
- [ ] 2.3 Repeat at a short viewport height, where the fixed chrome takes a
  larger share, and confirm nothing is clipped there either.
- [ ] 2.4 Confirm the group headings still pin while their own group is on
  screen and leave with it, now against the tray head. Check both tabs — Plex
  Posters can show two headings in quick succession.
- [ ] 2.5 Confirm the drag-to-dismiss gesture and backdrop tap still dismiss the
  tray, and that a flick reaching the end of the contents does not scroll the
  gallery behind it or trigger pull-to-refresh.
- [ ] 2.6 Confirm the desktop dialog is unchanged: the grouped stack still
  scrolls at its 62vh cap and its headings still pin inside it.

## 3. Pin the decision in the asset tripwires

- [x] 3.1 In `tests/Unit/Asset/PosterGroupsTest.php`, add a test asserting the
  mobile block resets `.poster-groups` to `max-height: none` and a
  non-scrolling `overflow-y`, so reintroducing the nested scroller fails.
- [x] 3.2 Extend the existing test that pins the desktop cap so the two read as
  one decision — the stack owns the scroll in the dialog, the body owns it in
  the tray, and never both at once — rather than as two unrelated assertions.
- [x] 3.3 In `tests/Unit/Asset/TrayDismissalTest.php`, confirm
  `testTrayScrollersContainTheirOverscroll` and `testTrayBodyIsTheScroller`
  still hold unchanged, and adjust only if the new rule makes their wording
  inaccurate.
- [x] 3.4 Document in the test docblocks why a nested scroller inside a tray is
  the fault rather than a containment gap, citing the reachability requirement.

## 4. Gates and docs

- [x] 4.1 Run `composer test`, `composer stan`, and `composer cs`; fix any
  failure rather than committing around it.
- [x] 4.2 Check whether `README.md`, `docs/`, or `CLAUDE.md` go stale. This is
  a CSS scroll-structure fix with no user-facing surface change; if nothing is
  stale, say so explicitly rather than inventing edits.
- [x] 4.3 Confirm no delta to `poster-sources` is needed — its sticky-heading
  requirement is written about what the user sees, and task 2.4 verifies that
  behaviour is preserved. If it turns out not to be, add the delta before
  shipping.
