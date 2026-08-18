## Why

Changing how Marquee behaves still means editing a compose file and recreating
the container — for a site title, a page size, or a sort order. Phase 1 moved the
source of truth into the application; nothing yet lets a user reach it, so the
settings store is authoritative and unreachable at the same time.

The sharpest cost is library exclusions. They are typed as a comma-separated list
of library names matched case-insensitively, so a misspelling excludes nothing
and says nothing. The user sees a library they meant to hide, has no way to tell
whether the name or the mechanism is wrong, and edits a file to try again.

## What Changes

- A new authenticated `/settings` screen, behind the Plex connection gate like
  the rest of the application, and a new **Settings** entry in the secondary
  navigation — the desktop header and the narrow-screen actions tray alike.
- Presentation settings become editable: site title, posters per page, default
  sort, ignore leading articles when sorting, maximum upload size.
- Plex behavior becomes editable: connect timeout, request timeout, remove
  Kometa's `Overlay` label on send.
- Session duration and the update check become editable.
- **Library exclusions become checkboxes over the libraries the connected server
  actually reports**, rather than free text. A library that cannot be misspelled
  cannot be silently not-excluded. Exclusions stay app-wide, as they are today.
  A stored exclusion naming a library the server does not currently report is
  kept rather than dropped, so an unreachable or renamed library does not quietly
  un-exclude itself.
- The screen lists any **relocated** environment variables still set in the
  user's compose file, with the instruction to delete them. Retired variables
  (`PLEX_TOKEN`, `AUTH_*`) keep their existing home on the connection screen,
  because "this no longer exists" and "this is managed here" are different
  remedies.
- Saved values take effect on the next request. No restart, no container
  recreation.
- Auto-import is deliberately absent. Its controls do nothing until phase 3
  inverts the cron, and a toggle that lies is worse than a toggle that is late.

No setting changes its meaning, its default, or its floor. This change gives
existing settings a surface.

This is phase 2 of `openspec/settings-in-app-plan.md`. It ships on `dev` with no
version bump; `main` is untouched until phase 4.

## Capabilities

### New Capabilities

None. `settings` already exists and already covers how configuration is stored
and resolved; this adds the screen that writes to it.

### Modified Capabilities

- `settings`: gains the settings screen — what it exposes, how a submission is
  validated against the same floors bootstrap applies, how exclusions are chosen
  from the server's own libraries, and where relocated variables are reported.
- `application-shell`: the enumerated secondary-navigation set — in the desktop
  page header and in the narrow-screen actions tray — gains Settings.
- `poster-library`: the same enumeration appears in the gallery's
  "kept out of the toolbar" requirement and gains Settings with it.
- `plex-import`: excluded libraries are still hidden from every screen, import,
  and scheduled run, with one carve-out — the screen that manages exclusions must
  see them, or an exclusion could never be undone. The import screen's
  "libraries are hidden" message points at Settings rather than at a variable.
- `auto-import`, `orphan-detection`: exclusion behavior is unchanged, but both
  specs describe exclusions as `EXCLUDED_LIBRARIES`, a variable a user no longer
  sets. Reworded to name the setting.
- `visual-design`: the application's first real form. Its controls — text,
  number, select, checkbox — draw from the existing token contract and share one
  focus treatment, rather than each being styled where it first appeared.

## Impact

- **New**: `SettingsController`, a settings-form service that validates and
  writes, `templates/settings.html.twig`, a `settings` icon glyph, form styles in
  `public/assets/app.css`.
- **Modified**: `Routes.php` (`GET`/`POST /settings`), `_nav_macros.html.twig`,
  `_icons.html.twig`, `SettingsStore` (a multi-key write), `PlexClient` (a way to
  list libraries without applying exclusions), `HttpPlexClient`.
- **Docs**: `README.md` gains a Settings section and points the "still in your
  compose file" note at the new screen. The configuration table keeps its current
  shape — phase 4 owns rewriting it.
- **Unaffected**: every default, floor, and fallback; the store's format; how
  configuration resolves at bootstrap; auto-import.
