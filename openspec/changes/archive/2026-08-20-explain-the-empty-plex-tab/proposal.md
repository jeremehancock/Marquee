## Why

On a phone, the Plex Posters tab refuses and says nothing. For a poster with no
linked Plex item the tab is switched off and its only explanation lives in a
tooltip — and tooltips are a hovering-fine-pointer affordance by design
(`public/assets/app.js:57`), so a touch user gets a slightly dimmed tab and
silence.

This is not an open design question. Three requirements already forbid it, and
all three are being violated today:

- **`poster-sources`** — *"The tab explains itself when a poster has no Plex
  item"* requires the tab to be present *"with an explanation that it is not
  linked to a Plex item"*. On touch there is no explanation.
- **`application-shell`** — *"Suppressing tooltips SHALL NOT remove any
  information a touch user needs"*. The reason a control is refusing is such
  information, and it is removed.
- **`visual-design`** — *"The state SHALL NOT be conveyed by transparency
  alone."* On touch, the cursor, the suppressed hover and the tooltip all fall
  away, leaving `opacity: 0.5` doing the work by itself.

The reason the tab is not simply hidden makes the gap worse rather than
better. `poster-sources` keeps it visible so the tab strip does not change
shape from poster to poster, on the argument that *"a disabled tab that says
why is steadier"* than an unexplained absence. On touch it does not say why —
so the application is running the option it rejected, an unexplained hole in
the strip, while still paying the cost of a dead control occupying a slot.

## What Changes

**The tab is not unavailable. It is empty.** That reframing is the change; the
rest follows from it.

Plex answers for an unlinked item the same way it answers any other empty
result, and the application already models it that way — `PlexPosterOutcome`
carries `NotLinked` alongside its other outcomes, and
`ChangePosterController` already maps it to *"This poster is not linked to a
Plex item."* The client short-circuits that model, converting an **outcome**
into a **switched-off control** — and switched-off controls are precisely the
things that cannot explain themselves on touch.

- The Plex Posters tab becomes available for every poster, linked or not. It is
  no longer marked unavailable and no longer carries a tooltip.
- Opening it for a poster with no linked Plex item shows the reason in the
  panel, in the same words the server already uses, **without contacting
  Plex** — the client already knows from the card whether the poster is linked.
- The reason therefore reaches every device by construction, because a rendered
  panel has no pointer gate to pass.
- State in `application-shell` that a tooltip may never be the sole carrier of
  the reason a control is refusing, and that retaining an accessible name does
  not discharge that obligation. This is the general rule the tab was the first
  instance of; it is written down so the next one does not have to be found the
  same way.

Not in this change, deliberately:

- **A new explanation channel.** The change adds no mechanism. It uses the
  empty-state vocabulary this tab already has, which is the point — an
  additional channel would be a fourth way to say something the application can
  already say three ways.
- **A stronger off-state for tab strips.** The strip has no tier below `--muted`
  for an available-but-inactive tab, so its off state falls back to opacity.
  That weakness is *retired rather than strengthened*: after this change the
  application has no switched-off tab at all. The rules stay, uninstantiated, so
  a tab switched off later does not arrive untreated — the same reasoning that
  kept `:disabled` alongside `aria-disabled`.
- **Focus management for dialogs.** Still absent application-wide, still a
  change in its own right.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: the mechanism by which the Plex Posters tab explains an
  unlinked poster. The requirement's intent is unchanged — the tab is still
  always present and still states why it has nothing. What changes is that it
  states it by opening to an explanation rather than by refusing with a tooltip,
  so the explanation is not conditional on the input device.

  Expressed as a removal and an addition rather than a modification, because one
  scenario loses its subject rather than changing: "A disabled tab cannot be
  opened" cannot be edited into truth once there is no disabled tab. The
  guarantee inside it — that an unlinked poster causes no request to Plex — is
  kept and restated. The replacement is named "The tab **opens** and explains
  itself when a poster has no Plex item"; the one added word is the whole of what
  changed.
- `application-shell`: the tooltip requirement gains the clause its own safety
  sentence assumed. It already says suppressing tooltips must not remove
  information a touch user needs, but discharges that with *"remains usable and
  retains its accessible name"* — which the tab passes while failing the
  sentence. The cause is that the requirement models tooltip content as exactly
  two things, a truncated repetition or a hint. A reason a control is refusing
  is a third kind, and it is now named.

`visual-design` is deliberately **not** modified. Its opacity clause is the
right rule and it is being brought into conformance by subtraction: the only
control whose off state was carried by opacity alone stops being off.

## Impact

- `templates/gallery.html.twig` — the tab loses its `:aria-disabled` binding,
  its `:data-tooltip`, and its click guard; the Plex panel gains the unlinked
  message. The call-site comment's claim that the reason lives *"in the panel"*
  becomes true for the first time.
- `public/assets/app.css` — no rule changes. The `.modal__tabs` off rules are
  kept with no caller; their comment is rewritten from a confession of the touch
  gap into the constraint a future off tab inherits.
- `tests/Unit/Asset/DisabledStateTest.php` — the Plex Posters tab leaves the
  roster of switched-off controls, leaving three. A new assertion over that same
  roster pins the general rule: no switched-off control carries its reason in a
  tooltip. The `pointer-events: none` ban stays and is re-anchored — it was
  justified by the tooltip it would silence, and is better justified by the fact
  that it also makes `cursor: not-allowed` unreachable, which `visual-design`
  requires.
- No PHP, no controller, no service, no database, no configuration, no network
  behaviour. `PlexPosterOutcome::NotLinked` and its message are untouched; the
  client now agrees with them rather than pre-empting them.
- One user-visible behaviour change: on a poster with no Plex item the tab can
  be opened and answers. Nothing is imported, changed, or sent.
