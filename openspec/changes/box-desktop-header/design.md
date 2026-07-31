## Context

The shared layout ([templates/layout.html.twig](templates/layout.html.twig))
renders `<header class="topbar">` above `<main class="container">` and
`<footer class="footer">`. Both `.container` and `.footer` are centred at
`max-width: 960px`; `.topbar` is not — it is a flex row with
`padding: 14px 24px`, a `--surface` background and a `border-bottom`, so it
stretches the full viewport width.

`public/assets/app.css` is a single hand-written stylesheet with one responsive
breakpoint at 640px. Its convention (documented in a comment at the top of the
block) is that base rules describe the desktop presentation and a single
`@media (max-width: 640px)` block at the **end** of the file overrides them for
phones, so mobile rules win at equal specificity. `.topbar` itself is not
currently touched by that mobile block — only its children (`.menu-btn`,
`.topnav__desktop`, `.brand`) are.

There is no build step for CSS and no visual-regression harness; the PHPUnit
suite renders the layout for markup assertions only
([tests/Functional/ApplicationShellTest.php](tests/Functional/ApplicationShellTest.php),
[tests/Functional/GalleryTest.php:517](tests/Functional/GalleryTest.php#L517)),
so correctness here is confirmed by eye in a browser.

## Goals / Non-Goals

**Goals:**

- On desktop widths, the header occupies the same 960px column as the content,
  with its brand and navigation flush with the content's left and right edges.
- The header reads as a discrete box — bordered on all sides, rounded, separated
  from the viewport edge — not as a bar clipped from a full-width strip.
- Phone rendering is provably untouched.

**Non-Goals:**

- Changing the header's markup, its contents, or the navigation behaviour
  (desktop Log out link, mobile menu button and tray) in any way.
- Making the header sticky, or changing the content column width itself.
- Touching the poster wall, which renders standalone without the shared layout.

## Decisions

**Box the header itself, not just its contents.** The alternative — keeping the
full-bleed surface and border while constraining an inner wrapper to 960px — was
considered and rejected: it would still paint a bar across the viewport, which
is the thing that makes the header look detached from the page. Boxing the
element means the page background shows on both sides of the header, matching
how the content and footer already float in that background. This requires no
markup change, since `.topbar` is already the only element to constrain.

**Apply the boxing in a `@media (min-width: 641px)` block rather than editing
the base `.topbar` rule.** The file's usual pattern is base-then-mobile-override,
but that pattern would mean rewriting the shared base rule and then undoing it
(background bleed, square corners, no margin, bottom-only border) inside the
mobile block — four properties that must each be reverted correctly for phones
to look unchanged. A desktop-only block instead leaves the base `.topbar` rule
byte-for-byte as it is, so the phone presentation cannot regress by
construction. The two queries are disjoint (`≤640` / `≥641`), so nothing about
the existing mobile block changes. A comment on the new block explains the
deviation so the next reader doesn't "fix" it back into the mobile block.

**Match the content column by reusing `max-width: 960px` with the same 24px
horizontal padding.** `* { box-sizing: border-box }` is set globally, so a
960px-wide header with 24px padding puts its inner edges exactly where
`.container`'s 24px padding puts the content's. Alignment therefore follows from
using the same two numbers, not from a separate calculation. They stay literal
values rather than becoming a new custom property: `.container` and `.footer`
already repeat `960px` literally, and introducing a variable for just this one
change would leave the codebase half-converted.

**Border on all sides plus a radius, and a small top margin.** `border-bottom`
becomes a full `border`, since a box with only one edge drawn reads as a
leftover bar. The top margin keeps the rounded top corners from colliding with
the viewport edge; the footer already uses `margin: 24px auto 0` for the same
reason at the other end of the page, so the header mirrors it.

## Risks / Trade-offs

- **The 641px boundary drifts from the 640px mobile block if either is edited
  later** → The new block's comment names its counterpart, and the two are
  complementary halves of the same single breakpoint the file already uses.
- **A tablet-width viewport (641–959px) now gets a header narrower than the
  viewport but not visibly "boxed", since 960px exceeds the viewport** →
  `max-width` degrades correctly on its own: below 960px the header is full
  width, exactly like `.container`, so header and content still agree. The
  rounded corners and side margins simply have no room to show, which is the
  same way the content column behaves there today.
- **The header is no longer a visual anchor spanning the window** → Accepted;
  this is the point of the change, and the header is not sticky, so nothing
  depends on its width.

## Migration Plan

None — a CSS-only presentation change with no persisted state, no cache key,
and no markup contract. The service worker
([public/sw.js](public/sw.js)) caches `/assets/*` by the versioned URL emitted
by the `asset()` helper, so a released build serves the new stylesheet without a
cache-name bump. Rollback is reverting the commit.

## Open Questions

None.
