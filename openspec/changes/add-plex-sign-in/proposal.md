## Why

Getting Marquee talking to Plex currently means hunting down an `X-Plex-Token`
through a browser dev-tools walkthrough and pasting it into a compose file — the
first thing a new user has to do, and the thing most likely to stop them. That
token then lives in `docker-compose.yml`, a file people commit to git and paste
into support threads.

Signing in to Plex from inside the app removes the walkthrough, removes the
credential from the compose file, and lets a user change Plex accounts without
editing YAML and restarting a container.

## What Changes

- **Sign in to Plex from the app.** A connection panel on the Import page runs
  Plex's PIN flow: the user opens Plex's own sign-in page in a popup, approves,
  and Marquee stores the resulting token under `/config`.
- **`PLEX_TOKEN` keeps working and keeps precedence.** Nothing breaks, nothing
  needs migrating, and no one is asked to change anything. The variable stays a
  supported option for automated and GitOps deployments — it is not deprecated.
  This is not a breaking change.
- **The connection is visible.** The panel names the connected server and states
  which of the two sources is in use, so a user who has signed in while
  `PLEX_TOKEN` is still set can see that the variable is winning.
- **Plex status app-wide.** The footer and mobile actions tray report the
  connected server and mode on every page, so the connection is legible from the
  gallery where Send and Fetch happen — not only on the Import page.
- **Plex error messages stop naming the wrong fix.** Today every Plex failure
  advises checking `PLEX_TOKEN`, which is useless advice for a signed-in user.
  Errors become aware of which source is in use and offer the matching remedy.
- **The poster wall stops borrowing the Plex token as a signing key.** It gets
  its own generated secret, so signing in again no longer rotates it.

Not included, deliberately: no server discovery or server picker
(`PLEX_SERVER_URL` stays a manual setting), no automatic migration of an
existing `PLEX_TOKEN` onto disk, and no change to Marquee's own username and
password login.

## Capabilities

### New Capabilities

None. This extends where existing configuration comes from and how it is
presented; it does not introduce a new area of behavior.

### Modified Capabilities

- `application-shell`: Configuration may now come from a persisted store as well
  as the environment, with the environment taking precedence. The
  recreatable-state invariant gains a carve-out for connection credentials.
  Adds the Plex sign-in flow, the connection panel, and the app-wide connection
  status readout.

## Impact

**Code**

- `src/Config/PlexConfig.php` — resolves the token from environment or store
- `src/Plex/` — new sign-in service, PIN client, token store, server identity
- `src/Plex/PlexException.php` — typed reasons; remedy copy moves to presentation
- `src/Controller/` — new connection controller; `GalleryController` status data
- `src/Routes.php`, `src/bootstrap.php` — new routes; `StreamToken` secret
- `templates/plex.html.twig` — connection panel replaces the "not configured"
  notice; `templates/layout.html.twig` — footer/tray status
- `public/assets/app.js` — popup + poll, status rendering

**Persistence**

- New token file under `/config`, mode `0600`. Kept out of `marquee.sqlite` so
  the database stays a pure, deletable cache.
- Connected server name cached in SQLite (recreatable from Plex).

**Docs**

- New `docs/plex-connection.md` comparing the two connection modes
- `README.md` — compose example and environment table

**External**

- New outbound dependency on `plex.tv` for the sign-in flow only. Deployments
  that set `PLEX_TOKEN` never contact it.

**Not affected**

- Authentication, import, export, poster editing, orphan detection, and the
  scheduled auto-import all keep their current behavior.
