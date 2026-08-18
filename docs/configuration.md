# Configuration reference — seeding a new install from the environment

**You almost certainly don't need this page.** Marquee is configured on its
**Settings** screen: open it from the **⋯** menu in the header, change what you
want, and it takes effect on the next page you load. Nothing there needs the
container restarted,
and nothing there is in your compose file.

This page is for one narrower job: **pre-configuring an install that does not
exist yet**, from its compose file, so that it comes up already set the way you
want it. Deploying a fleet from a template is the case it exists for.

---

## When seeding applies

Marquee keeps its configuration in a settings store at
`/config/data/settings.json`.

**On the first start that finds no store, and only then**, Marquee copies the
environment into it. From that moment the store is the only source: the variables
below are never read again, and Marquee tells you so rather than letting you
wonder.

```
first start, no store     → environment is imported, store is written
every start after that    → store is read, environment is ignored
```

Two consequences worth being clear about:

- **Editing one of these variables on a running install does nothing.** Not
  "takes a restart" — nothing. Change the setting on the Settings screen
  instead.
- **An install that upgraded into the settings store kept every value its
  compose file had set.** The move was invisible; nothing needs re-entering.

Seeding happens once rather than on every boot on purpose. Re-seeding until you
first saved something would keep compose working for longer, but an install that
never opened the Settings screen would never be told to tidy its compose file up,
and then every variable would go obsolete at once the first time anything was
saved. One import and one notice is the whole point.

## What happens to a variable afterwards

Nothing breaks. A superseded variable is **ignored, not an error** — an install
with a full compose file from an older version works exactly as it should.

The Settings screen lists the variables it no longer reads, so you can delete
them. It distinguishes two kinds, because the remedy differs:

| Kind | Variables | What to do |
| --- | --- | --- |
| **Relocated** | any variable in the table below | Managed on the Settings screen now. Delete the line. |
| **Retired** | `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, `AUTH_BYPASS` | The capability is gone. `PLEX_TOKEN` was replaced by signing in to Plex; the `AUTH_*` three by that sign-in becoming the login. Delete them. |

Presence is what counts, never the value: `AUTO_IMPORT_ENABLED: "false"` is
exactly as superseded as `"true"`, and deleting the line is the remedy for both.
An empty variable counts as absent and is not reported.

**Recreating the container is what clears a notice** — `docker compose restart`
is not enough, because the environment is only read when the container starts.

```bash
docker compose up -d
```

## Starting over

Deleting `/config/data/settings.json` returns the install to a clean slate. The
next start finds no store, seeds from the environment again, and every variable
below is back in play.

Your posters, your Plex connection, and your database are untouched — they live
in their own files.

---

## The variables the store is seeded from

Every one of these is a field on the Settings screen. The default applies when
the variable is absent, empty, or unset at seeding time.

### Presentation

| Variable | Description | Default |
| --- | --- | --- |
| `SITE_TITLE` | Site name in the header and browser tab. Does not rename the installed app, which is always "Marquee". Sixty characters or fewer. | `Marquee` |
| `IMAGES_PER_PAGE` | Posters per gallery page. The screen offers 1–200. | `24` |
| `DEFAULT_SORT` | Install default gallery sort. One of `alphabetical`, `alphabetical_desc`, `date_added`, `date_added_asc`. Users can still switch field and direction in the gallery. | `alphabetical` |
| `IGNORE_ARTICLES_IN_SORT` | Ignore a leading "a", "an", or "the" when sorting | `true` |
| `MAX_FILE_SIZE` | Maximum upload size, **in bytes**. The Settings screen asks for this in megabytes; the store keeps bytes either way. | `5242880` (5 MB) |

### Plex

| Variable | Description | Default |
| --- | --- | --- |
| `PLEX_CONNECT_TIMEOUT` | Plex connect timeout, in seconds | `10` |
| `PLEX_REQUEST_TIMEOUT` | Plex request timeout, in seconds | `60` |
| `PLEX_REMOVE_OVERLAY_LABEL` | Remove Kometa's `Overlay` label when sending a poster, so Kometa redraws it | `false` |

`PLEX_SERVER_URL` is **not** in this table. It is not a setting and is not
seeded — see [the README](../README.md#connecting-to-plex).

### Auto-import

| Variable | Description | Default |
| --- | --- | --- |
| `AUTO_IMPORT_ENABLED` | Enable the scheduled background import | `false` |
| `AUTO_IMPORT_SCHEDULE` | How often to run: `1h`, `3h`, `6h`, `12h`, `24h` | `24h` |
| `AUTO_IMPORT_MOVIES` | Include Movies | `false` |
| `AUTO_IMPORT_SHOWS` | Include TV Shows | `false` |
| `AUTO_IMPORT_SEASONS` | Include TV Seasons | `false` |
| `AUTO_IMPORT_COLLECTIONS` | Include Collections | `false` |

With auto-import on and none of the four types enabled, a scheduled run imports
nothing.

### Session

| Variable | Description | Default |
| --- | --- | --- |
| `SESSION_DURATION` | How long a session may go **unused** before it ends, in seconds. The window is renewed every time you use Marquee, so this is idle time, not total time. The Settings screen asks in whole days. | `2592000` (30 days) |

### Updates

| Variable | Description | Default |
| --- | --- | --- |
| `UPDATE_CHECK_ENABLED` | Check GitHub for a newer release and note it in the footer | `false` |

### Libraries

| Variable | Description | Default |
| --- | --- | --- |
| `EXCLUDED_LIBRARIES` | Plex libraries to hide from Marquee entirely, comma-separated. Matched on the **library name** as it appears in Plex (case-insensitive), never the section id. | _(none)_ |

An excluded library is invisible to the whole of Marquee: it is not offered for
import, nothing is imported from it, and posters already imported from it turn up
as orphans.

**Seeding is the only place a library exclusion is typed.** On the Settings
screen they are tick boxes over the libraries your server actually reports, so a
mistyped name can't quietly exclude nothing. That is the better way to set them
whenever the install already exists.

---

## Variables that are not settings

A few variables stay on the environment permanently. They are not seeded, are
never reported as superseded, and are not in the table above.

The ones an ordinary install may need — `PUID`, `PGID`, `TZ`, `PLEX_SERVER_URL`,
and `SESSION_DIR` — are documented in
[the README](../README.md#configuration).

The rest exist for the development loop, or have to work when the settings store
is what broke. They are described in
[docs/development-workflow.md](development-workflow.md#settings-that-stay-in-the-environment),
where the toolchain they serve is described. They are kept out of the
user-facing documentation on purpose: the `/config` layout is presented as fixed,
and offering its subpaths as knobs would make "back up `/config`" conditional.

## A worked example

A fresh install brought up already configured — 48 posters a page, newest first,
auto-import every 6 hours for Movies and TV Shows, two libraries hidden:

```yaml
services:
  marquee:
    image: bozodev/marquee:latest
    container_name: marquee
    ports:
      - "1818:80"
    environment:
      PUID: "1000"
      PGID: "1000"
      TZ: "America/New_York"

      PLEX_SERVER_URL: "http://192.168.1.10:32400"

      # Seeded on the first start only. Delete these once it is running.
      IMAGES_PER_PAGE: "48"
      DEFAULT_SORT: "date_added"
      AUTO_IMPORT_ENABLED: "true"
      AUTO_IMPORT_SCHEDULE: "6h"
      AUTO_IMPORT_MOVIES: "true"
      AUTO_IMPORT_SHOWS: "true"
      EXCLUDED_LIBRARIES: "4K Movies,Kids"
    volumes:
      - ./marquee/config:/config
    restart: unless-stopped
```

Sign in to Plex, confirm it came up the way you wanted, then delete everything
below the `PLEX_SERVER_URL` line and run `docker compose up -d`. The Settings
screen will have been listing those variables as relocated in the meantime.
