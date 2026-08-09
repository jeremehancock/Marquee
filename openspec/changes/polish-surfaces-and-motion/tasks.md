# Tasks: Surfaces and motion

Implementation tasks precede their test tasks, per the project workflow. Every
CSS edit must respect the load-bearing comments already in `app.css` — the
`.grid` column minimum tied to the poster action stack, the pinned-block
backgrounds, and the mobile block's position as the last thing in the file.

## 1. Token contract

- [x] 1.1 Add the elevation scale to `:root` in `public/assets/app.css` — five
      tiers, each a two-layer shadow (tight contact + wide ambient), keyed to the
      existing z-index ladder documented in the Modal section (pinned 30, tab bar
      40, tray 50, dialog 55, viewer 60, toast 80, tooltip 100).
- [x] 1.2 Add the radius scale. Seed it with the values already in use — 8, 10,
      12, 16, and 999px — so this task changes no rendered pixel.
- [x] 1.3 Add motion tokens: durations (fast / base / slow) and easing curves
      (standard, entrance, exit). Seed durations from the values already in the
      file so nothing changes yet.
- [x] 1.4 Add the translucency tokens — a chrome tint and a heavier backdrop
      tint, plus a blur radius token — chosen so the chrome tint alone holds
      contrast for its labels against a bright poster behind it.
- [x] 1.5 Comment the token block in the file's established voice, stating what
      each scale is for and that the elevation tier must agree with the z-index
      ladder.

## 2. Retrofit existing rules onto the tokens

- [x] 2.1 Replace every literal `border-radius` with its radius token. Values
      must not change in this pass.
- [x] 2.2 Replace every literal transition duration and easing with motion
      tokens, consolidating the seven ad-hoc durations onto the scale.
- [x] 2.3 Replace the three existing one-off `box-shadow` declarations —
      `.tooltip`, `.toast`, and the `.conn-dot` halo — with elevation tokens
      (the conn-dot halo stays a literal; it is a status ring, not elevation).
- [x] 2.4 Sweep `public/assets/wall.css` for values that duplicate the app's
      vocabulary and point them at the shared tokens.
- [x] 2.5 Grep for any remaining literal radius, shadow, or transition duration
      outside the token block and either tokenise it or add a comment saying why
      it is component-specific.

## 3. Page background

- [x] 3.1 Built a gradient wash on `body` anchored on `--bg`, shipped it to
      `:dev`, and removed it again. The pinned gallery controls must be opaque and
      must therefore reproduce the page exactly; a flat bar over a graded page is
      a visible rectangle, and painting the bar the same gradient does not work
      either, because `background-attachment: fixed` is unreliable on a sticky
      element. The page is one flat colour.
- [x] 3.2 Left all five other homes of `#1c1e24` untouched — the manifest's
      `background_color` and `theme_color`, the `theme-color` meta tag, the inline
      header logo, `logo.svg`, and `favicon.svg`.
- [x] 3.3 Recorded the constraint as a `visual-design` requirement ("The page and
      its opaque chrome share one background") so the next attempt at a graded
      page finds the reason rather than rediscovering it.

## 4. Glass chrome

- [x] 4.1 Make `.topbar` translucent with the chrome tint and blur, on both the
      desktop (`min-width: 641px`) and base rules, keeping the existing hairline.
- [x] 4.2 Make `.gallery-head` translucent, replacing `background: var(--bg)`.
      Keep the full-width coverage — nothing beside it may show at full strength.
- [x] 4.3 Make the mobile `.toolbar` translucent inside the mobile block. The
      negative side margins and matching padding that bleed it past `.container`'s
      14px gutters must stay exactly as they are.
- [x] 4.4 Make the mobile `.tabs` bottom tab bar translucent.
- [x] 4.5 Make `.modal__backdrop`, `.sheet__backdrop`, and `.overlay` use the
      backdrop tint and blur, so the page behind a dialog or tray is blurred as
      well as dimmed.
- [x] 4.6 Add the `@supports not (backdrop-filter: blur(1px))` fallback block
      restoring an opaque fill for every surface touched in 4.1–4.5.
- [x] 4.7 Verify contrast by measurement, not by eye: the tab labels, search
      field text, and sort control against the chrome tint over a worst-case
      bright poster.

## 5. Elevation applied

- [x] 5.1 Give `.modal__panel` and `.overlay__box` the dialog tier.
- [x] 5.2 Give `.sheet__panel` the tray tier, shadowed on its raised edges only.
- [x] 5.3 Give `.panel` the raised-content tier.
- [x] 5.4 Give `.card__frame` the resting tier, and its hover state the raised
      tier (paired with task 6.4).
- [x] 5.5 Re-read the ladder end to end and confirm no surface carries an
      elevation that disagrees with its z-index.

## 6. Motion

- [x] 6.1 Add hover, focus-visible, and active transitions to `.btn` — the base
      button has none today, which is why every button hover snaps. Scope hover
      to `@media (hover: hover)`; keep active feedback on all devices.
- [x] 6.2 Add the same treatment to `.icon-btn`, `.tab`, `.modal__tabs button`,
      `.pagination__page`, and `.modal__close`.
- [x] 6.3 Add a press state distinct from hover for every control above.
- [x] 6.4 Add the poster card hover lift: `transform: translateY(…) scale(…)`
      plus the raised elevation tier, inside `@media (hover: hover)`. Use
      `transform` only — never a property that participates in layout — so the
      grid cannot reflow.
- [x] 6.5 Confirm the card lift does not displace or obscure the action overlay,
      and that the overlay's existing opacity transition still runs.
- [x] 6.6 Add `x-transition` attributes to the `.modal` overlays in
      `gallery.html.twig` and `orphans.html.twig` — fade plus scale in, faster
      fade out — with durations from the motion tokens.
- [x] 6.7 Add `x-transition` to the `.sheet` trays in `gallery.html.twig` and
      `partials/_menu.html.twig` — slide from the bottom edge, backdrop fading
      with it. Reconcile with the existing `sheet-up` keyframes so the tray is
      not animated twice.
- [x] 6.8 Verify dismissal is not blocked by the exit animation: closing a dialog
      and immediately clicking the page behind it must register.
- [x] 6.9 Check the drag-to-dismiss sheet gesture in `gallery.js` still works —
      it manipulates `transform` on `.sheet__panel`, which `x-transition` also
      touches.

## 7. Reduced motion

- [x] 7.1 Replace the two `@media (prefers-reduced-motion: reduce)` blocks with a
      single universal rule collapsing animation and transition durations to
      `0.01ms` — not `none`, so `transitionend` and `animationend` still fire for
      any script awaiting them.
- [x] 7.2 Exempt the progress indicators — `.spinner` and the lazy-load shimmer
      on `.card__frame::before`, `.find-item__frame::before`, and
      `.viewer__placeholder` — so they keep conveying that work is in progress.
- [x] 7.3 Confirm the `search` capability's reduced-motion scroll-to-top branch
      in `gallery.js` is unaffected; it is a script branch, not a CSS rule.
- [ ] 7.4 Walk the app under reduced motion: every dialog, tray, overlay, and
      control must reach its correct appearance and stay fully operable.

## 8. Tests

- [x] 8.1 Update `tests/Unit/Asset/StickyToolbarTest.php`:
      `testPinnedToolbarHidesThePostersPassingUnderIt` and
      `testPinnedDesktopControlsHideThePostersPassingUnderThem` assert the literal
      `background: var(--bg)` under the message "must be opaque". Replace each
      with assertions on the chrome tint, the blur, and the `@supports` fallback —
      three assertions where there was one. Rewrite the docblock, which currently
      explains the opacity as load-bearing.
- [x] 8.2 Leave `testPinnedToolbarBleedsPastTheContentGutters` untouched. It
      guards a different failure — the 14px edge channel — which the change to
      translucency does not affect.
- [x] 8.3 Add a shape tripwire for the token contract: assert `:root` declares
      the elevation, radius, motion, and translucency tokens, and that no rule
      outside `:root` declares a raw `box-shadow` except the documented
      exceptions.
- [x] 8.4 Add a tripwire asserting elevation tier agrees with z-index for the
      dialog, tray, and pinned-control surfaces.
- [x] 8.5 Add a tripwire asserting the reduced-motion rule is universal rather
      than a selector list, and that the progress indicators are exempted.
- [x] 8.6 Add a tripwire asserting `.card__frame`'s hover lift uses only
      `transform`, so the grid cannot reflow.
- [x] 8.7 Re-run `PosterActionStackTest` and confirm the card action stack still
      fits — it guards the `.grid` minimum derived from that stack's height.

## 9. Gates and documentation

- [x] 9.1 `composer test` — all green.
- [x] 9.2 `composer stan` — PHPStan level 10 clean.
- [x] 9.3 `composer cs` — PHP-CS-Fixer dry-run clean.
- [x] 9.4 Check `README.md`, `docs/`, and `CLAUDE.md` for staleness. If any
      screenshot or description of the interface exists, refresh it; if nothing
      user-facing is documented, say so explicitly rather than inventing edits.
- [x] 9.5 Add `visual-design` to the capability map in `openspec/config.yaml`,
      with its one-line scope.

## 10. Validation against the `:dev` image

- [x] 10.1 Build and run the image; smoke-test `/health` per `docs/docker.md`.
- [ ] 10.2 Desktop pass: gallery scroll under the glass header and pinned
      controls, card hover lift, every dialog open and close, the fullscreen
      viewer, Find Posters, the import and orphans screens, and the sign-in
      screen.
- [ ] 10.3 Phone pass on real hardware: glass toolbar and bottom tab bar while
      scrolling, every tray opening and dismissing including the drag gesture,
      and confirmation that scrolling has not degraded. If it has, fall the phone
      toolbar back to opaque through the `@supports` path from 4.6.
- [ ] 10.4 Confirm the pinned controls read as deliberate rather than as a
      rendering fault — no poster individually recognisable through the blur.
- [ ] 10.5 Load an already-installed PWA against the new build and confirm the
      new stylesheet arrives rather than the service worker serving the cached
      one.
- [ ] 10.6 Reduced-motion pass with the OS setting enabled.
- [ ] 10.7 Test a browser without `backdrop-filter` support, or with it disabled,
      and confirm every glass surface falls back legibly.
