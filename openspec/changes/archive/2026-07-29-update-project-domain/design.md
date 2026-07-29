## Context

The project website URL appears in exactly three live places: the page footer
anchor in `templates/layout.html.twig`, the drawer footer anchor in
`templates/partials/_menu.html.twig`, and the assertion in
`tests/Functional/ApplicationShellTest.php` that both anchors carry the right
`href`, `target`, and `rel`. It is a literal string in the templates — there is
no config object, environment variable, or Twig global carrying it today. The
`application-shell` spec names the domain in two scenarios.

The old host, `marquee.dumbprojects.com`, is being replaced by
`getmarquee.now`.

## Goals / Non-Goals

**Goals:**

- Both footer links point at `https://getmarquee.now`.
- The `application-shell` spec and the functional test agree with the templates.

**Non-Goals:**

- Introducing a `PROJECT_URL` environment variable or Twig global. The URL is a
  fixed property of the product, like the product name, and the spec already
  states the product name is not configurable.
- Rewriting archived changes under `openspec/changes/archive/`. Those record
  what was true when they shipped.
- Any redirect, DNS, or hosting work — the new domain is assumed live and
  serving.

## Decisions

**Keep the URL hard-coded in the templates.** The alternative is threading it
through the config layer as an env-backed value, which would let deployments
repoint the footer at an arbitrary host. That is not a capability anyone asked
for, and it would contradict the existing "product name is not configurable"
stance. A literal swap in two templates is the smaller and more honest change.

**Do not centralise the two occurrences into a shared partial.** Two anchors in
two files with different surrounding markup (the drawer's also closes the tray
via `@click="menuOpen = false"`) do not justify an abstraction; the test already
pins both, so drift is caught.

**Update the test in the same commit as the templates.** The assertion is an
exact-string check on `href`, so template-only edits fail the suite. Both move
together.

## Risks / Trade-offs

- **The old domain may still be linked from elsewhere (README, Docker labels,
  external sites) →** a repo-wide grep for `dumbprojects` is part of the task
  list, so anything outside the three known files is caught rather than
  assumed absent.
- **Hard-coding means a future domain move touches code again →** accepted; a
  domain change is rare and the blast radius is three lines.
