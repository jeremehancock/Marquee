## Context

The secondary navigation lives in one macro,
`secondary_links()` in `templates/partials/_nav_macros.html.twig`. It renders in
exactly two places: the desktop gallery toolbar
(`templates/gallery.html.twig:70`) and the mobile menu tray
(`templates/partials/_menu.html.twig:19`). The `application-shell` spec makes
that single source of truth a requirement, so a link added to the macro appears
in both placements with no further edits.

Each link is `<a class="btn nav-item">` wrapping a `.nav-ico` and a `.nav-label`.
The icons come from a small inline path map in the `ic()` macro in the same
file — `wall`, `import`, `orphans`, `logout` — kept there so the macros stay
self-contained. Poster Wall is the existing precedent for a new-tab destination:
`target="_blank" rel="noopener"`.

The tray's own footer already carries an external link to the project site; the
support link is a navigation entry, distinct from that chrome.

## Goals / Non-Goals

**Goals:**

- A Support Development entry in the secondary navigation, visible on desktop
  and on phones.
- It opens `https://getmarquee.now/#support` in a new tab.
- A matching Support Development section in the README.

**Non-Goals:**

- Making the support URL configurable. Like the project website URL, it is a
  fixed property of the product.
- Any in-app donation flow, payment integration, or nag prompt. This is one
  link out to the project site.
- A separate desktop-only or tray-only variant of the link.

## Decisions

**Add it to `secondary_links()` rather than to `_menu.html.twig` directly.**
Editing the tray template alone would put the link on phones only — the tray is
hidden on desktop — and would break the spec's single-source-of-truth rule for
secondary navigation. Going through the macro gets both placements from one
edit, which is what the shared macro exists for.

**Place it last in the macro, after Orphans.** The first three entries are
working tools; the support link is not part of the poster workflow, so it reads
better at the end of the group. Log out continues to be appended separately by
the tray, so it stays last overall in the tray.

**Add a `support` icon to the existing `ic()` path map.** A heart outline drawn
with the same 24×24 viewBox, `fill="none"`, `stroke="currentColor"` conventions
as the other four, so it inherits sizing and color with no CSS change. The
alternative — an image or icon-font dependency — would add a runtime asset for a
single glyph.

**Use `target="_blank" rel="noopener"`.** Same as Poster Wall. `rel="noopener"`
is required, not decorative: without it the opened page gets a handle on the
Marquee window through `window.opener`.

**Hard-code `https://getmarquee.now/#support`.** Consistent with how the project
website URL is treated elsewhere in the layout, and with the decision recorded in
the `update-project-domain` change.

## Risks / Trade-offs

- **The desktop gallery toolbar gains a fourth button and could get crowded on
  narrow desktop widths →** the toolbar already handles four items in the tray
  and the responsive rules collapse to the bottom tab bar below the breakpoint;
  verification includes a look at mid-width desktop layout, and the label can be
  shortened to "Support" if it wraps.
- **The `#support` fragment depends on that section existing on the new site →**
  if the anchor is missing the link still lands on the homepage, which is a
  soft failure rather than a broken link.
