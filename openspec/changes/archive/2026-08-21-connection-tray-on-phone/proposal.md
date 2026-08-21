## Why

On a phone every other secondary destination — Import from Plex, Orphans,
Settings, Support Development — opens as a tray over the gallery. The Plex
connection is the one entry left that navigates away, so checking which server
Marquee is talking to, or reading what disconnecting costs, means leaving the
gallery and finding the way back through a "Back to gallery" link instead of the
swipe and backdrop the device already offers. The connection status is the entry
users touch most casually — it is a reading, not an errand — and it is the only
one that charges a page load for a glance.

## What Changes

- The Plex connection status opens the connection screen as a tray over the
  current page on a narrow touch screen, instead of navigating to `/connect`.
- The tray uses the same presentation the Import, Orphans and Settings trays use:
  the taller sheet reserved for a tray that holds a whole page, with the shared
  grab handle, backdrop, swipe-down and Escape dismissal.
- The tray is fetched on every open rather than cached, because what it reports
  decays: the connection screen asks Plex for the server's name and can find the
  connection gone since the page behind was rendered.
- Disconnecting from the tray still navigates. It is the one action on that
  screen, and it makes the gallery behind the tray unusable — so the browser
  follows the form to the connection screen, where the confirmation and the way
  back in are.
- The connection screen remains a page in its own right. A pointer/desktop
  screen, a direct link, and any page that hosts no tray keep reaching it by
  navigating, exactly as Settings does.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: "App-wide mobile actions menu and its destinations" gains
  the connection screen as a tray destination — the requirement currently names
  Import, Orphans, Settings and Support Development as the entries that open over
  the current page, and lists the connection nowhere. "The Plex connection is
  shown as a status, not a destination" is amended so that the status still
  leads to the connection screen but reaches it as a tray on a narrow touch
  screen, and so that disconnecting from the tray is specified to navigate.

## Impact

- `templates/partials/_nav_macros.html.twig` — the connection status anchor gains
  the tray-bridge data attribute, in the one place both its placements are drawn.
- `templates/gallery.html.twig` — a new connection tray beside the existing four.
- `public/assets/gallery.js` — one more entry in the secondary-destination bridge
  and the open/close pair for the tray, reusing `_loadTray` / `_injectTray`.
- `tests/Unit/Asset/` — a shape tripwire alongside `SettingsTrayTest`,
  `ImportTrayReuseTest` and `OrphansTrayRescanTest`; `DialogFocusTest` and
  `TrayDismissalTest` extend to cover the new sheet.
- No PHP, routing, controller or database change. `/connect` is unchanged and
  still serves the page the tray borrows its body from.
