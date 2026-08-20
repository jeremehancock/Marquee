## 1. Open the tab

- [x] 1.1 In `templates/gallery.html.twig`, remove the Plex Posters tab's
      `:aria-disabled` binding and its `:data-tooltip` binding. The tab is
      available for every poster.
- [x] 1.2 Change the tab's `@click` so it always switches to the Plex tab, and
      calls `loadPlexPosters()` only when `change.linked` is true and nothing is
      already loaded or loading. The guard moves from the tab switch to the
      request.
- [x] 1.3 Rewrite the call-site comment above the tab. The `aria-disabled`
      paragraph goes with the binding; what replaces it is why the tab is
      present for every poster and empty rather than unavailable — the strip's
      shape, and that a reason attached to a switched-off control does not reach
      a touch user. Its claim that the reason lives "in the panel" becomes true
      here, so state it as fact rather than intent.

## 2. Answer in the panel

- [x] 2.1 In the Plex panel in `templates/gallery.html.twig`, add the unlinked
      message, shown when `change.linked` is false, worded identically to
      `ChangePosterController.php:255` — "This poster is not linked to a Plex
      item." Draw it as the tab's other outcomes are drawn.
- [x] 2.2 Gate the panel's request-shaped states on `change.linked`, so an
      unlinked poster shows neither the loading line, nor an error, nor the
      "Tap a poster to preview it." invitation, nor the two poster groups.
- [x] 2.3 In `public/assets/gallery.js`, guard `loadPlexPosters()` on
      `this.change.linked` so the function refuses at the point the request is
      made, not only at the call site. Comment why the guard is duplicated.
- [x] 2.4 Confirm `modal__panel--wide` is left applying to the `plex` tab
      unconditionally, so the dialog does not resize according to whether the
      poster is linked.

## 3. Retire the off-tab treatment

- [x] 3.1 In `public/assets/app.css`, rewrite the comment on the
      `.modal__tabs button[aria-disabled='true']` rules. The rules stay and are
      unchanged; the comment stops confessing the touch gap and instead states
      the constraint a future off tab inherits — the inactive tier is already
      `--muted` so there is no room beneath it, and such a tab owes a signal
      that is not a further step down and owes its reason to the panel rather
      than to a tooltip. Record that the rules currently have no caller and why
      they are kept.

## 4. Tests

- [x] 4.1 In `tests/Unit/Asset/DisabledStateTest.php`, remove the
      `'Plex Posters tab'` row from `switchedOffControls()`, leaving three.
- [x] 4.2 Add an assertion over `switchedOffControls()` that no listed
      switched-off control carries a `:data-tooltip` bound on the same state as
      its `:aria-disabled`. Document that this pattern is pointer-only by
      construction, and that it is the shape of the defect rather than an
      instance of it.
- [x] 4.3 Re-anchor the docblock on
      `testTheOffStateDoesNotSuppressPointerEvents`. Replace the tooltip
      justification with the durable one: `pointer-events: none` makes an
      element non-hit-testable and so makes `cursor: not-allowed` unreachable,
      which `visual-design` requires, and it drops the tap through to whatever
      is behind. The assertion itself does not change.
- [x] 4.4 Add a tripwire that `templates/gallery.html.twig` no longer binds
      `aria-disabled` on the Plex Posters tab and that the Plex panel contains
      the unlinked message.
- [x] 4.5 Add a tripwire that `loadPlexPosters` in `public/assets/gallery.js` is
      guarded on `change.linked`, so the no-request guarantee is pinned by a
      test and not only by a scenario.

## 5. Gates and documentation

- [x] 5.1 Run `composer test`, `composer stan`, and `composer cs`. All three
      must pass.
- [x] 5.2 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness. The
      `CLAUDE.md` bullet on `aria-disabled` still holds — it describes controls
      that are switched off, and this change removes one rather than changing
      the rule. If nothing user-facing changed, say so explicitly in the commit
      rather than inventing edits.

## 6. Validate on the `:dev` image

**Shipped on 6.4 alone. 6.1–6.3 were not performed, deliberately.** They need a
poster with no `plex_items` row, and there is no user-facing way to make one —
posters enter only through import and import always writes the mapping, so the
repro is a hand-edit of `marquee.sqlite`. That was judged not worth doing against
live data for this change. The touch path is therefore reasoned rather than
observed: it follows from the panel being ordinary rendered markup with no
pointer gate, which is the whole substance of the change. Anyone who reaches an
unlinked poster before it is checked is the first to see it work.

- [ ] 6.1 On a phone, open the change-poster dialog for a poster with no Plex
      item. The Plex Posters tab opens and states the reason. **Not performed.**
- [ ] 6.2 On the same poster, confirm no request to `plex-posters` is made when
      the tab is opened. **Not performed** — pinned in source by
      `DisabledStateTest::testAnUnlinkedPosterIsNeverAskedAboutAtThePointOfAsking`.
- [ ] 6.3 Move between an unlinked poster and a linked one. The tab strip and
      the dialog keep their shape. **Not performed.**
- [x] 6.4 On a pointer device, confirm the tab no longer shows a tooltip and no
      longer draws itself as unavailable.
