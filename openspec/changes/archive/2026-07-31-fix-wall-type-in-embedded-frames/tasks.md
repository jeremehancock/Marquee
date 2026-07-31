## 1. Drive the banners from one property

- [x] 1.1 In `public/assets/wall.css`, declare
  `--wall-type: max(min(16px, 3.9cqw), 2.4cqw)` on `.wall__banner` — on the
  banner, **not** on `.wall__frame`, which cannot query the container it
  establishes
- [x] 1.2 Set `.wall__banner--bottom` to `font-size: var(--wall-type)` and
  convert its own `cqw` values to `em`: padding `3.5em 1.4583em 1.25em`
- [x] 1.3 Convert the bottom banner's children to `em`: `.wall__title` `1.5em`;
  `.wall__meta` `1em` with gap `0.2917em 0.9167em` and margin-top `0.4583em`;
  `.wall__user` padding-left `0.9583em`; the dot's width, height and box-shadow
  blur `0.4167em`
- [x] 1.4 Set `.wall__banner--top` to
  `font-size: calc(var(--wall-type) * 1.2083)` — a bare `em` would resolve
  against the parent, not against `--wall-type` on the same element — and
  convert its padding to `0.7241em 1.4828em`, leaving `letter-spacing: 0.23em`
  as it is
- [x] 1.5 Update the comment block above `.wall__frame` (lines 41-49): it
  currently explains why everything is expressed in `cqw`, which is no longer
  the whole story. Record why the plateau exists — proportional sizing assumes
  viewing distance scales with the frame, which fails in an embedded widget —
  and that `container-type` stays because both arms still use `cqw`

## 2. Verify against the toolchain

- [x] 2.1 Run `composer test`, `composer stan` and `composer cs`; all three must
  pass. No PHP changes, so this is a regression check rather than new coverage
- [x] 2.2 Confirm `tests/Functional/PosterWallTest.php` still passes unchanged —
  the markup is untouched, so any failure here means something was edited that
  should not have been
- [x] 2.3 Add no CSS tests: the project has PHPUnit only and nothing can assert
  a rendered type scale. Verification is task 3, and this is deliberate

## 2b. Measure the rendered type (done during implementation)

- [x] 2b.1 Render the wall markup against the real stylesheet in headless
  Chromium, inside iframes of exact size, reading back computed font sizes and
  counting the label's line boxes with `Range.getClientRects()`. Results are in
  `design.md` under Verification
- [x] 2b.2 Correct the arithmetic error this exposed: the label needs 23.74em of
  the base, not the 20.46em `design.md` first recorded (`× 1.2083` was applied
  in the wrong direction). Headroom at `3.9cqw` was 7%, not the 20% designed for
- [x] 2b.3 Reverse the design's rejection of trimming the label's decoration —
  worth 20%, not the 7% first calculated — and add the container query below a
  500px frame. Measured headroom is now 26% below the threshold, 24-43% above
- [x] 2b.4 Measure font sensitivity across the families the stack resolves to;
  spread is 0% to +6.0%. Record in `design.md` that Roboto, Segoe UI and SF Pro
  are absent from the build host and so were *not* measured
- [x] 2b.5 Reconcile the delta spec with the implementation: the label's
  decorative spacing is now an explicit exception to the scale-together rule

## 3. Verify on the `:dev` image

- [x] 3.1 Build and run the `:dev` image with a live Plex server and a stream
  active, so the banners actually render
- [x] 3.2 Embed `/wall` in a **350×350** iframe on the real dashboard and
  confirm "CURRENTLY STREAMING" stays on **one line**. Measured at 26% headroom
  in 2b, so this is confirming the browser's font rather than the layout — the
  fonts that box could not measure (Roboto, Segoe UI, SF Pro) are the residual
  risk. If it wraps, reduce `3.9cqw` until it fits
- [x] 3.3 In the same 350×350 frame, confirm a long title is readable and that
  the two banners still leave the poster the primary subject. Measured coverage
  is 34% with a two-line title, against 19% at 1080p — the judgement call is
  whether that reads as labelling or crowding
- [x] 3.4 Load `/wall` on a phone. The title goes 12.9px → 21.0px, the largest
  visible change here and the one place it may overshoot. If it reads heavy,
  lower the `16px` plateau ceiling — that single number pulls the phone and the
  widget down together
- [x] 3.5 Confirm 1080p looks unchanged from the current build. Measured as
  identical at 1440p and 4K and within 0.6% at 1080p, so this is a spot check
- [x] 3.6 Fold any number changed during 3.2-3.4 back into `design.md` so its
  tables match what shipped

## 4. Documentation

- [x] 4.1 Check `README.md`, `docs/` and `CLAUDE.md` for staleness. `docs/`
  never mentions the wall and `CLAUDE.md` does not describe banner sizing —
  both correctly need no edit. `README.md` does describe the wall
- [x] 4.2 `README.md` — the wall bullet said only "point a spare monitor or TV
  straight at `/wall`". Embedding is now a supported case rather than one that
  merely happens to render, so the bullet now says so

## 5. Ship

- [x] 5.1 Run `/ship` — do not hand-roll the commit, VERSION bump, archive or
  PR
- [x] 5.2 Do not archive until the `:dev` validation in section 3 is done and
  accepted
