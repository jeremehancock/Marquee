## Context

`templates/partials/gallery_results.html.twig:31` renders one sentence above the
grid:

```
Showing {{ result.firstItemNumber }}–{{ result.lastItemNumber }} of {{ result.total }}
```

That is correct on a desktop, where a pager sits below the grid and the range
names the page you are on. On a narrow screen it is wrong three times over:

```
  page 1 arrives from the server
    ┌──────────────────────────────────┐
    │ Showing 1–24 of 1948             │  ← rendered once
    │ .grid  [24 cards]                │
    │ .pagination                      │  ← display:none ≤640px
    └──────────────────────────────────┘

  user scrolls ─ IntersectionObserver fires ─ loadMore()
    gallery.js:1573  newGrid.querySelectorAll('.card')
    gallery.js:1574  infinite.grid.appendChild(frag)
                     ▲
                     └── cards only. The stats line is never touched.

  after three batches
    ┌──────────────────────────────────┐
    │ Showing 1–24 of 1948             │  ← unchanged, and now false
    │ .grid  [72 cards]                │
    └──────────────────────────────────┘
```

1. There is no current page on a phone, so a range of one is a fiction.
2. The range is stale from the first batch onward — the grid holds 72 posters and
   the line still says 24.
3. It refers to a pager that `app.css:2923` has hidden immediately below it.

The infinite-scroll behaviour (`poster-library` spec:767) and the count line came
from different changes and were never reconciled. The spec still requires the
broken result: the pagination requirement's "Posters are paginated" scenario ends
"**AND** reports how many posters are shown out of the total", stated for every
screen.

## Goals / Non-Goals

**Goals:**

- A narrow screen reports a figure that is true when the gallery opens and stays
  true however far the user scrolls.
- Desktop is untouched.
- The spec stops requiring the behaviour being removed.

**Non-Goals:**

- A live progress count ("Showing 72 of 1948", updated on each append). Discussed
  below and rejected for this change.
- Formatting the number. `1948` stays `1948`; desktop already prints it unpunctuated
  and making only the phone say `1,948` would be a new inconsistency. Thousands
  separators are separate work if wanted at all.
- The search branch, which already reports a count rather than a range.
- The empty state, which is shared and correct.

## Decisions

### Report the total, not live progress

Three candidates were weighed:

| | Narrow screen reads | Cost | Answers |
| --- | --- | --- | --- |
| **Total** | `Total: 1948` | template + one media query | how big is this category |
| **Live progress** | `Showing 72 of 1948` | JS updates the line on every append | how far have I got |
| **Nothing** | — | one media query | nothing |

Live progress is the closer analogue of the desktop line and would give real
feedback on a long scroll. It is rejected here because it makes a static string
into a piece of state that `loadMore()` has to maintain, that `setResults()` has
to restore after every search, sort, and view change, and that has to be correct
when a batch fails and the `.catch(function () {})` at `gallery.js:1579` swallows
it. That is a meaningful amount of machinery, and it can go wrong in the same way
the current line is wrong — by falling out of step with the grid.

`Total:` cannot fall out of step, because it does not describe the grid. It
describes the category, and the category does not change while you scroll.

Rejecting "nothing": the total is the one number a phone reader actually wants,
and there is nowhere else on the screen to find it.

### Render both sentences, hide one by media query

```twig
<p class="stats">
    <span class="stats__range">Showing {{ … }}–{{ … }} of {{ result.total }}</span>
    <span class="stats__total">Total: {{ result.total }}</span>
</p>
```

```css
.stats__total { display: none; }

@media (max-width: 640px) {          /* the block that already hides .pagination */
    .stats__range { display: none; }
    .stats__total { display: inline; }
}
```

The server cannot make this choice — it does not know the viewport. JavaScript
could, but it would add a third place the 640px threshold is written and would
have to re-run on resize; CSS does both for free.

`display: none` removes an element from the accessibility tree as well as from
the page, so a screen reader encounters exactly one sentence at any width. That
is what the "Only one report is presented" scenario pins, and it is the reason
this approach is acceptable rather than merely convenient — two sentences in the
DOM would otherwise be a regression for a screen-reader user.

*Alternative considered:* render only one sentence, chosen by a JS width check on
load. Rejected — it duplicates the threshold a third time, breaks on resize, and
puts a string that never changes under JavaScript's control.

### The threshold is written twice, and stays that way

`640px` already appears in two places: the media query in `app.css:2721` and
`isPhone()` at `gallery.js:1534`. This change adds rules inside the existing media
query rather than a new query, so the count is unchanged.

Unifying them would mean a CSS custom property read from JS, or a JS-set class on
`<html>` — worth doing on its own terms, not as a rider on a two-line template
change. Noted rather than fixed.

### Wording: `Total: 1948`

Chosen by the user. It reads as a label and a figure rather than a sentence about
a page, which is the point — it makes no claim about what is currently on screen.
Alternatives such as "1948 posters" read more naturally but bury the number; the
label-first form scans better above a grid.

It is shown even when the whole category fits on one screen and nothing will ever
scroll. A phone that sometimes shows a total and sometimes shows nothing is
harder to read than one that always shows the total, and the figure is not wrong
in that case — merely obvious.

### Test strategy

- `tests/Functional/GalleryTest.php` — the rendered listing contains both the
  range and the `Total:` sentence, and the search branch contains neither. This
  is where the "single-page category still reports its total" scenario lands.
- `tests/Unit/Asset/` — a new CSS-reading test in the pattern of the sixteen
  already there: `.stats__total` is hidden by default, and the `max-width: 640px`
  block hides `.stats__range` and reveals `.stats__total`. This is the assertion
  that catches the two rules drifting apart and leaving a phone showing both
  sentences or neither.

No test can prove the accessibility-tree behaviour of `display: none`; it is a
platform guarantee, recorded in the spec and in a comment beside the rule.

## Risks / Trade-offs

**Two sentences in the DOM where the reader sees one.** → The hidden one is
`display: none`, so it is absent from the page and from the accessibility tree.
The asset test pins that exactly one is visible at each width; the risk is a
future edit that switches the mechanism to `visibility` or `opacity`, which the
test would catch.

**A resize between widths shows a report matched to the new width, but infinite
scroll's own setup is not re-run on resize.** → Pre-existing: `setupInfinite()`
runs on load and after `setResults()`, not on resize, so a desktop window narrowed
mid-session already has pagination hidden without infinite scroll wired up. This
change makes the count line follow the width honestly rather than papering over
that gap. Out of scope, and worth noting as separate work.

**The count line is duplicated markup for one differing word.** → It is four
lines of Twig for a string the server cannot choose. The alternative costs
JavaScript.

## Migration Plan

Not applicable. Template and stylesheet only; no data, no stored settings, no
routes. Revert is a single commit.

## Open Questions

None.
