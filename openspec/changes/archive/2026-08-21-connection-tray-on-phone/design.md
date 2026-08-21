## Context

Four secondary destinations already open as trays over the gallery on a phone.
The machinery is settled and worth reusing verbatim:

- **The bridge.** `TRAY_LINKS` in `gallery.js` maps a `data-*` attribute on an
  anchor to a `gallery:*` event. A document-level click handler intercepts the
  anchor only when `isTouch()` and the page carries `[data-gallery]`; otherwise
  the anchor navigates. So the fallback is the anchor's own `href`, and it costs
  nothing to arrange.
- **The loader.** `_loadTray(url, ref)` fetches a page, and `_injectTray` takes
  its `main.container`, strips the `.search__clear` back link and the `h1`
  (the tray has its own title and its own dismissal), drops the HTML into the
  tray body, and re-runs `Alpine.initTree` over it so the fragment's own
  components bind.
- **The sheet.** `.sheet` + `.sheet__panel--tall`, `role="dialog"`,
  `aria-modal="true"`, `tabindex="-1"`, the grip, a backdrop click and
  `@keydown.escape.window` — plus `data-nested-scope` on the body so the
  gallery's document-level delegation leaves the fragment alone.

The connection status is the one navigation entry still outside all of this. It
is drawn once, by `connection_status()` in `_nav_macros.html.twig`, and rendered
in two placements: the desktop header bar and the phone actions tray.

Nothing on the PHP side needs to move. `/connect` already renders the whole
screen inside `main.container`; `PlexConnectionController::show()` refreshes the
connection from Plex and hands the template `connection`, `flash`, `back_url`
and `superseded`.

## Goals / Non-Goals

**Goals:**

- The connection status opens the connection screen as a tray on a narrow touch
  screen, on the gallery, with the same presentation and dismissal as the other
  four.
- Every other width, device and page keeps navigating to `/connect`, unchanged.
- Reuse `_loadTray` / `_injectTray` and the `TRAY_LINKS` bridge without
  generalising or refactoring them.

**Non-Goals:**

- No change to `/connect`, its controller, its routes, or the connection store.
- No fragment/partial rendering endpoint. The tray borrows the page, as the other
  three do.
- No change to the sign-in flow, the popup, or the polling component.
- No confirmation step in front of Disconnect. It has none on the page today, and
  adding one here would make the tray and the page disagree.
- No change to where the status is drawn or how it looks. It stays a dot and a
  name, and stays out of the icon-and-label shape the nav actions use.

## Decisions

### The trigger is a data attribute on the status anchor, not a new nav entry

`connection_status()` emits `data-connect` on the `<a class="conn-status">`
branch only — never on the `current == 'connect'` span, which has no href and is
not a link. `TRAY_LINKS` gains `'data-connect': 'gallery:connect'` and the
click-handler's selector gains `a[data-connect]`.

The attribute lands in both placements, because the macro is one source of truth
for both. That is correct and already how `data-settings` behaves: the desktop
placement is unaffected because the handler gates on `isTouch()`.

*Alternative rejected:* adding a `connect` entry to `item()` and the link groups.
That is the presentation the spec explicitly removed — the connection is a
status, not a place beside Import and Orphans — and it would put a glyph on it.

### The tray is fetched on every open, like Settings and unlike Import

No `connectLoaded` flag. `show()` calls `PlexConnectionStatus::refresh()`, which
asks the Plex server its name, and the connection itself can be gone since the
gallery behind was rendered. A cached body would pin both to whatever they were
the first time — the same trap `SettingsTrayTest` exists to prevent someone
walking into by analogy with the import tray.

The refresh is a live round trip to Plex, so the tray shows the shared
`Loading…` line while it is in flight, as the other three do.

### Disconnect submits normally; the tray does not intercept it

The disconnect form carries no `js-mutate` class, so the gallery's delegated
submit handler already ignores it, and `data-nested-scope` on the tray body keeps
the sheet delegation off it too. The browser follows the POST to its 302 and
lands on `/connect` with the flash.

This is the intended behaviour, not an accepted limitation. Disconnecting makes
the page behind the tray unusable — the connection gate is what sends a
disconnected user to `/connect` — so a tray that closed back onto a dead gallery
would strand the user; the next thing they touched would bounce them anyway,
without the confirmation.

*Alternative rejected:* intercepting the submit and calling
`window.location.href = '/connect'`. Same destination, an extra layer, and it
would drop the flash if the fetch and the navigation raced.

### `closeConnect()` clears the body

Symmetric with `closeSettings()`. The fetched body is stale the moment it is
dismissed, and leaving it in the DOM means a reopen flashes the previous
connection state before the new fetch lands.

### The sign-in state is handled by doing nothing special

On the gallery the user is connected — the gate guarantees it. If the connection
dropped in between, the fetched fragment is the disconnected screen with its
`plexConnection()` component, which `_injectTray`'s `Alpine.initTree` binds like
any other fragment. Completing a sign-in from there sets
`window.location.href = '/'`, which reloads the gallery the tray was over. That
is the right outcome and needs no code.

### Focus and dismissal come from the existing contract

The panel declares `role="dialog"` + `tabindex="-1"`, which is the only thing
that makes an overlay managed by the focus manager in `gallery.js`. The actions
tray's own `@click` handler already calls `$refs.menuTrigger.focus()` before
hiding, so the origin chain is not rooted at `<body>` — no new handler is needed
for the third menu case.

## Risks / Trade-offs

- **The status is drawn in two placements from one macro, so `data-connect`
  reaches the desktop header too.** → The bridge gates on `isTouch()` and on
  `[data-gallery]`, exactly as it does for `data-settings`. Nothing on desktop
  changes; the tripwire test pins that the attribute is emitted on the anchor
  branch only.
- **A `connectLoaded` flag would be added later "for consistency" with the
  import tray, pinning a stale server name and a stale connection state.** →
  The design note goes in the code beside `openConnect`, and the new tripwire
  test asserts no such flag exists, the way `SettingsTrayTest` does.
- **The superseded-variable notices travel into the tray.** → Accepted, and
  correct: they are the screen's explanation of why a `PLEX_TOKEN` in the compose
  file is being ignored, and the tall sheet scrolls. Same as the settings tray,
  which carries the relocated-variable notice.
- **`refresh()` stalls when the Plex server is down, so the tray can sit on
  "Loading…" for the HTTP timeout.** → Pre-existing behaviour of `/connect`
  itself; the tray inherits it and is dismissable throughout. Nothing about the
  status indicator changes, and it still contacts nothing.
- **None of this is verifiable without a browser, and the repo has no JS test
  runner.** → The tests are shape tripwires, as with the other three trays; the
  behaviour is validated by hand on a phone against the `:dev` image before
  archiving.
