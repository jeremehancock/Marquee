## Why

The project website has moved to a new domain, `getmarquee.now`. The footer
links in the page and the navigation drawer still point at the old
`marquee.dumbprojects.com` host, so users who click the product name land on a
stale address.

## What Changes

- Point the page-footer product-name link at `https://getmarquee.now`.
- Point the navigation drawer footer's product-name link at
  `https://getmarquee.now`.
- Update the `application-shell` spec so the two footer-link scenarios name the
  new domain.
- Update the functional test that asserts the footer anchor's `href`.

Link text, `target="_blank"`, `rel` attributes, and the surrounding version and
update-note text are unchanged — only the destination host moves.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the project website the footer links point to is
  `https://getmarquee.now` instead of `https://marquee.dumbprojects.com`.

## Impact

- `templates/layout.html.twig` — page footer anchor.
- `templates/partials/_menu.html.twig` — drawer footer anchor.
- `tests/Functional/ApplicationShellTest.php` — footer `href` assertion.
- `openspec/specs/application-shell/spec.md` — two scenarios naming the domain.

Archived changes under `openspec/changes/archive/` also mention the old domain;
they are a historical record and are deliberately left untouched. No
configuration, environment variable, or Docker change is involved — the URL is
hard-coded in the templates.
