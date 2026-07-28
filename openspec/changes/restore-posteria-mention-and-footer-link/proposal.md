## Why

The **Find Posters** feature is powered by the hosted poster service at
[posteria.app](https://posteria.app), but the user-facing description of the
feature reads as a generic "online poster search" — the service is only named in
a config-table row and one FAQ answer. Naming it plainly, in a general way, tells
users where their poster candidates come from without documenting internals.

Separately, Marquee now has a project site at
<https://marquee.dumbprojects.com>, and nothing in the app points there. The
footer already names the product on every page, which makes it the natural place
to link.

## What Changes

- Name posteria.app as the poster search service in the README's intro and
  **Find Posters** feature bullet, and in the Acknowledgements section — a
  general statement only, with no detail about endpoints, request shapes, how
  the service aggregates its results, or how to self-host it.
- Trim the existing self-hosting aside from the **Find Posters** FAQ answer, so
  every mention stays at the same general level.
- State in the `poster-sources` spec that the service Marquee queries is
  publicly identified to users, so the naming is a specified behavior rather
  than incidental README wording.
- Turn the product name into a link to <https://marquee.dumbprojects.com> in
  both places it appears as chrome: the shared page footer and the mobile
  navigation drawer's footer. Both open in a new tab. Both keep showing the
  product name and version exactly as they do today.

No behavior of the poster search itself changes, no configuration changes, and
no new dependency is added.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `application-shell`: the footer requirement gains a scenario stating that the
  product name links to the project website wherever the layout presents it as
  footer chrome — the page footer and the mobile drawer's footer.
- `poster-sources`: adds a requirement that the poster search service is named
  to users in the product documentation.

## Impact

- `templates/layout.html.twig` — page footer markup becomes an anchor.
- `templates/partials/_menu.html.twig` — drawer footer markup becomes an anchor.
- `public/assets/app.css` — link styling for both footers (inherit the muted
  footer color, hover affordance) so neither reads as default browser blue.
- `README.md` — intro paragraph, Features bullet, FAQ answer, Acknowledgements.
- `openspec/specs/application-shell/spec.md`, `openspec/specs/poster-sources/spec.md`
  — updated via the delta specs in this change.
- No PHP, routing, or configuration changes; `POSTER_SOURCE_URL` keeps working
  exactly as it does today and keeps its row in the configuration table.
