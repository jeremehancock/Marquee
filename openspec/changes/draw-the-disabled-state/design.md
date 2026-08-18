## Context

`public/assets/app.css` contains no `:disabled` or `[disabled]` rule. Not a weak
one — none. The single near-miss is `.is-disabled { opacity: 0.45 }` at
`app.css:1381`, which no template and no script ever applies; it is the residue of
an earlier attempt that never reached a call site.

The consequence is that `disabled` is currently a behavioural attribute with no
visual half. A disabled `.btn--accent` keeps:

- `background: var(--accent)` from `.btn--accent` (`app.css:1063`) — maximum
  emphasis on a control that cannot be used;
- `cursor: pointer` from `.btn` (`app.css:1016`);
- `.btn--accent:hover { background: #f0ac1c }` (`app.css:1039`). CSS `:hover`
  matches disabled elements, so the button brightens under the pointer. This is
  the worst of it: hover feedback is the application's promise that pressing will
  do something.

Inside the import tray `.sheet__body form .btn--accent` (`app.css:3031`) makes it
`width: 100%; padding: 14px`, so the Import button is the largest, brightest,
most inviting element on the screen before any selection exists.

Two comments in the codebase already assert this appearance works:

- `templates/gallery.html.twig:155` — *"Disabled carries its reason in the tooltip
  and in the panel."*
- `public/assets/gallery.js:1406` — *"the disabled button and the overlay
  communicate, the guard enforces."*

Both were written in good faith about a treatment that does not exist. That two
independent authors assumed it was there is the strongest argument for writing it
down rather than adding one more local fix.

The five controls bound to `disabled` today, all currently indistinguishable from
working:

| Control | Site | Off when |
| --- | --- | --- |
| Import | `templates/plex.html.twig:86` | `!type \|\| sections.length === 0 \|\| importing` |
| Sign in to Plex | `templates/connect.html.twig:88` | `busy` |
| Plex Posters tab | `templates/gallery.html.twig:157` | `!change.linked` |
| Change poster | `templates/gallery.html.twig:285` | `preview.applying` |
| Cancel | `templates/gallery.html.twig:287` | `preview.applying` |

Separately, on the import form the "Re-download unchanged posters" option is
`x-show="type"` (`templates/plex.html.twig:80`) while the Import button needs
`type && sections.length > 0`. Choose a content type, tick no library: the option
is live and settable while the import it modifies cannot start.

## Goals / Non-Goals

**Goals:**

- One disabled treatment, declared once, inherited by all five controls and by
  every control added later.
- Suppress hover and press feedback while disabled. This is the fix; the fill
  colour is the supporting detail.
- Write down the disable-versus-hide rule the application already follows in two
  places without having stated it.
- Align the re-download option's visibility with the Import action's availability.

**Goals (continued):**

- Keep a switched-off control reachable and announced, and stop it throwing the
  user's focus away when it switches itself off.
- Refuse each guarded action where it is performed, so a reachable control cannot
  act.

**Non-Goals:**

- **Focus management.** No dialog in the application moves focus into itself,
  keeps it there, restores it on close, or makes the page behind it inert. It is
  the gap underneath this one and a change in its own right — see Decisions for
  what it leaves latent here.
- A "Step 3" legend for the re-download option.
- An in-flight state for **Save settings** (`templates/settings.html.twig:163`),
  which is a plain POST and is double-submittable. Real, unrelated.
- Touching **Upload poster** / **Fetch poster** (`templates/gallery.html.twig:173`,
  `179`). They are gated by native `required` on submit — a different pattern that
  already gives the user a reason, and one that never leaves a dead-looking button
  on screen.
- Retuning any token. Nothing in the existing scales moves.

## Decisions

### Disable the Import button rather than hide it

The application already uses both patterns, and both are right where they sit:

- **Disable** — `templates/gallery.html.twig:152-156`, the Plex Posters tab, with
  the reasoning written out: *"Hiding it would leave a user who learned where the
  tab sits finding it gone with no reason given."*
- **Hide** — pagination first/prev/next/last,
  `templates/partials/gallery_results.html.twig:132-149`, wrapped in
  `{% if result.hasPrevious %}`. On page one there is no sequence to teach.

The distinguishing question is whether the user can bring the control to life
from this screen. For the Import button the answer is yes, which is exactly the
tab's case: it is the destination of a two-step flow, and a visible-but-off
button teaches the sequence — *pick a type, pick a library, and this comes
alive*. Hiding it leaves a tray containing four pills and no evident goal.

*Alternative considered:* hide it until enabled, matching Step 2 and the
checkbox. Rejected — it removes the only indication of what the form is for, and
it contradicts a decision this repo already made and documented.

### The treatment: surface, not opacity

`--surface` background, `--border`, `--muted` text, `cursor: not-allowed`, and —
the load-bearing part — hover and press suppressed. An accent button surrenders
its accent, which is what makes "off" legible at a glance rather than on
inspection.

*Alternative considered:* reuse `.is-disabled`'s `opacity: 0.45` and be done.
Rejected on two counts. Opacity alone is ambiguous — the application already uses
transparency for translucent chrome and for animating things in, so a faded
control reads as "arriving" or "behind something" as readily as "off". And it
does not suppress hover: a 45%-opacity button still brightens under the pointer,
leaving the actual defect in place. The proposal retires that rule rather than
promoting it.

*Alternative considered:* keep the accent fill and only suppress hover. Rejected —
correct but insufficient. A full-width gold bar reads as the primary action
whether or not it responds.

Legibility is the constraint on how far to push it. `--muted` on `--surface` is
already used for `.check__meta` and `.stats`, so the pairing is established in the
stylesheet rather than invented here. The label is the only thing on screen
stating what the control would do, so it must survive.

### Suppression means restricting the hover rules, not overriding them

`.btn--accent:hover` and `.btn:hover` live inside `@media (hover: hover)`
(`app.css:1034-1046`). The clean form is to exclude the off state on those
existing selectors, so no hover state is ever declared for an unavailable
control. Adding a later rule to undo them would work by specificity accident and
would have to be re-checked every time a hover rule is added.

`.btn:active { transform: translateY(1px) }` (`app.css:1054`) needs the same
treatment, and here it is load-bearing rather than belt-and-braces: browsers do
not match `:active` on a natively `disabled` button, but an `aria-disabled` one
is a perfectly ordinary button and *will* match. Under this change a switched-off
button would visibly depress under a press unless the rule excludes it.

Both selectors match on the attribute rather than the pseudo-class:

```css
.btn:disabled, .btn[aria-disabled="true"]              { … }
.btn:not(:disabled):not([aria-disabled="true"]):hover  { … }
```

`:disabled` is kept alongside it. Nothing in the application uses it after this
change, but a native `<button disabled>` added later must not silently arrive
untreated — the whole point of stating the treatment once.

Note that `:focus-visible` is deliberately *not* suppressed, and under
`aria-disabled` this stops being free: the control is now focusable, so a
keyboard user will actually land on it and needs to see where they are.

### `aria-disabled` instead of the `disabled` attribute

Real `disabled` removes a control from the tab order, so a keyboard user never
lands on the off Import button and is never told it exists. It also has a
sharper, less obvious cost: **when a focused element becomes `disabled` the
browser drops focus to `document.body`.** Three of the five controls become
unavailable *in response to being pressed* —

| Control | Switched off by |
| --- | --- |
| Sign in with Plex | `busy`, set by its own click |
| Change poster | `preview.applying`, set by its own click |
| Cancel | `preview.applying`, set by its sibling's click |

— so a keyboard user presses **Change poster** and their focus silently returns
to the top of the document, mid-task. `aria-disabled` keeps the element focusable
and the focus where the user put it.

**Half of this is groundwork, and the design should say so.** The application has
no focus management at all: `grep '\.focus()'` across `app.js` and `gallery.js`
returns nothing, against seven `role="dialog" aria-modal="true"` panels, none of
which move focus in on open, keep it inside, restore it on close, or make the
page behind them inert. So of the five:

- **Import** (`/plex`) and **Sign in** (`/connect`) sit on ordinary pages. A
  keyboard user genuinely reaches them, and the fix is immediate.
- **Change poster**, **Cancel**, and the **Plex Posters tab** are inside a dialog
  focus never enters. Correct after this change, observable only after focus
  management lands.

They are done here anyway rather than left for that change: the alternative is
editing the same five call sites twice, and the semantics are right regardless of
whether anything can currently perceive them.

### A reachable control is an operable one, so guard the action

This is the cost of the decision above and the part that carries risk.
`aria-disabled` is an announcement, not an enforcement — the click still fires.
Three sites need a guard they do not have:

| Site | Guard today |
| --- | --- |
| `applyPreview()` (`gallery.js:1414`) | ✅ `if (this.preview.applying) { return; }` |
| `signIn()` (`gallery.js:674`) | ✅ `if (this.busy) { return; }` |
| Plex Posters tab (`gallery.html.twig:157`) | ❌ inline `@click`, unguarded |
| Cancel (`gallery.html.twig:287`) | ❌ inline `@click`, would dismiss the confirm bar mid-apply |
| Import form submit | ❌ — see below |

The Import button is the one to be careful with, because it is `type="submit"`
and `disabled` is currently doing a second job nobody wrote down: it also blocks
*implicit* submission. With the default button disabled the form does not submit
on Enter, so pressing Enter on a radio does nothing today. Under `aria-disabled`
it would submit an incomplete selection.

That degrades safely, which is why this is a guard and not a blocker.
`PlexImportController::run` already rejects it at `src/Controller/PlexImportController.php:81`
with *"Select at least one library and one media type."*, and `runImport`
(`gallery.js:1022`) reads `.alert` out of the response and shows it as a notice.
Worst case is a correct error message, not a bad import. The `@submit` guard
exists so the user never has to see it.

*Alternative considered:* `pointer-events: none` on the disabled treatment, which
would stop the click without any handler changes. Rejected — it also kills the
tooltip on the Plex Posters tab, which is currently the only thing that explains
why that tab is off. It would trade an announced state for a silent one.

### What this still does not solve

A reachable, pressable control that refuses should say why. Tooltips here are
hover-and-fine-pointer only (`public/assets/app.js:57`), so on touch, pressing
the off Import button produces silence. For the import stepper the surrounding
steps supply the reason. For the **Plex Posters tab on a phone** they do not, and
that stays unsolved: the reason lives in a tooltip the device cannot show and in
a panel the disabled tab will not open. Noted rather than fixed — it wants its
own answer in `poster-sources`, not a fourth mechanism bolted on here.

### Tighten the re-download option's gate to match the button exactly

`x-show="type"` becomes `x-show="type && sections.length > 0"`, so the option and
the Import action become real at the same instant.

There is a safety invariant underneath, and it is why the *exact* match matters
rather than merely a tighter one. The `force` checkbox has no `x-model`
(`ImportTrayReuseTest:111-124` special-cases it for this reason), so `x-show`
hides it without clearing it, and a hidden-but-checked box would still submit
`force=1`. Today nothing bad happens only because the button's gate is
coincidentally at least as strict. Making the two conditions identical converts
that coincidence into a guarantee: whenever the form can be submitted, the
checkbox is on screen showing its setting. The `visual-design` delta states this
as a general rule so the next hidden-and-submitted control inherits it.

*Alternative considered:* disable the checkbox instead of hiding it. Rejected —
it already hides, hiding is right for it (before a library is picked there is
nothing for the option to modify, so it is the pagination case rather than the
Import-button case), and switching it to a visible-but-off control would put a
dead checkbox in a tray whose whole idiom is progressive reveal.

### Where the tripwire goes

`tests/Unit/Asset/DesignTokenContractTest.php` already parses `app.css` with
comments stripped, and the directory holds fifteen sibling tripwires. A new
`DisabledStateTest.php` fits that pattern: assert that a disabled rule exists,
that the hover rules exclude `:disabled`, and — reading `templates/plex.html.twig`
— that the checkbox's `x-show` condition and the button's `:disabled` condition
name the same two pieces of state.

This is shape, not behaviour. There is no JS runner and CSS is not unit-testable;
the appearance itself is verified by hand against the `:dev` image, as
`ImportTrayReuseTest`'s own docblock says of its subject.

## Risks / Trade-offs

- **The treatment reads as "broken" rather than "not yet".** A control that looks
  disabled but whose reason is not evident is a different failure from the current
  one, not a smaller one. → For the Import button the surrounding steps supply the
  reason; for the Plex Posters tab the panel and tooltip already do. The one to
  check by hand is Sign in to Plex, whose `busy` state is momentary.
- **`:not(:disabled)` on the hover selectors is easy to forget on the next hover
  rule added.** → The tripwire asserts the exclusion on the hover rules that exist;
  it cannot catch a new one. This is the same limit `DesignTokenContractTest`
  documents about itself, and the reason the requirement is written down.
- **Contrast.** `--muted` on `--surface` must stay legible in the tray as well as
  on the page — the tray sits on a translucent surface, so the effective
  background differs from the page's. → Check both during the `:dev` pass.
- **The `.is-disabled` removal.** It is referenced by nothing today, but a future
  script could have wanted it. → Grep before removing; the replacement covers the
  intent anyway.
- **Momentary states may now flicker.** `preview.applying` and `busy` are brief,
  so the new appearance may flash. → Both are already covered by a progress
  overlay, so the flash is mostly hidden. Worth a look, not worth a grace period.
- **A missed guard is now a live action rather than a no-op.** This is the
  regression this change can actually cause: `disabled` enforced on its own, and
  `aria-disabled` does not, so any switched-off control whose handler is not
  guarded becomes pressable. → Five call sites, all enumerated above, all in this
  change's diff; the tripwire asserts every `:aria-disabled` binding in the
  templates has a matching guard, which is the one part of this that a test can
  actually check.
- **`:active` starts matching where it did not.** A consequence of the same
  switch, easy to miss because nothing changes until someone presses. → Covered
  by the `:not([aria-disabled="true"])` exclusion on `.btn:active` and asserted.
- **Three of the five fixes are unobservable until focus management lands**, so
  the accessibility benefit claimed here is partly deferred. → Stated plainly in
  Decisions rather than implied; the two page-level controls are the ones to
  verify by hand.
