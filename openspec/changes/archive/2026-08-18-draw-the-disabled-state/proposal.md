## Why

A control the application has switched off looks exactly like one that works.
The Import button sits in the tray as a full-width accent bar before anything is
selected, and on a desktop it brightens under the pointer — so a user presses it,
nothing happens, and nothing on screen says why. The same is true of every other
switched-off control in the app: signing in while a sign-in is already running,
the Plex Posters tab on a poster with no Plex item, and the buttons under a
poster change that is already being applied.

The cause is that "unavailable" was never drawn. The stylesheet has no rule for
a disabled control at all, so `disabled` currently changes what a control *does*
and nothing about how it *reads*. Two code comments already claim the appearance
communicates on the user's behalf, and neither is true today.

## What Changes

- Give a switched-off control a visible appearance across the whole application,
  drawn from the existing design tokens: a plain surface rather than the accent
  fill, muted text, and a cursor that says the control will not respond.
- Stop a switched-off control reacting to the pointer. Suppressing the hover
  brightening and the press movement is the substance of the fix — an inert
  control that lights up when pointed at actively misleads.
- State when a control should be switched off and left in place versus removed
  from the screen entirely, so the two patterns the application already uses stop
  being decided case by case. A control that the user can bring to life from the
  same screen stays put and shows itself as unavailable; a control with nothing
  to act on in the current state is not shown at all.
- On the import form, make the "Re-download unchanged posters" option appear on
  exactly the same condition that makes the Import button usable. Today it
  appears one step early: choose a content type but no library, and the option is
  live while the import it modifies cannot run.
- Keep a switched-off control reachable by keyboard and have it announce that it
  is unavailable, instead of vanishing from the tab order as it does today. A
  control that cannot be reached is not reported as unavailable — it is not
  reported at all.
- Stop a control that switches itself off from throwing the user's focus away.
  Three of the five do this: they become unavailable in response to being pressed,
  and today that drops a keyboard user's focus to the top of the document
  mid-task.
- Refuse the underlying action wherever it is performed, so that a control which
  is now reachable, and therefore still operable, cannot act. This includes a form
  submitted without its button being pressed.
- Retire the unused `.is-disabled` rule, which is the vestige of an earlier
  attempt at this and is referenced by nothing.

Not in this change, and deliberately: **focus management for dialogs** — no
dialog in the application moves focus into itself, keeps focus inside, restores
focus on close, or makes the page behind it inert. That gap is what makes three
of the five controls here reachable in principle and not yet in practice, and it
is a change in its own right. Also out: a step legend for the re-download option,
and an in-flight state for the settings form.

## Capabilities

### New Capabilities

None. Both halves land on capabilities that already exist.

### Modified Capabilities

- `visual-design`: adds the missing member of the interaction-state family. The
  capability already requires hover, focus, and press feedback on every
  interactive element and says nothing about the unavailable state. This adds
  three requirements — how an unavailable control looks, that it stays reachable
  and reports itself and does not act, and the rule for choosing between switching
  a control off and removing it.
- `plex-import`: the re-download option becomes available on the same condition
  as the Import action rather than on a looser one, so no import option can be
  set while it cannot take effect.

## Impact

- `public/assets/app.css` — the disabled treatment, the suppression of hover and
  press while disabled, and the removal of the dead `.is-disabled` rule.
- `templates/plex.html.twig` — the re-download option's visibility condition, and
  the Import button and its form.
- `templates/gallery.html.twig`, `templates/connect.html.twig` — the four
  remaining switched-off controls.
- `public/assets/gallery.js` — guards at the two actions that do not yet have
  one, so a reachable control still cannot act.
- No PHP, no controller, no service, no database, no configuration. Nothing ships
  or is stored differently, and the server already refuses an incomplete import
  on its own.
- Five controls change appearance and become keyboard-reachable without changing
  what they do when used normally: Import, Sign in to Plex, the Plex Posters tab,
  and the Change poster / Cancel pair under a poster preview.
- Closes a long-standing conformance gap: the import flow's specification already
  required the Import action to be "unavailable" before the selection is
  complete. It was made unavailable and never made to look it.
