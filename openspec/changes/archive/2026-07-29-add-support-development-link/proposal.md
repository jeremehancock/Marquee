## Why

Marquee is free and self-hosted, and there is currently no way for a user who
wants to support its development to find out how. The project site now has a
support section at `https://getmarquee.now/#support`, but nothing in the app or
the README points at it.

## What Changes

- Add a **Support Development** link to the shared secondary-navigation macro,
  so it appears in the desktop gallery toolbar and in the mobile menu tray
  alongside Poster Wall, Import from Plex, and Orphans.
- The link opens `https://getmarquee.now/#support` in a new browsing context and
  carries its own icon, matching how Poster Wall already behaves.
- Add a Support Development section to the README linking to the same URL.

The link is unconditional — it does not depend on authentication being enabled,
matching the rest of the secondary navigation.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the secondary navigation set — shared between the desktop
  placement and the mobile menu tray — gains a Support Development entry that
  opens the project's support page in a new browsing context.

## Impact

- `templates/partials/_nav_macros.html.twig` — the `secondary_links()` macro and
  its inline `ic()` icon set gain an entry.
- `templates/partials/_menu.html.twig` and `templates/gallery.html.twig` — no
  edit needed; both already render the macro, which is the point of the shared
  source of truth.
- `README.md` — a new Support Development section.
- `openspec/specs/application-shell/spec.md` — the mobile navigation menu
  requirement enumerates the tray's links and needs the new one added.

This change assumes the new domain is in place; it uses `getmarquee.now`
directly rather than the old host. See the `update-project-domain` change, which
moves the existing footer links.
