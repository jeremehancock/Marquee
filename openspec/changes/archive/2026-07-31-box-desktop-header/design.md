## Context

The shared layout ([templates/layout.html.twig](templates/layout.html.twig))
renders `<header class="topbar">` above `<main class="container">` and
`<footer class="footer">`. Both `.container` and `.footer` are centred at
`max-width: 960px` with `24px` of horizontal padding; `.topbar` is not — it is a
flex row with `padding: 14px 24px`, a `--surface` background and a
`border-bottom`, so its contents sit out at the viewport edges while everything
below them is centred.

The reference for the intended look is the project's own landing page at
`getmarquee.now`, whose header is:

```css
.site-header  { position: sticky; top: 0; background: rgba(28,30,36,.82);
                backdrop-filter: blur(12px); border-bottom: 1px solid transparent; }
.site-header.is-stuck { border-bottom-color: var(--border); }
.wrap         { max-width: var(--wrap); margin-inline: auto; padding-inline: var(--pad); }
```

That is: a full-bleed bar tinted at the page background colour (`--bg` is
`#1c1e24` in both codebases, so the 82% overlay reads as the page itself), a
hairline underneath, and the contents constrained to the same wrap the page body
uses. The bar spans the viewport; only its contents are boxed.

`public/assets/app.css` is a single hand-written stylesheet with one responsive
breakpoint at 640px. Its convention is that base rules describe the desktop
presentation and a single `@media (max-width: 640px)` block at the **end** of the
file overrides them for phones. `.topbar` itself is not touched by that mobile
block — only its children are.

There is no build step for CSS and no visual-regression harness, so correctness
is confirmed by screenshot.

## Goals / Non-Goals

**Goals:**

- On desktop widths, the header's brand and navigation line up with the content
  column's left and right edges.
- The header matches the landing page's treatment: full-bleed, page-coloured,
  one hairline beneath it.
- Phone rendering is provably untouched.

**Non-Goals:**

- Changing the header's markup, its contents, or the navigation behaviour.
- Adopting the landing page's *behaviour* — sticky positioning, the backdrop
  blur, and the scroll-activated border are all deferred. Blur and translucency
  only do visible work behind a sticky bar, and the scroll state needs a
  listener; none of that is needed to match the look in a static view.
- Changing the content column width, or touching the standalone poster wall.

## Decisions

**Box the contents, not the header.** An earlier revision of this change boxed
the `.topbar` element itself into a centred, bordered, rounded card. That was
rejected on sight against the landing page: the landing header is unmistakably a
full-width bar, and the card version also degraded awkwardly between 641px and
959px, where the "card" touched both viewport edges while keeping a gap above it.
Constraining the contents instead is what the reference does, and it has no such
intermediate state.

**Constrain the contents with a padding `max()` rather than an inner wrapper.**
The landing page uses a `.wrap` child, which here would mean adding
`.topbar__inner` to the layout template and moving the flex row onto it. Instead:

```css
padding-inline: max(24px, calc((100% - 960px) / 2 + 24px));
```

Percentage padding resolves against the containing block — the body, i.e. the
viewport — so the first term is the gutter that centres a 960px column and the
`+ 24px` reproduces `.container`'s own padding, putting the brand exactly where
the content starts. Below 960px the calc goes negative and `max()` collapses it
to a plain `24px`, which is what `.container` uses at those widths, so the two
stay aligned with no second breakpoint. This keeps the change CSS-only: no
template edit, no re-parenting of the flex row, and therefore no way for the
phone layout to shift. The wrapper remains the better move if the header ever
needs internal structure of its own; for one alignment rule it is not worth the
markup churn.

**Repeat `960px` literally rather than introducing a custom property.**
`.container` and `.footer` already hardcode it, and converting just this one
call site would leave the codebase half-converted.

**Apply it in a `@media (min-width: 641px)` block rather than editing the base
`.topbar` rule.** The file's usual pattern is base-then-mobile-override, but that
would mean changing the shared base rule and undoing it for phones. A
desktop-only block leaves the base rule byte-for-byte intact, so the phone
presentation cannot regress by construction. The two queries are disjoint
(`≤640` / `≥641`). A comment on the block explains the deviation so the next
reader doesn't "fix" it back into the mobile block.

## Risks / Trade-offs

- **`padding-inline: max(...)` is less obvious than a wrapper div** → It carries
  a comment naming what each term does, and the alternative was judged more
  invasive. Anyone adding structure to the header should switch to the wrapper.
- **Desktop and phone headers now differ in background (`--bg` vs `--surface`)**
  → Deliberate: the phone bar is fixed chrome above a scrolling list and earns
  its surface, and holding the phone rendering constant was an explicit
  constraint on this change.
- **The 641px boundary drifts from the 640px mobile block if either is edited
  later** → The new block's comment names its counterpart, and the two are
  complementary halves of the single breakpoint the file already uses.

## Migration Plan

None — a CSS-only presentation change with no persisted state, no cache key, and
no markup contract. The service worker ([public/sw.js](public/sw.js)) caches
`/assets/*` by the versioned URL emitted by the `asset()` helper, so a released
build serves the new stylesheet without a cache-name bump. Rollback is reverting
the commit.

## Open Questions

None. Sticky positioning with the landing page's blur and scroll-activated
border is a deliberate non-goal above, available as a follow-up if wanted.
