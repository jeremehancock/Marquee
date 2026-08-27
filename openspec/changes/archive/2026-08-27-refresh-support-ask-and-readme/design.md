## Context

Three edits to text, in two places that are read at different moments.

**The README.** It opens with a blockquote warning that Marquee is "Early Alpha —
not ready for general use", which is the first thing an evaluator sees and is no
longer true. A second alpha claim sits further down, in the `PLEX_TOKEN` upgrade
note at line ~292 ("Marquee is in alpha and this is a breaking change"), where it
is doing rhetorical work — it explains why the maintainer felt free to break the
old way of connecting. Removing only the blockquote leaves that line as the last
surviving alpha statement in the file, which reads as an oversight.

The README also already has a `## Support Development` section at the end. It is
prose, and it names two destinations (the site's `#support` anchor and the in-app
overlay) but presents no clickable button. The sibling project Glimpse puts a Buy
Me A Coffee badge image right after its Features list. The two READMEs are written
in different registers — Glimpse uses emoji headings throughout, Marquee uses
none — so this change takes Glimpse's *badge*, not Glimpse's heading or its
placement.

**The app.** `templates/partials/_support.html.twig` renders the overlay. Its one
call to action is an `<a class="btn btn--accent">` reading "Hard drive fund",
pointing at `https://www.buymeacoffee.com/jeremehancock`. The label is a callback
to the joke in the paragraph immediately above it ("another hard drive for my Plex
server that I absolutely need"). A user who reads the paragraph gets the joke; a
user who skims to the button — which is what a dialog invites — sees a control
naming nothing they can act on.

The label appears in six places across three kinds of file: the template, the
`application-shell` spec (three times), and `ApplicationShellTest` (twice — once
as an assertion, once inside a failure message).

## Goals / Non-Goals

**Goals:**

- The README no longer describes Marquee as alpha, anywhere.
- The README's support section carries a visible, clickable Buy Me A Coffee badge.
- The in-app support button names its destination: "Buy me a coffee".
- Spec, tests and template agree afterwards, with no orphaned reference to the old
  label.

**Non-Goals:**

- Rewriting the support paragraph. Its hard-drive joke is the *ask*, and it still
  reads once the button stops repeating it. Changing the copy would mean changing
  the spec's description of the copy and diverging from the project site, which is
  a separate repository and a separate decision.
- Moving the README's support section, renaming it, or adding emoji headings.
- Touching `getmarquee.now` (the `Marquee-Site` repository), which carries the same
  copy and would drift by exactly one paragraph — acceptable, and noted below.
- Adding a badge or button anywhere else in the README.
- Any change to the link target, the new-tab behaviour, or the dismiss-on-activate
  behaviour.

## Decisions

**1. The badge goes into the existing `## Support Development` section, not into a
new Glimpse-style section after Features.**

Glimpse's arrangement is `## ❤️ Support this project` right after Features,
containing only the badge. Copying that into Marquee would mean either two support
sections or deleting prose that is doing real work — Marquee's section explains
that the app is free and stays that way, and points at the in-app overlay, which
Glimpse has no equivalent of. Marquee's README also has no emoji in any heading,
so `## ❤️ Support this project` would be the only one.

So: keep the section, keep its name, keep its place, add the badge markdown after
the prose. The badge is the part of Glimpse's treatment that was actually missing.

*Alternative considered:* moving the section up to sit after Features for
visibility. Rejected for this change — Marquee's README is a long prose document
whose top matter (intro, Features, Quick start) is what a first-time reader needs;
interrupting it with an ask is a product decision worth making on its own rather
than as a side effect of adding a badge.

**2. The badge is the standard Buy Me A Coffee yellow button image, linked to the
same URL the app uses.**

`[![Buy Me A Coffee](https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png)](https://www.buymeacoffee.com/jeremehancock)`

Identical to Glimpse's line, so the two projects present the same mark. It is a
remote image on GitHub's proxy, which is what every badge in every README is; no
asset is vendored.

**3. The `PLEX_TOKEN` note is reworded to keep the fact and drop the excuse.**

Current: "**`PLEX_TOKEN` is no longer read.** Marquee is in alpha and this is a
breaking change: one way to connect replaced two."

The sentence carries two things — that the variable is gone, and a justification
resting on alpha status. Only the first is durable. The rewrite keeps the bold
lead and the "one way replaced two" explanation, and drops the alpha clause. The
whole "Upgrading from a version that used `PLEX_TOKEN`" subsection stays; it is
still the instruction anyone upgrading needs.

**4. The new label is "Buy me a coffee" — sentence case, not the brand's
"Buy Me a Coffee".**

The project's naming rule (CLAUDE.md) says surfaces the interface offers *by name*
are Title Case and everything else a user reads is sentence case, with actions
given as the example: "Change poster", "Send to Plex", "Copy URL". This button is
an action, not a named surface — Support Development is the named surface, and it
is the heading. So the label is sentence case.

That it visually resembles a brand name is a coincidence of the destination. The
spec prose still refers to "the project's Buy Me a Coffee page" in Title Case,
because there it *is* naming a thing.

**5. The label stays quoted literally in the test; the heading does not.**

`ApplicationShellTest` deliberately compares the overlay's heading, its
`aria-label` and the nav entry's label *to each other* rather than to a literal,
because that name lives in two files and drifted once. The button label lives in
exactly one file and has nothing to agree with, so asserting the literal string is
correct and the two assertions stay as they are with the string swapped.

The spec delta says this explicitly, so a future reader does not "fix" the button
assertion into the comparison style and lose the check.

**6. No new test.**

`ApplicationShellTest` already asserts the call to action is present on every page
that renders navigation, that the overlay holds exactly one outbound link, and
that activating it dismisses the ask. Swapping the string keeps all three honest.
The one genuinely new spec scenario — "the button names its destination rather
than the joke above it" — has a testable second clause (the old label appears
nowhere), and that is worth one small assertion; it is folded into the existing
label test rather than given a method of its own, because it is the same fact
stated negatively.

## Risks / Trade-offs

**The project site keeps the old button label** → `Marquee-Site` is a separate
repository with its own deploy. After this change the app says "Buy me a coffee"
and the site's version of the same card may still say something else. This is a
one-line follow-up in that repo, not a blocker, and the two surfaces are never
seen side by side. Noted here so it is not rediscovered as a bug.

**The badge is a remote image** → If `cdn.buymeacoffee.com` goes away the README
shows broken alt text ("Buy Me A Coffee"), which still reads and still links.
Same exposure as every CI badge; not worth vendoring an image for.

**Removing the alpha notice raises the expectation bar** → A reader who would have
been warned off now installs without one. That is the intent of the request, and
the README's Quick start already tells people to back up `/config`; nothing in the
file promised stability that this removal now over-claims.

**A stale "alpha" could survive somewhere else** → Mitigated by grepping the whole
repository for alpha mentions rather than editing the two known lines. Today the
only hits outside `README.md` are false positives ("alphabetical", "alphabetised"),
so the task list makes the grep explicit and scoped to whole-word matches.

## Migration Plan

None. Text-only; no data, schema, configuration, or container change. Rollback is
`git revert`.

## Open Questions

None. The two scope questions (where the badge goes, and whether the second alpha
mention is in scope) were settled with the user before this document was written;
their answers are Decisions 1 and 3.
