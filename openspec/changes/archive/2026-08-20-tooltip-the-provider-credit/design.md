## Context

Marquee has one tooltip. `app.js` creates a single `.tooltip` element, positions
it, and drives it from a delegated `[data-tooltip]` listener on the document —
so a host does not register, it just declares. The `Consistent custom tooltips`
requirement in `application-shell` already forbids the browser's native `title`
tooltip application-wide.

Four elements were never converted. `templates/partials/_attribution.html.twig`
renders the provider credit — TMDB, TheTVDB, fanart.tv, TVmaze — and each logo
link carries `title="<provider>"`. That partial is the only source for both the
page footer and the drawer footer, so the four links are the complete set. A
grep for `title=` across `templates/` returns these four and nothing else.

The work is a template edit. `app.js`, `app.css`, and every PHP class are
already correct for it; this document exists to record why nothing else moves,
since the tempting changes here are all the ones that would be wrong.

## Goals / Non-Goals

**Goals:**

- The provider credit uses the shared themed tooltip, in both footers.
- No native tooltip survives anywhere in `templates/`.
- Each link keeps an accessible name without depending on a tooltip.
- A regression — a fifth provider added with a `title`, or the four converted
  back — fails a test rather than shipping.

**Non-Goals:**

- Changing which providers are credited, their order, their logos, or the
  layout of the credit.
- Changing the tooltip component: its positioning, timing, theme, or the
  device gate that keeps it off touch screens.
- Introducing a tooltip on any other footer element (the product name, the
  version link). They have no hint to give, and inventing one is a product
  decision this change does not make.

## Decisions

### Replace `title` with `data-tooltip` on the `<a>`, not the `<img>`

The `<a>` is what a pointer hovers and what a keyboard focuses, and `app.js`
resolves a host with `closest('[data-tooltip]')` — so a tooltip on the image
would work by bubbling anyway. Putting it on the link is still right: the link
is the focusable element, so it is the one that must show a tooltip on keyboard
focus, and `focusin` is dispatched at the link, not the image inside it.

### The accessible name comes from `alt`, and is therefore already correct

Removing a `title` from an icon-only control is exactly the case the tooltip
requirement warns about, and its remedy is an `aria-label`. Not needed here:
each link's only content is `<img alt="TMDB">`, and an image's alternative text
already names its link for assistive technology. Adding an `aria-label` on top
would give the link two names, and the wrong one wins — `aria-label` overrides
the image's `alt`, so the alternative text stops being announced while still
being the thing a reader would edit if the name were wrong. One name, from
`alt`. This is what the spec's *keeps its accessible name without a title*
scenario asserts.

### The tooltip text repeats the `alt` text, deliberately

Both say `TMDB`. That is not redundancy to factor out: they address different
readers. `alt` is for someone who cannot see the logo; the tooltip is for
someone looking straight at a mark that may not spell its own name — fanart.tv's
does not, and TVmaze's is a wordmark at a size where it is being read as a
shape. The spec requires the two agree, and the test asserts that, which is the
correct relationship between them: coupled in value, separate in purpose.

Alternative considered: mark the links `data-tooltip-collapsed` so the tooltip
is conditional. Rejected — that attribute means "the layout has dropped this
host's text label", measured against `.nav-label` elements the credit does not
have, so `collapsed()` would return `true` unconditionally and the tooltip would
show anyway. It would read as a considered condition while being a no-op. These
are hint hosts: unconditional is both correct and simpler.

### Nothing is added to `app.css`

`.tooltip` is styled once and positioned by script; a new host needs no rule.
The one thing worth confirming rather than assuming is stacking: the drawer is a
tray at z-index 50, the tooltip is at 100 and is appended to `document.body`,
so a tooltip raised over the drawer footer paints above it. The narrow layout
that shows the drawer is usually touch — where no tooltip appears at all — but a
narrowed desktop window is not, and that is the case this ordering covers.

### The test extends the existing attribution block

`ApplicationShellTest` already parses the footer credit and asserts provider
order by position. The new assertions belong beside it, driven off the same
markup: every logo link carries `data-tooltip`, none carries `title`, and each
link's tooltip text matches its image's `alt`. Asserting the absence of `title`
across the whole rendered credit is what catches a fifth provider added later
with the old attribute — the failure mode a per-provider assertion would miss.

## Risks / Trade-offs

- **A future provider is added with `title=` copied from an old example** → the
  absence assertion scans the whole credit block, not a fixed list, so the new
  link fails it.
- **Someone "fixes" the accessible name by adding `aria-label`** → the tooltip
  text and `alt` are asserted to agree, but an `aria-label` silently overriding
  `alt` would not fail that. Recorded here and in the spec's scenario wording as
  the reason `alt` is the single source; a reviewer has something to point at.
- **The hint is only ever seen by pointer users** → accepted, and true of every
  tooltip in the app. Nothing is lost: `alt` carries the name everywhere else,
  and the logos link to sites that name themselves on arrival.
