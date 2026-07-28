## Context

Two small, unrelated documentation/chrome edits ride together in this change.

**Poster source naming.** Find Posters queries the service at
`POSTER_SOURCE_URL`, whose default is `https://posteria.app`. Today the README
names the service only in the environment-variable table and one FAQ answer; the
intro and the Features bullet describe it as "an online poster search". The
`poster-sources` spec already names the service in its Purpose, but nothing
states that naming it publicly is intended behavior, so the README wording has
drifted before.

**Footer link.** [layout.html.twig:54](templates/layout.html.twig#L54) renders
`{{ app_name }} · v{{ app_version }}` plus an update-note span. `app_name` is a
Twig global set from `AppConfig::APP_NAME` in
[bootstrap.php:120](src/bootstrap.php#L120) — a fixed product name, deliberately
not configurable. The mobile navigation drawer renders the same pair in
`.menu__footer` ([_menu.html.twig:24](templates/partials/_menu.html.twig#L24)).
The project now has a site at <https://marquee.dumbprojects.com>.

Constraints: no new dependency, no config surface, no PHP logic. The footer is
rendered on every authenticated page and on login; `/wall` uses its own
standalone template with no footer and is unaffected.

## Goals / Non-Goals

**Goals:**
- Name posteria.app in the README where Find Posters is described, in plain
  user-facing language.
- Record that naming as a spec requirement so it is not silently dropped again.
- Link the product name to the project website in both footers — the page footer
  and the mobile drawer's — without changing what either displays.

**Non-Goals:**
- Documenting how the poster service works — no endpoints, parameters, response
  shapes, or aggregation mechanics, and no self-hosting guidance. Anything
  beyond "it is the service Find Posters searches" is out.
- Making the project URL configurable, or exposing it to PHP config. It is a
  fixed brand link, like the product name itself.
- Changing `POSTER_SOURCE_URL`, its default, or any poster-fetching code.
- Linking the product name anywhere it is *not* footer chrome — the header
  brand stays `SITE_TITLE` and stays unlinked.

## Decisions

**Hard-code the URL in the template rather than adding a Twig global or config
value.** `app_name` is a global because several templates interpolate it into
markup and metadata; the project URL appears exactly once. Adding a global or an
`AppConfig` constant for a single literal buys indirection with no reuse.
Alternative considered: a `PROJECT_URL` env var — rejected, since a per-install
override of the upstream project link has no plausible use and the spec already
establishes that product identity is not operator-configurable.

**Link the product name only, not the whole footer line.** Each footer also
carries the version and the JS-injected update note; wrapping all of it would
make "v1.2.3" and an update message clickable to the marketing site, which is
wrong. The anchor covers `{{ app_name }}`; `· v{{ app_version }}` and
`.js-update-note` stay outside it. The update-note script targets
`.js-update-note` by class and is unaffected by the surrounding markup — this
holds for both instances of the span.

**`target="_blank"` with `rel="noopener"`.** Marquee is a session-based app
often left open on a dashboard or TV-adjacent screen; navigating the tab away
from the gallery to an external site is a worse default than a new tab.
`rel="noopener"` is required for `_blank` regardless.

**Both footers link, with identical markup.** The page footer and the drawer's
`.menu__footer` render the same `Marquee · v…` credit line, so they get the same
anchor. Treating one as navigation chrome that should avoid outbound links was
considered and rejected: on a phone the drawer footer is the credit line the
user actually sees, and having only one of two identical-looking lines be
clickable is the more confusing outcome. `target="_blank"` matters more here,
not less — it keeps the underlying page in place when the drawer closes.

**Style the links to inherit the footer's muted color, underline on
hover/focus.** Both footers are `var(--muted)` at ~0.8rem; a default-blue link
would be visually loud for what is a credit line. Underline-on-hover plus a
visible focus ring keeps the affordance discoverable and keyboard-accessible.
Implement as one rule selecting both (`.footer a, .menu__footer a`) in
[app.css:90](public/assets/app.css#L90), next to `.footer`, so the two cannot
drift apart.

**README: name the service in three places, none of them technical.** The intro
sentence, the Find Posters feature bullet, and the Acknowledgements section. The
config table row already names it as the `POSTER_SOURCE_URL` default and needs
no change. Wording stays at the level of "powered by the poster search service
at posteria.app".

**Keep every mention at that same level — including ones already in the file.**
The FAQ answer went further, describing what the service aggregates and
inviting the reader to point Marquee at their own instance; that aside is cut
so no mention reads as a pitch to self-host a service this repo does not build.
The `POSTER_SOURCE_URL` row stays in the config table, where a setting listed
among other settings carries no such invitation. The spec states this as a
requirement so the wording does not creep back.

**Spec placement.** The naming requirement goes in `poster-sources` as ADDED
(it is a new concern, not a change to how searching behaves). The footer link
goes in `application-shell` as MODIFIED on the existing "Server-rendered pages
with shared layout" requirement, since that requirement already owns what the
footer displays.

## Risks / Trade-offs

- **The project site could move or lapse, leaving a dead link on every page.** →
  The URL lives in two template lines; changing it is a two-line edit. No cache
  or build artifact pins it.
- **Naming a maintainer-owned hosted service in the README can read as a plug.**
  → Every mention is a plain statement of where results come from, and the spec
  forbids going further — no aggregation details, no self-hosting pitch.
- **The drawer's link sits close to navigation items and could be tapped by
  mistake on a phone.** → It stays in the drawer's footer row, visually separate
  from the nav list and styled as muted credit text rather than a nav item; the
  new tab also means a mis-tap does not lose the user's place in the gallery.
- **Two places to update if the URL changes.** → Accepted for two one-line
  template literals; a shared Twig global would be more indirection than the
  duplication costs. Both live in `templates/` and are found by one grep.
