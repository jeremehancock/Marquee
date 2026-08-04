## Context

Two independent mobile-layout refinements that happen to touch the same screen,
shipped together because both are small and both are phone-only presentation.

**Current state.** The topbar (`templates/layout.html.twig`) renders a brand
lockup and, on narrow screens only, a hamburger button that opens the menu tray
(`templates/partials/_menu.html.twig`) as a bottom sheet. The topbar is not
pinned. On the gallery page, `.tabs` becomes a fixed bottom tab bar on mobile and
`.toolbar` — reduced to search plus a sort trigger — scrolls away with the
content.

**Why the glyph is wrong.** None of the menu's entries navigate in place on a
phone. `gallery.js` intercepts the Import and Orphans links on touch devices and
opens them as trays over the gallery; Poster Wall and Support Development are
`target="_blank"`; Log out is an action. The hamburger conventionally signifies a
navigation drawer, so the glyph promises something the menu does not do. The
bottom sheet is the right surface for an actions menu — the glyph is what needs
to change.

**Constraints.**
- Overlay layering is one documented ordered scale in `public/assets/app.css`
  (tab bar 40, trays 50, dialogs 55, viewer 60). Anything new must slot into it
  and the explanatory comment must stay accurate.
- The mobile overrides live in a single `@media (max-width: 640px)` block placed
  last in the stylesheet so it outranks the base component rules at equal
  specificity. New mobile rules belong there, not in a mid-file media query.
- `.container` supplies `padding: 20px 14px` on mobile.

## Goals / Non-Goals

**Goals:**
- The menu trigger's glyph matches what the menu contains.
- The spec describes the menu as what it is — an actions menu — rather than as
  navigation.
- Search and the sort trigger stay reachable at any scroll position on a phone.
- Zero change to desktop presentation and to all existing behavior.

**Non-Goals:**
- Pinning the topbar. Deliberately excluded — see Decisions.
- Changing the menu's surface (it stays a bottom sheet), its contents, its
  dismissal contract, or the link macros.
- Moving the menu trigger into the toolbar or the bottom tab bar.
- Any PHP, routing, data, or JavaScript change.

## Decisions

### Overflow glyph, not a drawer

The affordance mismatch has two possible fixes: change the surface to match the
hamburger (a slide-in drawer), or change the glyph to match the surface. We chose
the glyph.

A drawer would have meant a second overlay presentation, an X-axis variant of the
shared drag-dismiss helper in `gallery.js` (which is Y-axis only and keyed on
`.sheet__grip` / `.sheet__head`), and an exception to the "App-style tray
dismissal" requirement that currently binds every tray to one behavior. All of
that to arrive at a surface that is *less* conventional for an actions menu than
the bottom sheet already in place. Changing three `<path>` commands to three
`<circle>`s achieves the same coherence for none of the cost.

Horizontal ellipsis ("meatball") rather than vertical ("kebab"): the button sits
in a horizontal bar next to a horizontal brand lockup, and the horizontal form
reads as a peer of the other topbar content.

### The topbar stays unpinned

A pinned topbar would keep the menu reachable, but a phone would then carry three
permanently-visible bars — topbar, toolbar, tab bar — costing roughly 110px of
vertical space before any poster is drawn. The menu holds occasional actions;
search and sort are used continuously. Pinning the toolbar and letting the topbar
scroll spends the viewport budget on the controls that earn it.

Alternative considered and rejected: moving the menu trigger into the sticky
toolbar on mobile. It would make everything reachable from one bar, but the
toolbar exists only on the gallery page, so `/plex` and `/orphans` would still
need the topbar trigger — two placements to keep in sync for a marginal gain.

### Sticky toolbar via `position: sticky`, not `fixed`

`sticky` keeps the toolbar in normal flow, so it occupies its own space at the
top of the page and needs no compensating padding on the content below it — which
`fixed` would require, and which would have to be kept in sync with the toolbar's
rendered height. `.tabs` uses `fixed` because it is pinned to the bottom against
the document's natural flow; the toolbar is not.

Three details the rule cannot omit:

- **Opaque background.** `.toolbar` has no background today. Pinned, posters
  would scroll visibly through it. It takes `var(--bg)` to match the page.
- **Full-bleed.** An opaque toolbar inside `.container`'s 14px side padding
  leaves 14px channels at each edge where posters show through as they pass.
  Negative side margins with matching padding (`margin: 0 -14px 8px; padding:
  8px 14px`) let it span the viewport while its contents stay on the content
  grid. Preferred over restructuring the markup to hoist the toolbar out of
  `.container`.
- **Layering tier 30.** Above the poster grid, below the bottom tab bar (40) and
  every overlay. The scale's explanatory comment gains this tier.

### No JavaScript changes

Two existing mechanisms could plausibly have conflicted; neither does.

The overlay scroll-lock pins the body with `position: fixed` while any overlay is
open, which collapses document scrolling — sticky then has nothing to resolve
against, and the tray backdrop at z-50 covers the toolbar regardless. Infinite
scroll swaps only `#results`; the toolbar is never replaced by a no-reload update
(as `gallery.html.twig` already notes), so the sticky element is stable across
updates.

## Risks / Trade-offs

- **iOS Safari, keyboard up** → `position: sticky` resolves against the layout
  viewport, not the visual one, so with the search field focused the pinned bar
  can sit under the URL bar. Accepted: iOS scrolls the focused input into view,
  so it self-corrects, and the alternative (visual-viewport JavaScript) is far
  more machinery than the wart justifies.
- **Vertical space** → the pinned toolbar permanently costs ~56px of an already
  short phone viewport. Accepted deliberately; it is why the topbar is *not* also
  pinned.
- **Archiving reorders the spec** → the renamed requirement is relocated within
  `openspec/specs/application-shell/spec.md` at archive time rather than staying
  in place. Cosmetic, and confirmed harmless by rehearsing the archive against a
  scratch copy; no content is lost.
- **Stale cross-reference** → "App-style tray dismissal" names the menu in its
  requirement text, so renaming the menu requirement without updating it would
  leave the spec referring to a requirement that no longer exists. Mitigated by
  including that requirement in the delta.
- **Glyph discoverability** → an overflow glyph is marginally less universally
  recognised than a hamburger. Mitigated by the unchanged `aria-label="Menu"` and
  by the trigger keeping its established position in the topbar.
