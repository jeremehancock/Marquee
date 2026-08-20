## Context

The application has no focus management. Eleven full-screen overlays open over
the page and none of them moves focus in, keeps it in, or hands it back.

Two facts about the codebase shape everything below.

**Open state is not in one place.** It is spread across `galleryUI`,
`orphansPage`, and a standalone `menuOpen` scope on the topbar, and the import,
orphans and settings trays fetch whole pages at runtime and inject them — which
brings *further* overlays in with them, the orphans confirm among them. There is
no flag to watch. `gallery.js` already had to solve this once, for the page
scroll lock, and solved it by watching the DOM rather than the scopes:

> *"There is no single 'an overlay is open' flag to watch … So rather than wiring
> each one, watch the DOM for the inline `display` that x-show writes, the same
> way the drag gesture above stays agnostic of which scope owns a tray."*

**Overlays are descendants of the page they cover.** Everything except the
teleported actions tray lives inside `<main class="container">`, under the page's
own Alpine root:

```
<body>
├── <header class="topbar">
│     └── x-data="{ menuOpen }"
│           └── <template x-teleport="body"> ─────┐
├── <main class="container">                      │
│     └── <div x-data="galleryUI">                │
│           ├── toolbar, #results   ← "the page"  │
│           ├── .modal  change dialog ← overlays  │
│           ├── .viewer preview      ←            │
│           ├── .sheet  sort / import / orphans / settings
│           └── _overlays: .modal .sheet .viewer  │
├── <footer>                                      │
└── (teleported actions tray) ←───────────────────┘
```

There is no element meaning "page content but not the overlays".

The inventory is eleven, not the nine that declare themselves. Two overlays are
modal in behaviour and undeclared:

| | |
| --- | --- |
| `gallery.html.twig:334` `.viewer--preview` | backdrop, Escape, five controls — **no role, no name**. Two of the three switched-off controls stranded by `draw-the-disabled-state` live here. |
| `_overlays.html.twig:61` `.viewer` | full-screen image, `alt=""`, closes on any click. **No focusable content at all.** |

## Goals / Non-Goals

**Goals:**

- A keyboard user reaches an overlay's contents by opening it, not by tabbing
  the page behind it.
- Tab and Shift+Tab stay inside the topmost overlay.
- Focus returns to its origin on every close path, including the ones no
  per-dialog handler would see.
- Nesting works: change dialog → preview → confirm, each handing focus back as
  it closes.
- A twelfth overlay added later is managed without being registered anywhere.

**Non-Goals:**

- `inert` on the page behind an open overlay. Argued in Decision 7.
- Giving the full-screen poster viewer a close affordance. Argued in Decision 8.
- Touching the desktop overflow menu in `layout.html.twig`. It is a
  `role="menu"`, not a dialog, it already restores focus on Escape, and its panel
  follows its trigger in DOM order so Tab reaches it. Out of scope; noted so the
  next reader knows it was looked at.
- Any change to how overlays open, close, animate, or lock the page scroll.

## Decisions

### 1. One document-level manager, not per-dialog wiring

Alpine owns open and close, so per-dialog wiring is the obvious reading. It is
the wrong one, and the close paths are why:

| Close path | Per-dialog `@click` wiring | Document-level manager |
| --- | --- | --- |
| Escape (window-bound, guarded) | ten hand-written restores | free |
| backdrop click | ten more | free |
| close button | six more | free |
| swipe-to-dismiss (touch) | **missed** | free |
| completed action (`doConfirm`, `applyPreview`, `saveSettings`) | scattered | free |
| navigating away from the menu tray | n/a | free |
| an overlay added later | **missed** | free |

Swipe-to-dismiss is the decisive one. `initSheetGestures` is document-delegated
and deliberately scope-agnostic — it dismisses by synthesising a click on the
backdrop, "without knowing which Alpine scope owns it." Per-dialog restore
handlers would silently never fire for any touch dismissal.

*Alternative considered:* vendoring `@alpinejs/focus` and putting `x-trap` on
each overlay. Rejected. It is a third vendored script and a service-worker
precache entry, and `x-trap` on eleven elements is per-dialog wiring under
another name — with the same blind spots for swipe-dismiss and for overlays
injected into the trays at runtime.

### 2. An overlay is managed because it says it is a dialog

The manager finds its subjects with `[role="dialog"]`, not a list of selectors
and not a registry.

This falls out well in three directions. The plain poster viewer declares no
role and is therefore excluded, matching Decision 8 without a special case. The
orphans confirm, injected into the orphans tray at runtime, arrives already
carrying the attribute and is managed the moment it appears. And a twelfth
overlay is managed by being marked up correctly, which is the thing its author
was going to do anyway.

The panel carrying `role="dialog"` is also the trap boundary and the element
that takes focus, so one attribute answers three questions. Each such panel
gains `tabindex="-1"` so it can receive focus programmatically without entering
the tab order.

`x-show` writes `display` on the overlay *root* (`.modal`, `.sheet`,
`.viewer`), which is the panel's parent — except on the preview, where root and
dialog are the same element. So visibility is read from the root and the dialog
is found as its descendant-or-self.

### 3. The stack is built from observation order, not the DOM

These genuinely nest, and the existing Escape guards already encode the ordering
by hand:

```
change:  "if (!confirm.open && !preview.open) change.open = false"
sheet:   "if (!confirm.open) closeSheet()"
```

```
  change dialog ──▶ preview ──▶ (confirm bar, same surface)
  poster actions tray ──▶ confirm dialog
  orphans tray ──▶ orphans confirm
```

Document order is not stack order: the shared confirm is markup at
`_overlays.html.twig:12`, *before* the poster actions tray at `:47` that raises
it. z-index is not stack order either — it is a static value per class. So the
manager pushes when an overlay becomes visible and pops when it stops being
visible, and the top of that stack is the overlay that holds focus. Order of
arrival is the only signal that is true by construction.

*Known limit:* two overlays becoming visible in the same animation frame would
be ordered arbitrarily. No interaction in the app does that — one user action
opens one overlay — and the case is left unhandled rather than guessed at.

### 4. The panel takes focus on open, not its first focusable control

Three of the eleven — import, orphans and settings — fetch their body over the
network *after* opening, and render "Loading…" in the meantime. At the moment
focus must move, there is no first focusable control to move it to. That settles
it on its own.

It is also the better behaviour for the other eight: the panel's `aria-label`
("Change poster", "Orphaned posters") is announced, the user tabs forward through
the contents in DOM order, and no keyboard user is ever dropped onto a
destructive confirm button.

*Alternative considered:* focusing Cancel on the two confirm dialogs, per
convention. Rejected as an exception that does not earn itself — Cancel is
already the first control after the close button, one Tab away.

When a tray's fetched body arrives, focus stays on the panel and the new content
is simply ahead of it in the tab order. No second focus move is needed.

### 5. Return focus to what was focused at open, remembered as a chain

The change dialog and the shared confirm are opened by **window custom events**
(`@gallery:change.window`, `@gallery:confirm.window`) dispatched from delegated
document handlers. There is no trigger element in scope at the dialog to name.
So the origin is `document.activeElement`, snapshotted as the overlay is pushed.

The element is often gone by the time it is wanted: deleting a poster removes the
card that opened the tray, deleting an orphan removes its row, saving settings
reloads the page. So what is remembered is the element **and its ancestor
chain**, and focus is restored to the first entry still in the document. A
removed poster card resolves to `#results`, which is where the user was looking —
rather than to the top of the document, which is the failure this whole change
exists to end.

Two cases resolve on their own. An origin of `<body>` — what a touch tap leaves
behind — restores to nothing, harmlessly. An origin that is `aria-disabled` is
still focusable, which is precisely what `draw-the-disabled-state` bought.

### 6. Focus is restored when the overlay starts closing, not when it finishes

`x-show` keeps an element displayed for the length of its leave transition. The
scroll lock learned this the hard way and switched its release to the
`overlay-closing` class arriving; the same signal, for the same reason, governs
the restore. Anything else would contradict the existing requirement that
*"dismissing one SHALL take effect immediately even while the exit animation is
still running."*

Focus moves use `{ preventScroll: true }` throughout. While an overlay is open
the body is pinned by the scroll lock, and an unguarded focus scroll would fight
it.

### 7. No `inert` — the promise is already declared, and the cost is structural

Three mechanisms make the same promise: `aria-modal="true"` states it for
assistive technology, a Tab trap enforces it for the keyboard, and `inert`
enforces it for both. `aria-modal` is already in the markup on nine of eleven and
is added to the tenth here. The trap is what this change builds.

`inert` is the third, and it has nowhere to go. As the diagram above shows, the
overlays are descendants of the content they would need to inert; there is no
"page minus overlays" element. Getting one means either wrapping page content in
a new element across every template, or teleporting every overlay to `<body>` —
which the actions tray already does, but for an unrelated reason (the header's
`backdrop-filter` establishes a containing block, written up at length in
`_menu.html.twig`).

Deferred rather than dropped. It is worth having, and the shape of the later
change is known: teleport the overlays, then inert `header`, `main` and `footer`.
Doing it here would put a DOM reshuffle of every overlay in the same change as
the focus behaviour, and a regression in either would be hard to attribute.

### 8. The plain poster viewer is out of scope, and that is a product call

`_overlays.html.twig:61` is a full-screen poster with `alt=""`, no controls, and
no visible close affordance. A trap there would trap focus on nothing.

Its real problem is not focus: a keyboard user gets an unannounced image that
Escape happens to dismiss, and a screen reader user gets nothing at all. Fixing
that means giving it a name and a close button — which changes what is on the
screen, and is a `poster-library` decision rather than a focus fix. Left alone,
and left undeclared as a dialog, so Decision 2 excludes it without a special
case.

The preview at `gallery.html.twig:334` is the opposite and is fixed here: it is
already modal in every respect except the three attributes that say so.

### 9. Focus that falls out from under the user is caught

Tab-wrapping alone is not enough, because focus can leave the overlay without
anyone pressing Tab. `x-show` hides the preview's action row the moment
`preview.confirming` flips — and hiding a *focused* element hands its focus to
the document body. A keyboard user who presses "Use this poster" is standing on
exactly that element. From the body, the next Tab starts at the top of the
document, outside the overlay, which is the bug this change is removing.

This is the same failure `draw-the-disabled-state` fixed for `disabled`, in a new
guise, so it is answered the same way rather than left as an edge case: while an
overlay is open, focus arriving outside it is returned to it.

### 10. A second observer, not a shared one

The scroll lock's `MutationObserver` block is correct, subtle, and carries a long
comment explaining why it watches both `style` and `class` and why coalescing
into one `rAF` makes the cost affordable. The focus manager needs the same signal
but a different consumer — a stack rather than a boolean.

Two observers, then, rather than refactoring a working block to share one.
Watching `class` across the subtree means every `is-loaded` on a lazily revealed
poster schedules a pass in both; the lock's comment already argues that cost is
one comparison per frame, and doubling a negligible cost is a better trade than
editing the block that makes the page scroll correctly on iOS.

### 11. The capability map's own description is amended

`openspec/config.yaml` glosses `visual-design` as *"how things look, not what
they do."* That stopped being true on 2026-08-18, when *"An unavailable control
stays reachable and reports its state"* — tab order and assistive-technology
reporting, which are not how anything looks — landed there. This change adds
more of the same and amends the line rather than leaving the next reader to ask
the question again.

Keeping focus in `visual-design` also keeps one capability owning dialog
behaviour: it already holds *"Interactive elements respond to pointer and
focus"*, *"Dialogs and trays animate in and out"*, and the reachability
requirement this one extends. A separate `keyboard-navigation` capability would
be an honest name and a worse split.

## Risks / Trade-offs

**The test suite cannot prove any of this works.** → There is no JS runner in this
repo, so the tests are source-shape tripwires in the manner of
`DisabledStateTest`: every `[role="dialog"]` carries `tabindex="-1"`, the preview
carries its role and name, the manager is keyed on the attribute rather than a
hard-coded list. Behaviour needs a keyboard pass against the `:dev` image, listed
as an explicit task, before the change is archived.

**A focus trap that traps too well is worse than none.** A bug that pins focus
inside a dismissed overlay leaves the user with no way out at all. → The stack
pops on the same signal the scroll lock releases on, which is proven in
production; and the manager holds no state that survives an empty stack, so any
failure to pop degrades to the current behaviour rather than to a locked page.

**Decision 9's backstop could fight legitimate focus moves.** → It applies only
while the stack is non-empty, and only to focus landing outside the top overlay.
The one other thing in the app that listens on `focusin` is the tooltip, which
reads focus and never moves it.

**Two observers over the whole document.** → Accepted and argued in Decision 10.
If profiling ever shows it, the merge is mechanical.

**`aria-modal` carries the assistive-technology side alone until `inert` lands.**
→ Accepted. It is the attribute designed for the job and is already present on
nine of eleven overlays; support is broad. Decision 7 records the follow-up.

## Migration Plan

None. No data, no settings, no routes, no dependencies. The change is additive
markup plus one new block in `gallery.js`; reverting is reverting the commit.

## Open Questions

- Does the poster viewer get a name and a close button, as a `poster-library`
  change? Decision 8 says it should, and does not do it here.
- Does `inert` follow, on the back of teleporting every overlay to `<body>`?
  Decision 7 sketches it.
- The desktop overflow menu restores focus on Escape but not after choosing an
  entry or clicking outside. Left alone here; worth a look if menu behaviour is
  ever revisited.
