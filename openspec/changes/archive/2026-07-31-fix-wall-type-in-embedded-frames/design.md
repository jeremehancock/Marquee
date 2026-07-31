## Context

`.wall__frame` is a portrait 2:3 box sized from the container's height
(`height: 92%`), so in any landscape container its width is
`0.92 × H × 2/3 = 0.613 × H`. Since the 2026-07-29
`fix-wall-type-and-live-tv` change it is also a containment context, and every
dimension inside the banners is a share of it in `cqw`.

That change was correct for the complaint it answered — type that was 41.6px at
1080p and still 41.6px at 4K — but it embeds an assumption: that pure
proportionality is right, which holds only while viewing distance scales with
display size. It does for a TV across a room, a desktop at a desk and a phone in
the hand. It fails for a wall embedded in a dashboard widget, where the frame
shrinks but the viewer's distance to the monitor hosting it does not.

The reported case is a 350×350 widget. That is square, so it is
height-constrained like any landscape container: the frame is 215×322.

| | frame width | title | detail | badge |
| --- | --- | --- | --- | --- |
| 350×350 widget | 215px | 7.7px | 5.2px | 6.2px |
| phone 390×844 | 359px | 12.9px | 8.6px | 10.4px |
| 1080p | 662px | 23.8px | 15.9px | 19.2px |
| 4K | 1325px | 47.7px | 31.8px | 38.4px |

Reported as good at 1080p and on a phone, illegible in the widget. The wall is
not iframe-aware and should not become so — an embedded page cannot detect its
own viewing distance, and requiring the host to opt in defeats the purpose.

## Goals / Non-Goals

**Goals:**

- Legible banner type in a 350×350 embedded frame, with no opt-in from the
  embedding page.
- The fixed sizes from Posteria — the app whose wall this reproduces — held
  across the range of frames where they fit.
- 1080p and above unchanged, so the previous change's fix is not undone.
- One number driving every dimension in the banners, so they cannot drift out
  of proportion with themselves.

**Non-Goals:**

- A separate layout for small or wide-short containers. Placing the text beside
  the poster rather than on it would read better in a wide dashboard tile, but
  the reported container is square: the frame is 215px of the available 350px,
  leaving 67px per side. There is nowhere to put it.
- Dropping or shortening the "Currently Streaming" label. Confirmed as staying.
- Any change to the banners' colours, layout, copy, or the gold-bar treatment.
- Detecting the embedding context in JavaScript or via a URL parameter.

## Decisions

### Replace pure proportionality with a plateau

One custom property drives the banners:

```css
.wall__banner { --wall-type: max(min(16px, 3.9cqw), 2.4cqw); }
```

Read outward: `min(16px, 3.9cqw)` holds Posteria's 16px base until the frame is
too narrow for it, then scales down; `max(…, 2.4cqw)` resumes proportional
growth once the frame is wide enough that 16px would look small. The result is a
plateau between 410px and 667px of frame width with a proportional arm at each
end.

| | frame | base | title | badge | detail | vs. today |
| --- | --- | --- | --- | --- | --- | --- |
| 350×350 widget | 215px | 8.4px | 12.6px | 10.1px | 8.4px | **1.63×** |
| phone 390×844 | 359px | 14.0px | 21.0px | 16.9px | 14.0px | **1.62×** |
| plateau start | 410px | 16px | 24px | 19.3px | 16px | — |
| 1080p | 662px | 16px | 24px | 19.3px | 16px | 1.01× |
| 1440p | 883px | 21.2px | 31.8px | 25.6px | 21.2px | 1.00× |
| 4K | 1325px | 31.8px | 47.7px | 38.4px | 31.8px | 1.00× |

1080p moves by 0.6% and everything above it is untouched, so the previous
change's fix survives intact. The gain is 1.6× and lands on the phone as well
as the widget — see the risks.

*Alternatives considered.* A plain floor, `max(Npx, 2.4cqw)`, is shorter but has
no upper knee, so it either under-serves the widget or overshoots 1080p.
Literal fixed sizes everywhere, `--wall-type: 16px`, is shortest of all and is
what Posteria did — but Posteria's wall was `90vh` and full-screen only, never
narrower than ~600px. At 215px it puts a 24px title and a 19.2px badge in a
215px frame: the badge wraps to two lines, the title to three, and the two
banners take roughly 79% of the poster. It also returns a 4K TV to a 24px title,
re-opening the complaint the last change closed.

### The label's width sets the lower arm, not taste

`3.9cqw` is derived, not chosen. "CURRENTLY STREAMING" is a fixed 19 characters
carrying `0.23em` of tracking and `1.4828em` of side padding, and the spec
requires it on one line. Measured in a browser rather than estimated — see
Verification — its width in units of the base is:

| | full decoration | trimmed |
| --- | --- | --- |
| bare text | 12.31em | 12.31em |
| letter-spacing × 19 | 4.37em (`0.23em`) | 2.28em (`0.12em`) |
| side padding × 2 | 2.97em (`1.4828em`) | 1.20em (`0.6em`) |
| **of the badge's size** | **19.65em** | **15.79em** |
| **of the base** (× 1.2083) | **23.74em** | **19.08em** |
| worst measured font | 25.17em | 20.52em |

Holding 20% headroom against a frame of width `W` on the *worst* font:
`base ≤ 0.8W / 20.52 = 0.039W`. Hence `3.9cqw`, which holds that margin at every
size below the plateau by construction — measured at 26% in a 215px frame and
24% at the threshold.

This is what caps the improvement at 1.6× rather than the 2× the poster coverage
alone would allow. The label, not the poster, is the binding constraint on how
large this type can get in a narrow frame.

### Spend the label's decoration, not the type

Below a 500px frame the label's tracking drops to `0.12em` and its side padding
to `0.6em`, in a container query. That is a 20% reduction in the label's width,
and it is what makes `3.9cqw` viable: with full decoration the same 20% headroom
would require `3.2cqw`, taking the title in a 215px frame from 12.6px to 10.3px
and cutting the gain from 1.6× to 1.33×. Tracking and padding are decoration;
type size is the thing being fixed. The gold bar and the uppercase treatment,
which carry the look, are untouched.

The threshold is 500px of frame width rather than the plateau's own 410px
because the label is pinned at 19.3px across the whole plateau while the frame
keeps shrinking — full decoration only has room to spare from 500px up. The
discontinuity at that boundary is a tracking and padding step on a frame 500px
wide, i.e. a container ~815px tall, visible only while dragging a window
through that width.

*Alternatives considered.* An earlier draft of this design rejected the trim as
worth only 7%, and set the arm at `4.9cqw`. Both numbers came from an arithmetic
error — `20.21 × 1.2083` was recorded as `20.46` rather than `24.42`, converting
badge-size units to base units in the wrong direction. Measurement put the true
figure at 23.74em and the trim's value at 20%, which reverses the decision.
Letting the label wrap to two lines removes the constraint entirely but
"CURRENTLY / STREAMING" reads as broken. Shortening the label to "NOW PLAYING"
would be the largest win available and is explicitly out of scope. Trimming the
decoration at *all* sizes avoids the boundary but discards Marquee's tracking on
the displays that have ample room for it.

### Convert the banners to an `em` cascade off that one property

Every `cqw` value in the banners becomes `em`, so the banners scale as a rigid
unit and there is no second number to keep in sync:

| Selector / property | Today | Becomes |
| --- | --- | --- |
| `--bottom` font-size | — (inherits 16px) | `var(--wall-type)` |
| `--bottom` padding | `8.4cqw 3.5cqw 3cqw` | `3.5em 1.4583em 1.25em` |
| `.wall__title` | `3.6cqw` | `1.5em` |
| `.wall__meta` | `2.4cqw` | `1em` |
| `.wall__meta` gap | `0.7cqw 2.2cqw` | `0.2917em 0.9167em` |
| `.wall__meta` margin-top | `1.1cqw` | `0.4583em` |
| `.wall__user` padding-left | `2.3cqw` | `0.9583em` |
| user dot size / blur | `1cqw` | `0.4167em` |
| `--top` font-size | `2.9cqw` | `calc(var(--wall-type) * 1.2083)` |
| `--top` padding | `2.1cqw 4.3cqw` | `0.7241em 1.4828em` |
| `--top` letter-spacing | `0.23em` | unchanged |

Every ratio is the old `cqw` value over `2.4`, so at the plateau's lower knee
the rendering is identical to today's — only the driving number changes. The
top banner's padding is expressed against its *own* size (`2.1/2.9`), which is
what `em` resolves against there.

### Two placement traps that make this silently wrong

**`--wall-type` goes on `.wall__banner`, not `.wall__frame`.** `.wall__frame`
establishes the containment context, and an element cannot query the container
it establishes. `cqw` in `.wall__frame`'s own rule resolves against the next
container out — the small-viewport fallback — so the wall would look plausible
and be sized against the wrong box. The property must be declared on a
descendant of the frame.

**`.wall__banner--top` needs `calc()`, not a bare `em`.** That element matches
both `.wall__banner` and `.wall__banner--top`, so a `font-size: 1.2083em` on it
resolves against its *parent's* font-size, not against `--wall-type` on itself —
yielding a constant 19.3px at every size. `calc(var(--wall-type) * 1.2083)`
sidesteps the self-reference. Its padding may stay in `em` because that does
resolve against the element's own computed size.

### Nothing else changes

No template, JavaScript or PHP edit. `container-type: inline-size` stays on
`.wall__frame` — both arms of the expression use `cqw`, and the label's
container query resolves against it. The markup already carries every class
this needs.

Incidentally, if `cqw` is unsupported the whole expression is invalid at
computed-value time, `font-size` falls back to inherited, and the banners render
at the 16px root — which is Posteria's base. The failure mode is the plateau.

## Risks / Trade-offs

- **The phone changes as much as the widget** → The lower arm is a share of
  frame width, so a 359px phone frame gets the same 1.6× as the 215px widget:
  the title goes 12.9px → 21.0px. That is the intended Posteria sizing and the
  reason for choosing this over a floor that only caught the widget, but it was
  reported as already good. This is the main thing to confirm on the `:dev`
  image. If it reads heavy, `16px` is a single number to turn down; lowering it
  pulls the plateau down and shrinks the phone and widget together.
- **The label could still wrap on an unmeasured font** → Measured headroom is
  26% below the threshold. The widest font available to measure against needed
  6% more than the authored stack, so the margin covers the observed spread four
  times over. Roboto, Segoe UI and SF Pro could not be measured on the build
  host — they are not installed, and a stack naming them silently falls back —
  so a device using one of them is the residual risk. Confirmed by loading
  `/wall` in a 350×350 frame and checking the label stays on one line.
- **Banner coverage grows in the widget** → Measured at 34% of the poster in a
  215px frame against 19% at 1080p, of which the bottom banner's transparent
  gradient lead-in is roughly a third. The 34% figure includes a title wrapping
  to two lines; a short title gives less. It still labels rather than dominates,
  which the spec continues to require, but it is the most crowded the wall gets.
- **Long titles wrap in a narrow frame** → At 215px a 12.6px title has ~190px of
  usable width, about 30 characters. "The Fellowship of the Ring" fits on one
  line; longer titles wrap and push the banner taller. Wrapping is existing
  behaviour, not introduced here, and the larger type makes it more likely.
- **No automated coverage** → This is CSS, and the project has PHPUnit only. No
  test can catch a regression here, so the existing functional wall tests
  continue to assert the markup and the type scale is verified as below.

## Verification

The numbers in this document are measured, not estimated. The wall markup was
rendered against the real stylesheet in headless Chromium inside iframes of
exact size — the scenario under test — reading computed font sizes back out and
counting the label's line boxes with `Range.getClientRects()`.

| case | frame | base | title | badge | label lines | headroom |
| --- | --- | --- | --- | --- | --- | --- |
| 300×300 | 184×276 | 7.2px | 10.8px | 8.7px | 1 | 26% |
| 350×350 widget | 215×322 | 8.4px | 12.6px | 10.1px | 1 | 26% |
| phone 390×844 | 359×776 | 14.0px | 21.0px | 16.9px | 1 | 26% |
| threshold 816×816 | 500×751 | 16.0px | 24.0px | 19.3px | 1 | 24% |
| 1080p | 662×994 | 16.0px | 24.0px | 19.3px | 1 | 43% |
| 1440p | 883×1325 | 21.2px | 31.8px | 25.6px | 1 | 43% |
| 4K | 1325×1987 | 31.8px | 47.7px | 38.4px | 1 | 43% |

Every rendered size matches the design table above, the label holds one line at
every size, and 1440p and 4K are byte-identical to the previous behaviour.

Font sensitivity was measured the same way, rendering the label across each
family the stack can resolve to. The spread against the authored stack was 0% to
+6.0%, the outlier being DejaVu Sans. Roboto, Segoe UI and SF Pro are not
installed on the build host, so rows naming them silently fell back and are not
evidence — that gap is recorded as a risk above rather than treated as measured.

## Migration Plan

Deploy is the ordinary `dev` → `:dev` image → validate → PR flow; the change is
one stylesheet and carries no data, config or route implications. Rollback is
reverting the commit. Browsers cache `wall.css`, but `asset()` already
fingerprints it, so a stale stylesheet is not a concern.

## Open Questions

- Does the 16px plateau ceiling read well on the phone, or should it come down?
  The type nearly doubles there. Resolved by looking at the `:dev` image, not by
  analysis — it is a judgement about legibility, not a measurement.
- Does the label hold one line on a device using Roboto, Segoe UI or SF Pro?
  Measured headroom is 26% and the observed font spread is 6%, so this is
  expected to hold, but those three fonts could not be measured here.

Resolved during implementation: the label's true width (23.74em of the base,
measured) and whether trimming its decoration is worth the container query
(yes — 20%, not the 7% first calculated).
