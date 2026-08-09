## Context

Three phone-only rendering faults, all in the trays. They come from two rules,
and both rules are cases of the same larger thing: a component authored for a
full page gets reused inside a tray, and something that was correct on the page
follows it in.

**Cause A — a shadow with nothing casting it.** `.panel` (`app.css:500`) is a
surface: background, border, radius, and `box-shadow: var(--elev-2)`. Inside a
tray, `.sheet__body .panel` (`app.css:2502`, in the `@media (max-width: 640px)`
block) flattens it so the tray's own surface shows through — it clears the
background, the border, and the padding, but not the shadow. The result is an
`--elev-2` halo tracing a rectangle that is no longer drawn.

Two surfaces hit it. `templates/plex.html.twig:30` is `<form class="panel form">`
— the whole Import from Plex tray. `templates/orphans/_results.html.twig:6` is
the "No orphaned posters found" empty state, which looks worse because the panel
is a single line of text and a button, so there is visible daylight between the
halo and any real edge.

**Cause B — the gallery's pinned bar, borrowed.** `templates/orphans/_results.html.twig:11`
labels the orphan count / delete-all bar `class="toolbar"`. At phone width,
`.toolbar` (`app.css:2349`) is solved entirely against the gallery page:

```css
position: sticky; top: 0; z-index: 30;
background: var(--chrome-tint);
backdrop-filter: var(--chrome-blur);
margin: 0 -14px 8px;   /* .container's gutter */
padding: 8px 14px;
```

All five clauses follow the class into the tray, and two symptoms fall out.

*It does not span the tray.* `.sheet__body` pads 16px; the bleed is −14px,
solved for `.container`. A 2px channel of tray surface is left down each edge.
The tint is the second half of it: `--chrome-tint` is the page's colour, so a
band of page colour is painted over the tray's `--surface`. That band is the
"background behind the number of orphans" — on the page there is nothing to
contrast against, in the tray there is.

*It paints over the progress overlay, unblurred.* `.sheet .overlay`
(`app.css:2072`) is knocked down to `z-index: 5` so it pins inside the tray
rather than covering the screen. `.sheet__panel` is `position: relative` with
`z-index: auto`, and `.sheet__body`'s `overflow-y` opens no stacking context
either, so the sticky bar's `z-index: 30` competes with the overlay's `5`
directly and wins:

```
z:30  .toolbar  (sticky, own backdrop-filter)   ← drawn on top
z: 5  .overlay  "Checking Plex for orphans…"
      .sheet__body  results
```

Because it is drawn *above* the overlay, the overlay's dim and blur never reach
it — a backdrop filter samples only what is behind. Worse, the bar's own
backdrop filter then samples the spinner.

Only a reopen shows it. `openOrphans` (`gallery.js:1024`) loads the tray on a
first open, so the body is empty while the overlay is up and there is nothing to
paint over. A reopen goes through `_rescanOrphans` (`gallery.js:1052`), which
deliberately leaves the previous results — bar included — in the DOM while
`loading` is true.

## Goals / Non-Goals

**Goals:**

- Every flattened panel inside a tray casts no shadow; every panel on its own
  page keeps the one it has.
- The orphan bar reads as part of the tray it is in, edge to edge, on the tray's
  own surface.
- Nothing inside a tray can be drawn above that tray's progress overlay.
- The fix is expressed so it cannot silently regress, in the shape-tripwire style
  the `tests/Unit/Asset` suite already uses.

**Non-Goals:**

- Any behaviour change. The scan, the delete, the confirm, and the
  reopen-rescans rule all stay as they are.
- Changing what a reopen shows. Stale results stay visible under the spinner —
  once the bar is unpositioned, the overlay dims and blurs them, which reads
  correctly as refreshing.
- Touching the gallery's own pinned toolbar, or `StickyToolbarTest`.
- Revisiting `--elev-2` on `.panel` generally. The desktop panel is correct.

## Decisions

### Complete the tray's panel reset rather than move the shadow

Add `box-shadow: none` to `.sheet__body .panel`. The rule already exists and
already means "this panel is not a surface here" — it was simply incomplete.

*Alternative rejected:* moving `box-shadow` off `.panel` into a `.panel--raised`
modifier, so nothing has to be un-done. That is the cleaner factoring in the
abstract, but `.panel` appears on eight-plus templates and every one of them
would need the modifier for a defect that shows in one context. It also fights
the last change (`ce894c8`, "Drop the resting card elevation"), which settled
where resting elevation belongs.

### Give the orphan bar its own class, with no positioning at all

`templates/orphans/_results.html.twig` gets `class="orphans__bar"`, defined in
the **base** stylesheet as the plain flex layout the bar always needed:

```css
.orphans__bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
```

No `position`, no `z-index`, no `background`, no negative margin — at any width,
in the tray and on the standalone `/orphans` page alike.

Three things follow from having no positioning, and the third is the reason to
prefer it over re-tuning:

1. The bar sits inside `.sheet__body`'s padding like any other content, so it
   spans the tray with no gutter arithmetic to keep in sync.
2. It has no surface of its own, so the tray's `--surface` shows through and
   there is no band.
3. It is not in the stacking contest at all, so it cannot outrank
   `.sheet .overlay`. The layering bug is dissolved rather than re-tuned — there
   is no z-index left to get wrong later.

The bar is deliberately **not** pinned, including on the standalone page where
it currently is by accident. The orphan list has no search or sort to keep
reachable, and a permanently on-screen "Delete all orphans" is not a control to
make easier to hit.

*Alternative rejected:* scoping the phone rule to `.gallery-head .toolbar` and
leaving the orphan markup alone. Smaller diff, but it makes the orphan bar's
correctness depend on a selector written for a different page, and
`StickyToolbarTest::rule()` matches a bare `.toolbar` anchored at line start —
the tripwire guarding the gallery's pinned bar would have to be rewritten to
accommodate a change that has nothing to do with the gallery.

*Alternative rejected:* keeping it sticky inside the tray on `--surface`, with a
−16px bleed and a `z-index` below 5. It works, but it re-tunes three values
against the tray instead of removing them, and leaves a second place where the
tray's overlay ranking has to be remembered.

### The rename must carry into `gallery.js`

`gallery.js` reads and writes the count through this bar, by class:

- `gallery.js:457` — after a reload, reads `data-count` off `.toolbar` to set
  `count`, which is what the delete-all confirmation says it will delete.
- `gallery.js:599` — after a single delete, writes the decremented `data-count`
  back and rewrites the "N orphaned posters." line.

Both selectors must follow the rename. Missed, they fail silently and badly:
`count` falls to 0, so the confirmation offers to delete "0 orphaned posters"
while delete-all still deletes every one of them.

This is the one edit outside CSS and Twig. It is a mechanical consequence of the
rename, not a behaviour change — the decision to leave `_rescanOrphans` and the
stale-results behaviour alone still stands.

### Assert both causes as shape tripwires

A new `tests/Unit/Asset` test in the house style — regex over the stylesheet
source, with a docblock carrying the reasoning, as `StickyToolbarTest` and
`TrayDismissalTest` do. Two tripwires:

- The `.sheet__body .panel` reset zeroes elevation as well as the surface. Worded
  so that removing `box-shadow: none` fails, since that is the exact edit that
  reintroduces the halo and looks like tidying.
- The orphan bar's rule declares no `position`, no `z-index` and no
  `background`, so it can never again be drawn above the tray's overlay.

The second is the one worth having. The halo is visible the moment anyone opens
the tray; the layering fault only appears on a *reopen* after a scan has already
resolved, which is exactly the path a quick check skips.

## Risks / Trade-offs

- **The `gallery.js` selectors are missed** → the delete-all count silently
  reads 0. Called out as its own task, ahead of the Twig rename, and the
  template is the only place the class is authored.
- **A long orphan list loses its delete-all on scroll** → accepted, and intended.
  The control is destructive and the list has nothing else to keep pinned.
  Reversible in one rule if it turns out to be wrong on a real library.
- **`.orphans__bar` restates `.toolbar`'s flex layout** → six duplicated
  declarations. Preferred to a shared base class, which would re-couple the two
  bars and invite the phone rule to reach the orphan bar again.
- **A future panel is added to a tray and re-hits cause A** → the reset is on
  `.sheet__body .panel`, so it applies to any panel placed in a tray
  automatically; only a panel using a different class would escape it.

## Migration Plan

None. Static assets and one template; no data, no config, no route. The
stylesheet is served with the app image, so a rollback is a revert.

## Open Questions

None. Both design calls — the bar's own class with no pinning, and leaving the
stale-results behaviour alone — were settled before this change was written.
