## Context

The gallery page renders three stacked control regions above the poster grid:
the page header (`layout.html.twig`), the category tabs, and the toolbar
(`gallery.html.twig`). On a phone all three have been designed deliberately —
the tabs are a fixed bottom bar, the toolbar is pinned at the top, and the
secondary actions live in an overflow tray. On a pointer/desktop screen none of
them are pinned, and the toolbar carries search, the inline sort control, and
four secondary action buttons in a single flex row.

Two measurements drive this design.

**The toolbar already overflows.** At the 960px content column the content box is
912px wide. Search reserves `flex: 1 1 240px`, the sort group runs roughly 200px,
and the four secondary buttons roughly 530px — about 994px of demand. `.toolbar`
is `flex-wrap: wrap`, so today the actions silently drop onto a second row. The
desktop toolbar is a two-row block, which is both the layout complaint and the
reason pinning it as-is would be expensive in vertical space.

**Icon-and-label buttons do not fit beside the brand.** With the icons enabled,
full labels measure roughly: Poster Wall 146px, Import from Plex 186px, Orphans
116px, Support Development 210px — about 682px with gaps, and about 882px once
the brand and Log out are included. That leaves ~30px of slack in a 912px column,
and `SITE_TITLE` is user-configurable ([`AppConfig::32`]) so the brand's width has
no upper bound.

Existing constraints this design must not disturb:

- The z-index ladder documented at `app.css` above `.modal`: pinned chrome 30,
  bottom tab bar 40, trays 50, dialogs 55, fullscreen viewer 60.
- The scroll lock (`.is-overlay-open body { position: fixed }`) that pins the page
  beneath any open overlay.
- The mobile toolbar's own pinning, including the negative side margins that bleed
  it past `.container`'s gutters.

## Goals / Non-Goals

**Goals:**

- Keep search, sort, and the category tabs reachable at any scroll position on a
  pointer/desktop screen.
- Give the secondary actions a home that is not the poster grid's toolbar, and
  present them consistently with each other, including Log out.
- Leave every phone behaviour bit-identical.
- Ship as template and CSS changes only.

**Non-Goals:**

- Widening the 960px content column or changing poster density. That is a
  separate, larger question and is deliberately excluded.
- Pinning the page header itself.
- Changing pagination, infinite scroll, the card hover overlay, or adding
  keyboard shortcuts.
- Converting Import or Orphans into desktop overlays. They keep navigating.

## Decisions

### A single wrapper element is pinned, not the tabs and toolbar independently

`.tabs` and `.toolbar` are adjacent siblings. Giving both `position: sticky;
top: 0` would pin them to the same coordinate, so the toolbar would slide
underneath the tabs as the page scrolls. Offsetting the second by the first's
rendered height would work but introduces a magic number that has to be kept in
sync with the tabs' padding and font size — precisely the coupling the existing
mobile comment calls out as the reason the mobile toolbar is `sticky` rather than
`fixed`.

Instead both are wrapped in a single `.gallery-head` element which is itself the
sticky one. No height constant exists to drift.

*Alternative considered:* pinning only `.toolbar`, leaving the tabs to scroll.
Rejected because switching category is the most frequent navigation action in the
gallery, and a phone already keeps the tabs permanently on screen — leaving them
out would keep desktop behind mobile on exactly the interaction this change
exists to fix.

### The wrapper opts out of layout on phones with `display: contents`

A sticky element can only stick within its containing block. `.toolbar` is
*already* sticky on phones; placing it inside a wrapper that is only as tall as
the tabs plus the toolbar would collapse its sticky range to that wrapper's box,
so it would unpin after roughly 90px of scrolling. This is the single most likely
way to regress the phone experience while touching only desktop CSS.

`.gallery-head { display: contents }` inside the existing `max-width: 640px`
block removes the wrapper from layout and from the box tree entirely, restoring
today's phone rendering exactly. The wrapper is a semantically empty `<div>`, so
the historical accessibility problem with `display: contents` — elements losing
their implicit ARIA semantics — does not apply here.

*Alternative considered:* rendering the wrapper only for desktop via a Twig
conditional. Rejected: the server cannot know the viewport, and the existing
responsive strategy in this codebase is CSS-only.

### The pinned block carries the background and the separating rule

`.toolbar` and `.tabs` have no background of their own; posters would scroll
straight through the pinned block without one. The mobile rule's comment is
explicit that its `background` is load-bearing rather than cosmetic, and the same
applies here.

Unlike the mobile case, no negative side margins are needed: the poster grid sits
inside `.container`'s padding box, so nothing renders in the 24px side gutters
that could show through beside the block.

`.tabs` currently owns a `border-bottom` that separates it from the toolbar.
Inside a single pinned block that rule reads as an internal divider rather than
the block's edge, so it moves to `.gallery-head`'s bottom edge — matching how the
page header is already described in `application-shell` ("separated from the
content by a single rule along its bottom edge").

### The pinned block reuses tier 30

The existing ladder already reserves the lowest tier for pinned page chrome that
must cover the grid and be covered by every overlay. The desktop block has
exactly those requirements, so it takes tier 30 rather than introducing a new
one. The ladder's comment, which currently names tier 30 as "the pinned mobile
toolbar", is updated to describe the pinned gallery head on both form factors.

The scroll lock needs no changes: `.is-overlay-open body { position: fixed }`
already stops the document scrolling beneath an overlay, and the pinned mobile
toolbar has been living with that combination since it shipped.

### Secondary navigation moves into the shared layout, not a gallery-specific header region

`secondary_links()` moves from `gallery.html.twig`'s `.toolbar__actions` into
`layout.html.twig`'s existing `.topnav`. The consequence is that it renders on
every page that draws the header, not only the gallery.

This is treated as intended rather than incidental. `/plex` and `/orphans`
currently offer no navigation on desktop beyond a "Back to gallery" link, so
moving between them requires a detour through the gallery. The phone already
behaves the way this change produces, because the overflow tray lives in the
layout. Desktop reaches parity.

Two details follow from it. The login page already overrides `{% block nav %}` to
empty, so it is unaffected. And on `/plex` and `/orphans` one of the actions now
points at the page being viewed, so the current destination is marked with
`aria-current="page"` and a non-interactive treatment, following the pattern the
category tabs already use.

### Short labels are a second span, not a different link set

Full labels do not fit (see Context). Shortening to Wall / Import / Orphans /
Support brings the group to roughly 454px and the whole header to roughly 718px,
which holds up against a long `SITE_TITLE`.

The gallery tabs already solve this exact problem: `tab__text` and
`tab__text--short` render both strings, CSS chooses one, and the full category
name stays as the link's accessible name. The nav links follow that precedent, so
the header shows "Import" while the phone tray keeps "Import from Plex" and
assistive technology always hears the full name.

*Alternative considered:* two separate macros, or passing a `short` flag.
Rejected — `secondary_links()` is required by `application-shell` to be a single
source of truth shared between the header and the tray, and duplicating the link
set would undermine that.

### A narrower band falls back to icons alone

Between 641px and the width where labelled buttons fit, even the short labels
(~656px with the brand) exceed the available column — at a 700px viewport the
content box is 652px. The toolbar absorbed this today by wrapping; a header row
has no equivalent room.

In that band the labels are hidden and the buttons render as icons alone, relying
on the custom tooltip system already required by `application-shell`. The
accessible names are unaffected because they do not come from the visible label.

The changeover is specified as approximately 900px and is expected to be tuned
against a real browser during implementation — the figures above are derived from
nominal character metrics, not measured text.

*Alternative considered:* raising the overflow menu's breakpoint from 640px so
the tray covers this band. Rejected: the tray is a bottom sheet with a drag
handle and app-style dismissal, designed for touch, and would be an odd fit in an
800px-wide desktop window.

### Log out becomes the button form of its existing macro

`logout_link(as_button)` already renders both a plain `<nav><a>` link and an
icon-and-label `.btn.nav-item`; the tray uses the latter. The header switching to
`logout_link(true)` leaves the plain branch with no callers, so the parameter and
that branch are removed and the macro collapses to one form.

### The page header is still not pinned

`application-shell` already states that the topbar is deliberately not pinned,
with a phone-specific rationale about a third pinned bar costing more viewport
than it is worth. That decision now applies on desktop too, for a comparable
reason: the pinned gallery head is the surface that earns its space, and pinning
the header as well would put roughly 165px of permanent chrome on a laptop
viewport while requiring the two to agree on the header's rendered height.

The accepted consequence is that the secondary actions scroll out of view. This
is not a regression — they scroll away today as well — but it becomes more
noticeable once the controls beside them stop moving.

## Risks / Trade-offs

- **The wrapper silently breaks the phone's pinned toolbar** → `display: contents`
  in the existing `max-width: 640px` block, plus an explicit verification step on
  a narrow viewport that the mobile toolbar still pins through a full scroll of
  the grid rather than releasing early.

- **The chosen icon-only breakpoint is derived from estimated text widths** → the
  figures come from nominal character metrics, not measurement. Implementation
  verifies the labelled row against a long `SITE_TITLE` at the content column
  width and adjusts the breakpoint upward if the row crowds.

- **Permanent chrome costs vertical space on short laptop screens** → the pinned
  block is two compact rows (~100px) rather than the three the toolbar currently
  occupies when it wraps, so a scrolled gallery shows more posters than it does
  today, not fewer.

- **Icon-only buttons are not self-evident, particularly Orphans** → confined to
  an intermediate width band, backed by the existing tooltip system, with full
  accessible names retained. Wide screens, the common desktop case, keep labels.

- **Secondary actions become unreachable mid-scroll** → accepted deliberately;
  they behave no differently from today, and they are deliberate destinations
  rather than controls used while browsing.

- **Navigation appearing on `/plex` and `/orphans` is a visible behaviour change
  on pages this change is not otherwise about** → the current destination is
  marked rather than offered as a live self-link, and the behaviour matches what
  the phone tray already does on those pages.
