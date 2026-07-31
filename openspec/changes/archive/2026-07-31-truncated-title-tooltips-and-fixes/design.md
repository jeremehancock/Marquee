## Context

Four independent fixes, bundled because each is a few lines. Only the first has
a real design question.

**Tooltip truncation.** `.card__caption` is a single-line, `text-overflow:
ellipsis` element that carries `data-tooltip="{{ caption_title }}"` — the exact
same string it renders. When the title fits, hovering pops a bubble that repeats
what is already on screen, and the `help` cursor promises information the tooltip
will not add. The shared tooltip module in `public/assets/app.js` is delegated
from `document` and already gates every trigger through one `show()` function,
which is where the device-capability check lives.

The other three are mechanical: a string in
[ChangePosterController.php:256](src/Controller/ChangePosterController.php#L256),
a meta tag in [layout.html.twig:8](templates/layout.html.twig#L8), and a README
table row. Note that `POSTER_SOURCE_URL` is not in the README's Compose snippet
at all — it appears only in the configuration table at
[README.md:154](README.md#L154), so that row is what "remove from the docker
compose" resolves to. `src/bootstrap.php` keeps reading the variable unchanged.

## Goals / Non-Goals

**Goals:**

- A poster caption tooltip appears only when the caption is truncated, on both
  the gallery and the orphans view, which share the caption component.
- The `help` cursor appears under exactly the same condition.
- Truncation is judged from the live rendered element, so a resize or a
  late-loading font cannot leave a stale answer.
- Every other tooltip host keeps behaving exactly as it does today.

**Non-Goals:**

- No change to touch behavior, keyboard behavior, or tooltip styling.
- No change to what `POSTER_SOURCE_URL` does in code, or its default.
- Not removing `apple-mobile-web-app-capable` — iOS still honors only the
  prefixed name for home-screen installs.
- No new JS test harness; there is none in this project, and adding one for a
  measurement that only exists in a real layout is not worth it.

## Decisions

### Measure at hover time, inside `show()`

The gate goes in `show()`, next to the existing `allowed()` device check:
if the host opts into the gate and `el.scrollWidth <= el.clientWidth`, return
without showing anything.

Alternatives considered:

- **Mark truncated captions with a class up front** (on load, on resize, after
  AJAX, after font load) and let CSS and JS both read it. Rejected: it needs a
  `ResizeObserver` or a debounced resize pass plus a re-measure hook on every
  path that replaces gallery markup — the AJAX gallery, Alpine-rendered
  previews, pagination. That is a standing invariant to maintain, and a missed
  hook produces a silently wrong tooltip. Measuring at hover time is one
  expression that cannot go stale.
- **Compare `scrollWidth` against a cached width.** Rejected for the same
  staleness reason.

`scrollWidth > clientWidth` is the standard ellipsis test and is exact for a
single-line, `nowrap` element — which is precisely what `.card__caption` is. It
forces layout, but only for one element, once per hover.

### Opt in per host, not by guessing

The gate is opt-in through a new boolean attribute on the host — e.g.
`data-tooltip-truncated`. Pagination steps, the Sort trigger, and the finder
preview carry hints, not repetitions of their own text, and must keep showing
unconditionally. Inferring "does this tooltip equal the element's text?" would
be clever and fragile; an explicit attribute states the intent in the template
where the decision belongs.

Applied to the two `.card__caption` hosts:
[gallery_results.html.twig:75](templates/partials/gallery_results.html.twig#L75)
and [_results.html.twig:35](templates/orphans/_results.html.twig#L35).

### Cursor follows via a class set at hover time

`cursor: help` moves off `.card__caption` onto a state class (e.g.
`.card__caption.is-truncated`), which the tooltip module toggles on
`pointerover` using the same measurement. CSS alone cannot detect truncation, so
something has to measure; reusing the hover measurement keeps one source of
truth instead of two that can disagree.

The trade-off is that the cursor resolves on pointer entry rather than being
correct before the pointer arrives — imperceptible in practice, and the wrong
state is never *shown* for a hovered element. The alternative (a persistent
measured class) reintroduces the observer machinery rejected above for a purely
cosmetic gain.

### `mobile-web-app-capable` is added, not swapped

Chrome logs the deprecation only when the prefixed tag is present without the
standard one; adding the standard tag clears it. Removing the prefixed tag would
clear the warning too, but iOS Safari reads only the prefixed name for
standalone home-screen installs, so removal would be a real regression for a
cosmetic warning. Both tags stay.

## Risks / Trade-offs

- **A caption truncated by exactly a sub-pixel amount reads as "fits".**
  `scrollWidth` and `clientWidth` are integers, so a fractional overflow can
  round to equal and suppress a tooltip for a title clipped by under a pixel →
  Accepted: at that size nothing meaningful is hidden.
- **Measuring in `show()` forces a synchronous layout on hover.** → One element,
  on a pointer event the user initiated; the module already reads
  `getBoundingClientRect()` twice in `place()` on the same path.
- **The opt-in attribute could be forgotten on a future caption-like host.** →
  Failure mode is the current behavior (tooltip always shows), not a broken
  page. The spec states the rule, and both existing hosts are updated together.
- **Removing `POSTER_SOURCE_URL` from the README could look like it was
  removed from the product.** → The variable still works; only the
  documentation row goes. The spec records that the variable remains supported
  but undocumented, so a future reader does not "restore" the row.

## Migration Plan

None. No data, no configuration, no persisted state is touched. Anyone already
setting `POSTER_SOURCE_URL` keeps working exactly as before.

## Open Questions

None.
