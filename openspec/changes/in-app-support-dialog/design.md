## Context

Support Development is one of six entries in the shared secondary navigation. It
is rendered by `item()` in `templates/partials/_nav_macros.html.twig` — the single
source of truth for both placements — as an anchor to
`https://getmarquee.now/#support` with `target="_blank"`. That anchor appears in
three rendered locations, all fed by the same two macros:

| Location | Rendered by |
| --- | --- |
| Desktop header ⋯ panel | `layout.html.twig:97` → `nav.overflow_links()` |
| Phone actions tray | `_menu.html.twig:48` → `nav.overflow_links()` |
| (bar group, not Support) | `nav.bar_links()` |

All three sit inside — or, for the teleported tray, remain scoped to — the
`x-data="{ menuOpen: false, moreOpen: false }"` scope declared on `.topnav` in
`layout.html.twig:52`. That scope is the one piece of Alpine state present on
**every** page that draws navigation, independent of any page's own root
component (`galleryUI`, `orphansPage`, …).

The content to port is the marketing site's support section
(`~/github/Marquee-Site/index.html:405-432`): a heart mark, an `<h2>Support
development</h2>`, one paragraph, and one accented button to
`https://www.buymeacoffee.com/jeremehancock` labelled "Hard drive fund".

Three constraints shape everything below.

1. **The app already has "dialog on desktop, tray on phone".** `.modal` is
   centred and closes with its own `×` above 640px; the `@media (max-width: 640px)`
   block at the end of `app.css` reshapes `.modal`/`.modal__panel` into a bottom
   sheet and turns the `.sheet__grip` back on. The delete confirmation and the
   change-poster dialog already ride it.
2. **Everything that services an overlay is document-level delegation.** The drag
   gesture (`.sheet__grip, .sheet__head, .modal__head` → `gallery.js:259`), the
   scroll lock (`gallery.js:321`), and the focus manager (`OVERLAY = '.sheet,
   .modal, .viewer'`, `gallery.js:420`, keyed on `[role="dialog"]`) all find their
   subjects by selector. None of them needs to be told a new overlay exists.
3. **The header is a `backdrop-filter` surface, so it is a containing block for
   `position: fixed` descendants.** `_menu.html.twig` documents this at length: it
   is why the phone tray is wrapped in `<template x-teleport="body">`. `.modal` is
   `position: fixed; inset: 0`, so the same trap applies exactly.

## Goals / Non-Goals

**Goals:**

- Support Development opens over the current page, from either placement, on
  every page that renders navigation.
- One overlay, taking the two presentations the shared component already gives.
- No new overlay system, no new JS module, no fetch, no route, no PHP.
- The entry keeps its icon-and-label form, its full accessible name, and its
  position last-but-one in the overflow group.

**Non-Goals:**

- A `/support` page. There is nothing on it that is not in the overlay.
- Payment inside Marquee. The Buy Me a Coffee button still opens a new tab.
- Removing the marketing site's own support section — this repo does not own it.
- Reworking `item()` into a general "opens an overlay" nav primitive. One entry
  needs it; a second would be the time to generalise.
- Touching the in-app update notice, the footer's `getmarquee.now` link, or
  anything else that leaves the app.

## Decisions

### 1. `.modal`, not a bespoke pair of components

**Chosen:** one `.modal` root, one `.modal__panel` at its default 440px, carrying
the `.sheet__grip` every modal in this app carries. Not `--narrow`: that variant
is 380px and sized for a confirmation — one line of question and two buttons —
and it sets this paragraph to six lines with a ragged right. Verified against
both at 1280px before choosing.

The request was "modal on desktop, tray on mobile", and that is not two things
here — it is what `.modal` already *is*. The mobile block reshapes the panel,
restores the grip, and re-points the open/close transform; the gesture handler
already claims `.modal__head` as a drag target. Building a separate `.sheet` for
narrow screens and a `.modal` for wide would mean two copies of the same content,
two `x-show` bindings, and a width query in Twig deciding which to render —
against a stylesheet that has spent its whole life keeping that decision in CSS.

*Rejected:* rendering both a `.sheet` and a `.modal` and hiding one per width.
Duplicates the copy, doubles the dialogs the focus manager sees (both would match
`OVERLAY`, and `display: none` on the wrong one is a media-query race the manager
has no reason to arbitrate).

*Rejected:* a native `<dialog>`. Nothing else in the app uses one; it would bring
its own backdrop, its own focus trap competing with `gallery.js`, and its own
top-layer stacking outside the documented z-index ladder (tab bar 40, trays 50,
dialogs 55, viewer 60).

### 2. State lives on the existing `.topnav` scope

**Chosen:** add `supportOpen: false` to `x-data` on `.topnav` in
`layout.html.twig`.

That scope is already shared by both placements of the entry, already present on
every navigation-rendering page, and already the mechanism by which the teleported
phone tray stays reactive. `supportOpen` is a third boolean beside `menuOpen` and
`moreOpen`, in the same shape, at the same scope.

This is deliberately **not** the mechanism Import/Orphans/Settings use. Those are
anchors carrying `data-import` / `data-orphans` / `data-settings`, intercepted by
a document-level click handler (`gallery.js:695-701`) that dispatches a
`gallery:*` event — because their trays live in the *gallery's* Alpine root and
exist on no other page, so the anchor must still navigate as a fallback. The
support overlay has no fallback to arrange: its content is static, it is declared
in the layout, and it is therefore present wherever the entry is. Routing it
through the data-attribute bridge would add an interception layer to solve a
problem it does not have, and would leave a live external href behind as the
"fallback" that this change exists to remove.

### 3. The entry becomes a `<button>`, via a new branch in `item()`

**Chosen:** `item()` grows an `action` argument. When set, the entry renders as
`<button type="button" class="btn nav-item" @click="...">` carrying the identical
icon/label/short-label body, the identical `aria-label`, and the identical
`data-tooltip` / `data-tooltip-collapsed` pair.

It must not stay an anchor. An `<a>` with no `href` is not focusable and is not a
button to assistive technology; an `<a href="#">` announces a destination and
dirties the URL. The visible presentation is unchanged because `.btn.nav-item`
already styles both — `logout_link()` in the same file is a form-submit control
wearing the same classes, so a non-anchor entry in this group is established
practice rather than a new idea.

Both existing placements dismiss themselves on any click inside
(`layout.html.twig:96`, `_menu.html.twig:46`), so the ⋯ panel and the actions tray
each close on their own as the overlay opens — matching how Settings behaves from
the tray today.

They do need one thing added, which this design first assumed they did not. Alpine
hides the panel on the flush *after* the handler, and hiding a focused element
hands its focus to `<body>`; the focus manager reads `document.activeElement` on
the next frame to decide where to return focus when the overlay closes, and an
origin chain rooted at `<body>` is explicitly the case it declines to restore. So
each placement now moves focus to its own trigger before closing —
`$refs.moreTrigger.focus()` on the ⋯ panel, `$refs.menuTrigger.focus()` on the
tray — giving the manager a live element to remember. Harmless for the entries
that navigate, and it incidentally fixes the same gap for the Import, Orphans and
Settings trays opened from the phone tray.

### 4. `overflow_current_class()` drops `'support'`

`{{- current in ['settings', 'support'] ... -}}` exists to keep the ⋯ control
marked current when the page being viewed is one the panel holds. Support is no
longer a page one can be viewing, so `'support'` is dead in that list — and worse
than dead: leaving it invites a future `nav_current = 'support'` that would mark
the control current for an overlay. The `key == current` branch of `item()` is
likewise unreachable for this entry and stays unreachable by the same removal.

### 5. Teleport to `<body>`, for the reason `_menu.html.twig` already documents

The overlay is declared inside `.topnav` — that is where its state lives — and
`.topnav` is inside `<header class="topbar">`, which carries `backdrop-filter`.
A `position: fixed` overlay declared there resolves against the header's box and
renders squashed into the height of the bar. `<template x-teleport="body">` moves
the rendered element out while keeping it in scope, exactly as the phone tray
does. This will look correct on desktop *without* the teleport, which is what
makes it worth writing down.

### 6. Its own partial, `templates/partials/_support.html.twig`

Included from `layout.html.twig` beside `_menu.html.twig`, inside the `.topnav`
scope. Not folded into `_overlays.html.twig`: that file is included only by pages
whose Alpine root mixes in the overlay component (`galleryUI`, `orphansPage`) and
binds against that component's state (`confirm`, `sheet`, `viewer`, `toast`). The
support overlay binds against the layout's nav scope and must appear on pages that
include no `_overlays.html.twig` at all.

### 7. Presentation: `.support-ask` block inside the panel

New CSS, ported in spirit from the site's `.support` block but expressed in this
app's tokens (`--accent`, `--surface-2`, `--muted`, `--radius-*`). Centred column:
heart mark in a soft accent-tinted tile, `<h2>`, one `.stats`-toned paragraph, one
`.btn.btn--accent` sized to the full panel width on a phone the way the mobile
block already sizes `.modal__actions .btn`. The heading is `<h2>` inside
`.modal__head` alongside the existing `.modal__close`, matching the confirm
dialog's structure, so `aria-label` on the panel and the visible heading agree.

The heart comes from `icons.icon('support')` — the same glyph the nav entry wears,
at a larger size — not the site's own path. One icon set, one drawing style.

### 8. What the tests have to become

- `ApplicationShellTest` asserts `Support Development` appears in both placements
  and that the header carries `aria-label="Support Development"`. Those hold. Any
  assertion that it is an anchor, or names the `getmarquee.now/#support` href,
  becomes an assertion that it is a button that opens the overlay.
- `GalleryTest:390` iterates `['/wall', '/plex', '/orphans', 'getmarquee.now/#support']`
  as hrefs expected in the markup. Drop the last, and assert the overlay's
  presence instead.
- `DialogFocusTest` pins that every declared dialog carries `tabindex="-1"`. The
  new panel is a declared dialog and must satisfy it; add it to whatever
  enumeration that test walks.
- One new assertion is worth having and does not exist today: that no template
  links to `getmarquee.now/#support` any more. It is the thing being removed, and
  a stray copy would be invisible.

## Risks / Trade-offs

- **A new overlay declared without `role="dialog"` + `tabindex="-1"` is silently
  unmanaged** → The panel declares both, alongside `aria-modal="true"` and an
  `aria-label`, per the standing project rule. `DialogFocusTest` catches the
  half-declared case.
- **Forgetting the teleport ships a dialog that renders correctly on the
  developer's desktop and squashes into the header bar on a phone** → Decision 6
  states it; the partial carries the same warning comment `_menu.html.twig` does.
  Verify at a narrow viewport, not only at desktop width.
- **The support ask is now behind a click instead of a page** → That is the point,
  but it does mean the ask is only ever seen by someone who goes looking. It was
  already only ever seen that way; the change removes a page load from the path,
  it does not remove a prompt that was being shown.
- **Copy drifts from the marketing site** → Accepted. The two are independently
  edited already, and the site's section is not this repo's to keep in step. The
  overlay's copy is now Marquee's own.
- **`.modal` on a phone is a sheet, and a sheet drags to dismiss from its head**
  → The support panel's head holds the `×` control. That is already true of the
  confirm dialog, so the gesture handler's existing tap-versus-drag threshold
  covers it; no new interaction.

## Migration Plan

None required. No data, no settings, no routes, no stored state. Deploying is
shipping the templates and the stylesheet; rolling back is reverting them. The
navigation entry keeps its name and both of its positions, so nothing a user has
learned changes location.

## Open Questions

None blocking. One judgement call recorded rather than asked: the overlay holds
only the Buy Me a Coffee button, not also a GitHub star / issues link. The site
puts those in its own nav and footer, and the requirement being ported is the
support ask — an overlay that asks for money and also asks for a star asks for
two things and gets neither.
