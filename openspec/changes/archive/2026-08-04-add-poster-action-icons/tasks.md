## 1. Consolidate the icon macros

- [x] 1.1 Give `_icons.html.twig`'s `icon()` macro a size parameter defaulting to
      the 22px the tab bar and sort trigger already use, so a caller can ask for
      the 20px the nav items use.
- [x] 1.2 Move the five nav glyphs (wall, import, orphans, support, logout) from
      `_nav_macros.html.twig`'s local `ic()` into `_icons.html.twig`, and delete
      `ic()`. Carry the import glyph's comment across — it explains why that mark
      is sideways, which is about to matter for Send and Fetch too.
- [x] 1.3 Point `_nav_macros.html.twig` at the shared macro and confirm the
      rendered header markup is byte-identical apart from the icon size argument.
- [x] 1.4 Correct `_icons.html.twig`'s docblock: it describes its contents as
      belonging to "the mobile UI", which stopped being true when the desktop
      header gained these glyphs.

## 2. Add the seven action glyphs

- [x] 2.1 Add `change` (pencil), `download`, `copy`, `fullscreen`, and `delete`
      (trash) to the shared macro, drawn to match the existing set: 24-unit
      viewBox, no fill, `currentColor` stroke at 1.7, round caps and joins.
- [x] 2.2 Add `fetch` as the same glyph as `import` — the same operation on one
      item rather than a library — rather than a second drawing of it.
- [x] 2.3 Add `send` as `import` with the arrowhead moved to the far end of an
      identical shaft, so the bar and shaft are unchanged and direction is the
      only difference.
- [x] 2.4 Put `send` and `fetch` side by side at 20px and confirm the arrowhead
      direction is legible at that size; also compare `send` against `logout`,
      which is deliberately close (see design.md).

## 3. Render icons on the action controls

- [x] 3.1 In `templates/partials/gallery_results.html.twig`, wrap each action
      control's icon and text in `card__action-ico` and `card__action-label`
      spans, following the card's existing `card__*` naming. Mark the icon
      `aria-hidden`; the label continues to name the control.
- [x] 3.2 Apply the same treatment to all seven controls, including the two inside
      `js-mutate` forms and the `<a>` for Download, so the set is uniform.
- [x] 3.3 Lay the controls out as left-aligned flex rows with a leading icon and a
      gap, in `.card__actions .btn`. Verify the control's height is unchanged —
      the glyph is 20px against a 20.48px line box, so the row must not grow.

## 4. Guarantee the stack fits

- [x] 4.1 Raise `.grid`'s minimum column width from 190px to 200px, so the frame
      is at least 300px against the ~295px a seven-action stack needs. Comment the
      link between the two numbers, since neither explains the other alone.
- [x] 4.2 Confirm the column count at the full 912px content column is unchanged
      at four, and note in the comment that five columns cannot occur.

## 5. Tests

- [x] 5.1 Assert in `tests/Functional/GalleryTest.php` that every action control
      renders an icon and a label, that the icon is `aria-hidden`, and that no
      control's accessible name depends on the glyph.
- [x] 5.2 Assert that the Fetch and Import controls render the same glyph, and
      that Send and Fetch differ only in the arrowhead — the pair's whole meaning
      rests on this, and a copy-paste slip would silently invert it.
- [x] 5.3 Add an `app.css` shape assertion, in the manner of
      `tests/Unit/Asset/StickyToolbarTest.php`, tying the grid's minimum column
      width to the height a seven-action stack needs, so the two cannot drift
      apart unnoticed.
- [x] 5.4 Confirm the existing header navigation tests still pass unchanged after
      the macro consolidation — they are what proves the move was mechanical.

## 6. Verify by hand and close out

- [x] 6.1 On a desktop viewport, hover a Plex-linked poster and confirm all seven
      actions are visible with no scrolling, at the narrowest column width the
      grid produces as well as at the widest.
- [x] 6.2 Confirm no label wraps now that an icon takes part of the row — "Fetch
      from Plex" is the longest and the one to watch.
- [x] 6.3 On a phone viewport, tap a poster and confirm the action sheet shows the
      same icons at its own larger size, with nothing clipped.
- [x] 6.4 Check `README.md`, `docs/`, and `CLAUDE.md` for anything this makes
      stale, and fix it in the same commit — or state explicitly that no
      documented surface changed.
- [x] 6.5 Run `composer test`, `composer stan`, and `composer cs`; all three must
      pass before committing.
