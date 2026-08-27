## Context

Marquee names things in two registers, and until now neither was written down.

**Sentence case** carries everything a user *does* or *reads*: every action
("Change poster", "Send to Plex", "Fetch from Plex", "Copy URL", "Full screen",
"Save settings", "Delete all orphans", "Clear search"), every confirmation title
("Delete poster?", "Send to Plex?", "Are you sure?"), every form label ("Image
file (JPG, PNG, or WebP)", "Image URL"), every settings section heading
("Presentation", "Auto-import", "Session", "Updates", "Libraries"), and every
positional accessible name ("First page", "Next page", "More actions", "Poster
actions", "Sort order").

**Title Case** carries the handful of things the application offers *by name* —
Poster Wall, Plex Connection, Plex Posters, Find Posters, Import from Plex,
Support Development. These read as proper nouns in the interface and in the
project's own prose alike: `PosterWallController` documents "the Poster Wall",
`connect.html.twig` tells the user "the Poster Wall is unaffected".

An audit of every user-facing string — visible text, `aria-label`, `data-tooltip`,
page `<title>`, and every tray, dialog and page heading — found exactly one place
where a single surface is spelled two ways:

| Surface | Names it uses | Agrees? |
| --- | --- | --- |
| Poster Wall | nav label, short form "Wall", `<title>` | yes |
| Import from Plex | nav label, short form "Import", tray title, `<h1>`, `<title>` | yes |
| Orphans | nav label "Orphans"; tray title, `<h1>`, `<title>` all "Orphaned posters" | yes — see below |
| Settings | nav label, tray title, `<h1>`, `<title>` | yes |
| Plex Connection | tray title, `<h1>`, `<title>` | yes |
| Log out | nav label, short form | yes |
| **Support Development** | nav label "Support Development"; overlay `<h2>` and `aria-label` "Support development" | **no** |

Two entries in that table need their "yes" explained, because both look like
divergences and neither is:

- **Short forms are not second names.** The desktop header shares a fixed content
  column with a brand of unbounded length, so `item()` in `_nav_macros.html.twig`
  renders both a full label and a short one and lets CSS choose. The short form is
  hidden from assistive technology and the entry's `aria-label` always carries the
  full name, so the label shortens and the accessible name never does. That is a
  width decision already specified, not a naming one.
- **"Orphans" and "Orphaned posters" are a name and a description**, and the
  application uses each consistently in its own register: "Orphans" names the
  destination in navigation, "Orphaned posters" describes what the page holds and
  appears identically in all three places that describe it. Renaming either to
  match the other would flatten a distinction the interface is making on purpose.

The reason the table is otherwise clean is structural rather than careful. Every
label that appears at both widths has exactly one source: `_nav_macros.html.twig`
emits each navigation entry once and both placements call it; `_sort.html.twig`
emits the sort buttons once and the desktop toolbar and the phone tray both call
it; the card action labels come from one `action_body()` macro and the touch
action sheet clones that same markup rather than re-rendering it. A mobile/desktop
divergence in those is not merely absent — it is unrepresentable.

The support ask is the exception precisely because it escapes that structure. The
overlay lives in `_support.html.twig`, the entry that opens it is a call to
`item()` in `_nav_macros.html.twig`, and the name is a literal in each. Nothing
connects them, so they were free to disagree, and they did.

## Goals / Non-Goals

**Goals:**

- The support overlay names itself what the entry that opened it named it.
- The naming rule the application already follows is written into the spec, so a
  new label has something to conform to instead of a majority to be inferred from.
- The rule is pinned by a test at the one joint where it can break: a surface
  named in one file and opened from another.

**Non-Goals:**

- **Renaming any established surface.** Poster Wall, Plex Connection, Plex Posters
  and Find Posters keep their names. They are already spelled one way everywhere.
- **Re-casing prose.** Sentences stay sentences; a name inside a sentence keeps its
  Title Case exactly as it does today.
- **Docs and code comments.** The audit's scope is what a user reads in the running
  application. `README.md`, `docs/` and in-code commentary already use the Title
  Case names and are unaffected.
- **A general string-casing linter.** Discussed and rejected below.
- **Any behavioural or visual change.** No control gains, loses, or moves a
  binding; no CSS changes.

## Decisions

### The overlay takes the entry's name, not the reverse

"Support Development" wins over "Support development".

Both directions produce internal consistency, so the tie is broken on which
register the surface belongs to. Support Development is a named surface: it is
offered by name in navigation, sits in a list beside Poster Wall and Plex
Connection, and is referred to by that name throughout the spec's existing
scenarios and the project's own commentary. Sentence-casing it would make it the
only named surface in the list not spelled as a name, and would pull "Poster Wall"
and "Plex Connection" after it by the same argument — which is a rename of four
established features, in service of a rule the application does not follow.

*Alternative considered:* sentence case everywhere, with no exception list. It has
the simpler rule and the worse outcome. "Poster wall" and "Plex connection" would
disagree with the page titles, the controller docblocks, the connect screen's
prose, and the README — so the "one rule, no exceptions" saving is spent
immediately on a much larger reconciliation.

### The rule is stated as name-versus-not, not as a word list

The requirement distinguishes a **named surface** (a destination or overlay the
interface offers by name) from **everything else** (actions, descriptions,
confirmations, positional names), rather than enumerating the six Title Case
strings that exist today.

A word list answers "is this string spelled right" and cannot answer "what should
I call the thing I am adding" — which is the question that actually gets asked,
and the one that produced this divergence. A rule stated as a distinction survives
the next surface; a list has to be edited by the person who least knows it exists.

### The test pins the joint, not the strings

The new assertion is that **a navigation entry's label equals the name of the
surface it opens** — read out of the rendered markup on both sides, not compared
against a hard-coded expectation.

This is deliberate about what it can and cannot catch. It catches the exact defect
being fixed, and catches it again if either side is edited alone, because the two
sides are compared to each other. It cannot catch a brand-new overlay named
nothing like its opener, or a surface whose casing register is simply chosen
wrong — no test can, which is why the rule is written into the spec in prose as
well. This is the same shape as the hazards already recorded in `CLAUDE.md`: pin
what is pinnable, then write down what is not.

*Alternative considered:* a linter over every string in `templates/` asserting
Title Case appears only in an allow-list. Rejected. It cannot tell a name from a
sentence containing one — "the Poster Wall is unaffected" is correct prose that
such a check flags — so it would need an exception list per string, which is the
word list above with more machinery and the same blindness.

### The heading and the accessible name change together, and stay one decision

`_support.html.twig` states the name twice: once in the `<h2>` and once in the
panel's `aria-label`, which the focus manager announces when focus lands. Both
change. They are not deduplicated into a variable — the surrounding overlays all
spell their names out in both places, and introducing a local variable for one
overlay would make it the odd file without making it more correct. The new test
compares both against the navigation entry, so a future edit to one alone fails.

### The support ask requirement stops quoting the name independently

The existing `In-app support ask` requirement quotes the heading as a literal
string. That literal is the thing that drifted from the navigation entry, so the
restated requirement says the heading is *the name the navigation entry uses* and
gives the current spelling — the constraint is the agreement, and the spelling is
an instance of it.

## Risks / Trade-offs

- **The rule is a judgement call at the margin, and always will be.** Is a new
  overlay a named surface or a described one? → The requirement's own examples do
  the work: it lists what today falls on each side, including the "Orphans" versus
  "Orphaned posters" pair, which is the clearest illustration of the boundary
  precisely because both spellings are correct for their own register.

- **The new test reads rendered markup and is therefore coupled to nav and overlay
  structure.** A refactor of `_nav_macros.html.twig` or `_support.html.twig` could
  break it for reasons unrelated to naming. → It extracts by the attributes that
  are already load-bearing and already asserted elsewhere in
  `ApplicationShellTest` — the entry's `aria-label` and the panel's `aria-label` —
  so it fails alongside the existing assertions rather than in isolation, and a
  refactor that breaks it has broken those too.

- **The user-visible change is one capital letter, and could read as churn in the
  release notes.** → It is a letter a screen reader announces as a different name
  one gesture after the user chose the first one. The rule and the test are the
  substance of the change; the letter is what made it findable.

## Migration Plan

None. No stored data, no configuration, no route, no cached asset carries the
string — it is rendered from the template on every request. Rollback is reverting
the commit.

## Open Questions

None. The direction (Title Case for named surfaces) and the audit's scope (visible
labels plus accessible names, excluding docs and comments) were both settled with
the user before this document was written.
