## Context

For a poster with no linked Plex item, the Plex Posters tab is switched off at
`templates/gallery.html.twig:162-165`:

```twig
:aria-disabled="change.linked ? 'false' : 'true'"
:data-tooltip="change.linked ? null : 'This poster is not linked to a Plex item.'"
@click="if (change.linked) { change.tab = 'plex'; … }"
```

The comment above it claims *"Off carries its reason in the tooltip and in the
panel."* Neither clause holds on touch, and the second holds nowhere:

```
tap the off tab ──▶ @click="if (change.linked) …"
                        │
                        └── false ──▶ nothing happens.
                                      change.tab never becomes 'plex'.
                                      the panel never renders.
                                      NotLinked never surfaces.
```

`PlexPosterOutcome::NotLinked` and its message at
`src/Controller/ChangePosterController.php:255` are real, and correct, and
unreachable through the interface. They fire only on a direct API call or when
the client's `data-linked` has gone stale. So the "panel" channel is a defensive
path, not an explanation. The tooltip channel is real but gated on
`(hover: hover) and (pointer: fine)` at `public/assets/app.js:57`.

On a phone, what reaches the user:

| Signal | Pointer | Touch |
| --- | --- | --- |
| `opacity: 0.5` | ✅ | ✅ |
| `cursor: not-allowed` | ✅ | ✗ no cursor |
| suppressed `:hover` | ✅ | ✗ no hover |
| suppressed `:active` colour shift | ✅ | ✗ already `--muted`; invisible |
| `data-tooltip` | ✅ | ✗ gated |

Three archived requirements are violated by that right-hand column:
`poster-sources` (the tab must state why), `application-shell` (suppressing a
tooltip must not remove information a touch user needs), and `visual-design`
(the off state must not be carried by transparency alone).

`draw-the-disabled-state` (archived 2026-08-18) knew. Its design document ends
with a section titled *"What this still does not solve"* naming this exact tab,
and the CSS comment it shipped at `app.css:2478-2491` says *"On touch … the
dimming is doing the work alone. That gap is known and is not solved here."* It
deferred the answer to `poster-sources` rather than bolting a fourth mechanism
onto a change about appearance. This is that answer.

## Goals / Non-Goals

**Goals:**

- The reason a poster has no Plex posters reaches every device, by construction
  rather than by a second implementation for touch.
- No new explanation mechanism. The application already has three.
- Keep every guarantee the current arrangement provides: no request to Plex for
  an unlinked poster, no change to the user's stored poster, a tab strip whose
  shape does not vary from poster to poster.
- Write the general rule down once, where tooltips are specified, so the next
  control that tries to explain itself through a tooltip is caught by a
  requirement rather than by someone noticing.

**Non-Goals:**

- **A touch equivalent of the tooltip.** Long-press, tap-to-reveal, a toast.
  Each is a fourth channel for a message the panel can simply contain, and each
  reopens the device-detection problem `application-shell` closed.
- **A stronger off-state for the tab strip.** See Decisions — the weak instance
  is retired, not strengthened, and designing a tier for a control that does not
  exist could never be verified against the `:dev` image.
- **Focus management for dialogs.** Unchanged and still absent.
- **Retuning `PlexPosterOutcome` or the controller.** The server's model is the
  one being adopted, not revised.

## Decisions

### The tab is empty, not unavailable

This is the whole change. Everything below is consequence.

Compare the neighbouring tab. Find Posters is always available and reports "no
match", "rate limited", "no service covers this type" — `poster-sources` has two
requirement families for distinguishable outcomes. *Not linked* is structurally
the same thing: an empty result with a reason. The server already models it that
way, as one case of `PlexPosterOutcome` among the rest.

The client converts that outcome into a switched-off control. Switched-off
controls are exactly the class of thing that cannot explain itself on touch, so
the conversion is what manufactures the problem:

```
Today                                  After
─────                                  ─────
[Plex Posters]  aria-disabled          [Plex Posters]  available
   tap → nothing                          tap → opens the panel
   reason → tooltip (pointer only)        panel: "This poster is not linked
   reason → panel (unreachable)                  to a Plex item."
                                         no request to Plex
```

A rendered panel has no pointer gate to pass, so the asymmetry does not get
papered over — it stops being load-bearing.

*Alternative considered: hide the tab for an unlinked poster.* Rejected, and
`poster-sources` already rejected it, on the grounds that the strip would change
shape from poster to poster and a user who learned where the tab sits would find
it missing with no reason given. Worth noting that a strict reading of
`visual-design`'s disable-versus-hide rule points at hiding — nothing on this
screen can link the poster, so it is the *"nothing to act on"* case. That
contradiction is real today and **dissolves under this change** rather than
needing to be arbitrated: an available tab that opens to an explanation is
neither the disable case nor the hide case, because it is not switched off at
all. See below for why no clause is being added to settle it.

*Alternative considered: keep the tab off and add an inline hint beside the
strip.* Rejected. It is per-poster clutter in a dialog reused for every poster,
it is the fourth mechanism the previous change warned against, and it leaves the
off tab's own silence intact — the hint explains the tab without the tab
explaining itself.

*Alternative considered: make tooltips fire on touch.* Rejected hardest.
`application-shell` forbids it in detail and for good reasons — a tap has no
hover to end, so the bubble sits over the interface with no way to dismiss it.
Fixing a tooltip-shaped problem by breaking the tooltip contract trades one
requirement for another.

### The explanation is rendered from client state, with no request

`change.linked` is already carried from the card via `data-linked`
(`gallery_results.html.twig:80`), for precisely this reason — its comment says
so. The panel shows the unlinked message directly from it, and
`loadPlexPosters()` is not called.

This preserves the existing scenario's guarantee verbatim: *no request is made
to Plex.* The tab is not "off" any more, but the network behaviour for an
unlinked poster is byte-for-byte what it was.

*Alternative considered: let the request go and render the `NotLinked` the
server returns.* Rejected. It is a round trip whose only possible answer is
already known, against a server that may be slow or unreachable — so the user
could be shown "Could not reach Plex" in place of the real reason. The existing
guarantee exists to prevent exactly that.

The two messages must therefore agree. They already do, word for word, and the
change keeps them identical rather than paraphrasing. That matters when
`change.linked` is stale — an orphan cleanup or re-import between page render
and dialog open — because then the request *does* go, and the server's
`NotLinked` answers with the same sentence the client would have shown.

### The guard moves; it does not disappear

`draw-the-disabled-state` established that `aria-disabled` announces and does not
enforce, so every off control needs a guard at the action. That principle is not
being relaxed — its subject is being removed. The tab is no longer off, so it no
longer needs a guard *for being off*.

What still needs guarding is the **request**, and the guarantee is now stated
about the request rather than about the control:

```
Before:  @click="if (change.linked) { tab='plex'; if (…) loadPlexPosters(); }"
         guard on the control, protecting both the tab switch and the request

After:   @click="tab='plex'; if (change.linked && …) loadPlexPosters();"
         guard on the request alone
```

The guard belongs at the call site *and* inside `loadPlexPosters()`. Two
reasons: the requirement is about Plex not being contacted, so the refusal
should sit where the contacting happens; and the inline `@click` expression is
the fragile half — the previous change's own risk register named unguarded
inline handlers as its likeliest regression.

### The panel gates its request-shaped states on `linked`

The panel currently shows loading, error, an invitation, and two groups. For an
unlinked poster none of those apply — there was no request, so there is no
loading and no failure — and the unlinked message replaces the lot. This keeps
the three empty states distinguishable, which `poster-sources` requires of the
two that arise from a request and now requires of this one too.

The distinction to hold onto: *not linked* is **not** a third request outcome.
`poster-sources` says of the request that *"there are two, not the five a title
search can produce"*, and that stays true — no request is made here, so there is
nothing for it to be an outcome of. The delta states the boundary rather than
folding this into the outcomes requirement.

### The dialog stays wide on the Plex tab, even with one line in it

`modal__panel--wide` is applied when the tab is `find` or `plex`. For an
unlinked poster the tab now holds a single sentence, and a wide dialog around
one sentence looks like a mistake.

It stays wide anyway. The width is a property of the tab, and making it depend
on the poster reintroduces exactly what the requirement rejects one level up —
a dialog that changes shape from poster to poster. Trading a steady strip for a
jumping panel would answer the requirement's letter and lose its argument.

*Alternative considered: narrow it when unlinked.* Rejected on the above. Also
practical: the width would then change as the user moves between an unlinked
poster and a linked one in the same session, which is the disorientation the
whole requirement is about.

### Keep the `.modal__tabs` off rules with no caller, and rewrite their comment

After this change no tab in the application is switched off, so
`.modal__tabs button[aria-disabled='true']` has zero call sites.

The rules stay. `draw-the-disabled-state` kept `:disabled` alongside
`aria-disabled` for a control that did not exist either, on the reasoning that a
control added later must not silently arrive untreated. That reasoning is
unchanged and applies identically here: `opacity: 0.5` and a cursor for the next
off tab beats nothing for the next off tab.

The comment does not stay. It currently says the strip *"leans on the tooltip and
on the panel to carry the reason"* — about to be false — and books the touch gap
as a known unfixed defect. It is rewritten from a confession into the contract a
future off tab inherits: the inactive tier is already `--muted`, so there is no
room beneath it, and a tab switched off here owes a signal that is not a further
step down and owes its reason to the panel rather than to a tooltip.

*Alternative considered: delete the rules along with their only caller.*
Rejected. It makes the next off tab arrive with no treatment at all, which is
the precise failure `draw-the-disabled-state` existed to fix.

*Alternative considered: invent a proper off tier for the strip now.* Rejected.
It is design against an imagined control, and this repo verifies appearance by
hand against the `:dev` image — a tier with no instance could not be looked at.
The constraint is recorded in the comment so whoever needs it has it.

### Re-anchor the `pointer-events: none` ban, do not lift it

`DisabledStateTest::testTheOffStateDoesNotSuppressPointerEvents` justifies itself
by the tooltip it would silence — *"the only thing that explains why that tab is
off."* After this change no switched-off control in the application carries a
tooltip, so the justification is orphaned even though the assertion is still
right.

The better anchor was always available: `pointer-events: none` makes an element
non-hit-testable, and `cursor: not-allowed` requires hit testing. The two are
mutually exclusive, so the shortcut silently deletes a signal `visual-design`
explicitly requires — *"The pointer cursor SHALL likewise indicate that the
control will not respond."* It also drops the tap through to whatever sits
behind the control.

This anchor is strictly better than the one it replaces: it is a fact of CSS
rather than a property of one call site, it is derivable from a requirement, and
it needs no example — so it cannot be orphaned again the way the tooltip anchor
just was. The assertion itself does not change.

### No new clause in `visual-design`

The disable-versus-hide contradiction described above is real, and the temptation
is to settle it with a third clause — something like *a control belonging to a
set whose shape is itself information stays in place even where nothing on this
screen can revive it, provided it carries its reason*.

Rejected. The clause would be born with zero call sites: this change removes the
only control it would classify. Writing a requirement to arbitrate a dispute the
same change dissolves is spec surface for a hypothetical, against a repo whose
stated habit is writing down what is expensive to get wrong *and has bitten*.

Nothing is lost. The argument for keeping the tab visible — the strip's shape —
already lives in `poster-sources` and stays there. It simply stops being framed
as a disable-versus-hide decision and becomes the simpler claim that the tab is
always present.

### The tripwire goes in `DisabledStateTest`, over the roster it already has

`DisabledStateTest::switchedOffControls` enumerates every `:aria-disabled`
binding in the templates and pairs each with its guard. The Plex Posters tab
leaves that roster, taking it to three.

The same provider is the natural home for the new rule, because the invariant is
about the intersection of two things it already knows: assert that no listed
switched-off control's markup carries a `:data-tooltip` bound on the same state
as its `:aria-disabled`. That pattern is pointer-only by construction — it is
the shape of the defect, not an instance of it — and it fails today on exactly
one element, the one being fixed.

Two further assertions, both shape rather than behaviour, as the file's own
docblock says of its subject:

- `templates/gallery.html.twig` no longer binds `aria-disabled` on the tab, and
  the Plex panel contains the unlinked message.
- `loadPlexPosters` is guarded on `change.linked`, so the no-request guarantee
  has a tripwire and not just a scenario.

## Risks / Trade-offs

- **A tab that opens to a dead end may read as worse than one that will not
  open.** → It is not a dead end; it is an answer. The refusal raises the
  question the panel now answers, and the current arrangement answers it on
  roughly half the devices in use.
- **The `@click` expression is unguarded for the tab switch, and a later edit
  could drop the request guard with it.** This is the regression this change can
  actually cause: an unlinked poster triggering a Plex request. → The guard is
  duplicated inside `loadPlexPosters()`, where the request is made, and pinned by
  a tripwire. The failure is also benign if it ever happens: the server returns
  `NotLinked` with the same sentence.
- **`change.linked` can be stale.** → It already can be, and the server already
  handles it. The change makes the two paths agree word for word rather than
  introducing the divergence.
- **The `.modal__tabs` off rules become untested by instance** — kept, described,
  and drawn by nothing. → Accepted deliberately and stated in the comment. The
  alternative is deleting them and reintroducing the untreated state for the next
  caller.
- **`DisabledStateTest`'s roster shrinking makes the file look like it is losing
  coverage.** → It gains an assertion as it loses a row, and the row it loses is
  a control that no longer exists. Worth saying in the commit message so the diff
  does not read as a weakening.
- **Nothing here is verified by an automated test at the pixel level.** → As with
  every sibling tripwire in `tests/Unit/Asset/`, the appearance and the touch
  behaviour are checked by hand against the `:dev` image. On a phone, on an
  unlinked poster, the tab must open and state its reason.
