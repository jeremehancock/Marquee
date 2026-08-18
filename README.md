# Marquee

> ⚠️ **Early Alpha — not ready for general use.** Marquee is under active
> development. Things may change, break, or behave unexpectedly without notice.
> Don't point it at a Plex library you aren't willing to experiment on, and keep
> your own backups. Testing and feedback are very welcome — but treat it as
> experimental for now.

Marquee is a self-hosted web app for managing your Plex media posters — for
Movies, TV Shows, TV Seasons, and Collections. Import every poster from Plex,
then refine each one in place: upload your own art, paste an image URL, or pick a
replacement from an online poster search powered by
[posteria.app](https://posteria.app). When you update a poster, Marquee sends
it back to Plex and locks it so Plex keeps your choice.

Marquee is a ground-up rewrite of [Posteria](https://github.com/jeremehancock/Posteria):
same idea, cleaner code, built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec).

## Features

- **Nothing to configure before you start** — the compose file carries only
  `PUID`, `PGID`, and `TZ`. On first run Marquee prints a claim code; enter it
  with your Plex server address and the install is yours, so a Marquee reachable
  from the internet cannot be taken over by whoever opens it first.
- **Connect to Plex from the app** — sign in to Plex in a popup; no token goes
  in your compose file, and there is none to go hunting for. Only the account
  that owns your server is accepted.
- **Import from Plex** — pull the current poster for every Movie, TV Show, TV
  Season, and Collection. A step-by-step picker asks what you want first, then
  shows only the libraries that can provide it.
- **Edit posters in place** — for any poster:
  - **Change poster** by uploading a file, pasting an image URL, choosing from
    **Plex Posters** — the posters your own Plex server already holds for the
    item — or from **Find Posters**, an online poster search served by
    [posteria.app](https://posteria.app) and grouped by the service each result
    came from.
  - **Send to Plex** — re-apply the poster Marquee has stored, and lock it.
  - **Fetch from Plex** — pull the item's current poster from Plex.
  - **Download**, **Copy URL**, view **Full screen**, or **Delete**.
- **Plex is the source of truth** — changing a poster uploads it to Plex and
  locks the artwork so Plex won't overwrite it.
- **Efficient imports** — Marquee skips re-downloading posters that haven't
  changed in Plex (with an option to force a full refresh), reducing load on your
  Plex server.
- **Auto-import** — optionally re-import on a schedule (1h / 3h / 6h / 12h / 24h),
  turned on and off in the app. Changing it needs no restart, and an install that
  was switched off over its scheduled time imports when it comes back.
- **Settings in the app** — change how Marquee behaves from a screen rather than
  a compose file: site title, page size, sort, upload limit, Plex timeouts, how
  long you stay signed in, auto-import, and library exclusions. Saved settings
  apply on the next page you load, with no restart.
- **Library exclusions** — hide chosen Plex libraries from Marquee entirely, in
  the UI and in every import. Tick the ones to hide from the list your server
  reports, so a mistyped name can't quietly exclude nothing.
- **Orphan detection** — find and remove posters whose media no longer exists in
  Plex, or whose library is now excluded.
- **Browse by type or all at once** — switch between Movies, TV Shows, TV
  Seasons, and Collections, or use the **All** view (the default) to see your
  whole library in one grid, each poster tagged with its type.
- **Sort your way** — order the gallery by title or by when each item was added
  to Plex, either way round: tap a sort button again to reverse it (A–Z becomes
  Z–A, newest first becomes oldest first). Each keeps the direction you left it
  in. Set the install default under **Settings**.
- **Poster Wall** — a full-screen, slideshow-style view of your library that
  turns into a live "now playing" board when someone is watching: it shows the
  poster of what each person is streaming, with a *Currently Streaming* banner
  and the media details and Plex user, cycling when more than one stream is
  active (Live TV gets a placeholder; music is ignored). The wall needs no
  sign-in, so you can point a spare monitor or TV straight at `/wall`, or embed
  it in a dashboard — the banner text stays readable down to a small tile.
- **Fast, modern UI** — search as you type, background updates without full page
  reloads, and a mobile experience built around trays: an actions menu and a
  full-size poster action sheet keep the phone view focused on your posters,
  with search and sort pinned to the top as you scroll. On a phone the header,
  the toolbar, and the tab bar are translucent, so your posters stay visible
  passing behind them; dialogs, trays, buttons, and poster cards all animate
  rather than snap. If your system asks for reduced motion, Marquee drops the
  movement and keeps the progress indicators.
- **Installable PWA** — add it to your phone or desktop home screen.

## Quick start (Docker Compose)

Create a `docker-compose.yml`:

```yaml
services:
  marquee:
    image: bozodev/marquee:latest
    container_name: marquee
    ports:
      - "1818:80"                     # http://<host>:1818
    environment:
      PUID: "1000"                    # match your host user (id -u)
      PGID: "1000"                    # match your host group (id -g)
      TZ: "Etc/UTC"
    volumes:
      - ./marquee/config:/config
    restart: unless-stopped
```

That is the whole file. There is no Plex address to fill in, no token to hunt
for, and no credentials to invent — you set all of it up in the browser, and
change it there afterwards.

Then start it:

```bash
docker compose up -d
```

**Get your claim code.** The first time Marquee starts it prints one, and stores
it on your volume:

```bash
docker compose logs marquee | grep "Claim code"
# or
cat ./marquee/config/data/claim-code.txt
```

Open `http://<host>:1818` and enter that code along with your Plex server
address. Then sign in to Plex — that one step is both your login and the
connection to your server. Finally go to **Import from Plex** to pull in your
posters.

> **Why a code?** Marquee only lets in the Plex account that owns the server you
> point it at — but on a brand-new install, whoever opens it first would get to
> name that server. The code is how Marquee knows you are the person who set the
> container up: reading it takes access to the machine Marquee runs on. It works
> once, and is deleted the moment the install is claimed.

The `/config` volume holds everything Marquee needs to persist:

- `/config/posters` — the poster images, grouped by category
- `/config/data` — your settings, your Plex connection, the SQLite database, and
  logs
- `/config/sessions` — your signed-in sessions, so updating Marquee doesn't sign
  you out

Back this directory up if you want to keep your poster selections.

## Configuration

**Marquee is configured in the browser.** Open **Settings** in the header; every
change takes effect on the next page you load, with no restart and no editing of
files. See [Settings](#settings) below for what it covers.

Nothing in the table below is required. It exists for two reasons: the first
three are container settings that must be set before PHP starts, and the rest
are a way to seed a *brand-new* install from a compose file — useful if you
deploy from one, and unnecessary otherwise.

> **These variables set up a new install, once.** Marquee keeps its own
> configuration on the `/config` volume. The first time it starts, it copies
> whatever your compose file says into that store — so upgrading changes nothing
> about how your install behaves — and from then on the store is what it reads.
>
> An install seeded with `PLEX_SERVER_URL` is treated as already claimed and
> never asks for a claim code, which is what makes upgrading from an older
> version seamless.
>
> Editing a variable afterwards has no effect, and Marquee will tell you so: the
> Settings screen lists any variable still in your compose file that it no longer
> reads, so you can delete it. `PUID`, `PGID`, `TZ`, and the `/config` paths are
> unaffected — those are container settings and are always read from the
> environment.
>
> Deleting `/config/data/settings.json` returns the install to a clean slate and
> lets the next start read your compose file again.

| Variable | Description | Default |
| --- | --- | --- |
| `PUID` / `PGID` | User / group id that owns the `/config` volume | `911` |
| `TZ` | Timezone (e.g. `America/New_York`) | `Etc/UTC` |
| `SITE_TITLE` | Site name shown in the header and browser tab. Does not rename the installed app, which is always "Marquee". | `Marquee` |
| `SESSION_DURATION` | How long a session may go unused before it ends, in seconds. The window is renewed every time you use Marquee, so this is idle time, not total time. | `2592000` (30 days) |
| `SESSION_DIR` | Where sessions are stored. The default is on the `/config` volume, so staying signed in survives updating the container. Point it at `/tmp` if your `/config` is a network share whose file locking misbehaves — sessions then last only until the container is recreated. | `/config/sessions` |
| `PLEX_SERVER_URL` | Plex Media Server URL, e.g. `http://10.0.0.5:32400` | _(unset)_ |
| `PLEX_REMOVE_OVERLAY_LABEL` | Remove Kometa's `Overlay` label when sending a poster | `false` |
| `PLEX_CONNECT_TIMEOUT` | Plex connect timeout, in seconds | `10` |
| `PLEX_REQUEST_TIMEOUT` | Plex request timeout, in seconds | `60` |
| `AUTO_IMPORT_ENABLED` | Enable the scheduled background import | `false` |
| `AUTO_IMPORT_SCHEDULE` | How often to auto-import: `1h`, `3h`, `6h`, `12h`, `24h` | `24h` |
| `AUTO_IMPORT_MOVIES` | Include Movies in the auto-import | `false` |
| `AUTO_IMPORT_SHOWS` | Include TV Shows in the auto-import | `false` |
| `AUTO_IMPORT_SEASONS` | Include TV Seasons in the auto-import | `false` |
| `AUTO_IMPORT_COLLECTIONS` | Include Collections in the auto-import | `false` |
| `EXCLUDED_LIBRARIES` | Plex libraries to hide from Marquee entirely, comma-separated. Match is on the **library name** as it appears in Plex (case-insensitive), never the section id. Excluded libraries are not listed on the Import from Plex screen and are skipped by every import, manual or scheduled. See the [FAQ](#faq) for what happens to posters already imported from one. | _(none)_ |
| `IMAGES_PER_PAGE` | Posters shown per gallery page | `24` |
| `MAX_FILE_SIZE` | Maximum upload size, in bytes | `5242880` |
| `IGNORE_ARTICLES_IN_SORT` | Ignore leading "a/an/the" when sorting | `true` |
| `DEFAULT_SORT` | Preferred gallery sort: `alphabetical` (A–Z) or `date_added` (newest first). Users can switch field and direction in the gallery. | `alphabetical` |
| `UPDATE_CHECK_ENABLED` | Check GitHub for a newer release | `false` |
| `UPDATE_REPO` | Repository to check for releases (`owner/repo`) | `jeremehancock/Marquee` |

### Settings

**Settings** in the header is where configuration lives once an install is
running. Nothing here needs the container restarted or recreated.

| Group | What you can change |
| --- | --- |
| Presentation | Site title, posters per page, default sort, whether leading articles are ignored when sorting, maximum upload size |
| Plex | Server address, connect and request timeouts, whether Kometa's `Overlay` label is removed when a poster is sent |
| Auto-import | Whether to import on a schedule, how often, and which types |
| Session | How long you stay signed in |
| Updates | Whether to check GitHub for a newer release |
| Libraries | Which Plex libraries to exclude |

Everything takes effect on the next page you load, with one exception that the
screen states: **auto-import applies from the next scheduled run.** Marquee
checks once an hour whether a run is due, so a change you make is picked up by
that check rather than immediately.

**Excluded libraries are tick boxes over the libraries your server actually
reports**, not a list of names to type. An excluded library is invisible to the
whole of Marquee: it is not offered for import, nothing is imported from it, and
posters already imported from it turn up as orphans.

If a library you have excluded is no longer on your server — renamed, removed, or
the server is not answering — Marquee keeps the exclusion and shows it separately
rather than quietly dropping it. Clearing it is a tick box of its own.


### Signing in

**You open Marquee by signing in to Plex.** There is no Marquee username or
password to invent, and no way to turn the login off. Only the Plex account that
owns your configured server is accepted; every other account is refused and
nothing is stored.

Plex is consulted when you sign in and never again. After that Marquee trusts
its own session, so a plex.tv outage cannot lock you out of an install you are
already signed in to — everything you do afterwards talks to your own Plex
server directly.

The header carries a **connection status** — the name of your Plex server with a
green dot, or "Not connected" with an amber one. It is also the way to the Plex
Connection screen, where disconnecting lives.

**Two ways out, and they are not the same:**

| | What it does | Scheduled imports |
| --- | --- | --- |
| **Log out** | Ends this browser's session. Plex stays connected. | keep running |
| **Disconnect** | Forgets the Plex connection. Marquee stops working until someone signs in again. | **stop** |

Logging out does not revoke Marquee's access to Plex. To do that, disconnect
here *and* remove Marquee from **Authorized Devices** in your Plex account
settings.

> **Upgrading from 2.0.x?** `AUTH_USERNAME`, `AUTH_PASSWORD`, and `AUTH_BYPASS`
> are no longer used. Your Plex connection and your scheduled imports carry over
> untouched — only the way you sign in changes. If you were running with
> `AUTH_BYPASS: "true"`, Marquee will now ask you to sign in; the Poster Wall is
> unaffected and still runs unattended. Delete the three variables from your
> compose file and run `docker compose up -d`. The Plex Connection screen says
> all of this while any of them are still set.

There are no user accounts or roles inside Marquee, by design: it is a
single-user tool for managing your own posters.

### Connecting to Plex

Marquee does nothing without Plex, so connecting is the first thing it asks for.
Until you have, every page redirects to the **Plex Connection** screen.

1. Open Marquee and claim it: enter the claim code from your log or from
   `data/claim-code.txt`, along with your Plex server's address. Marquee checks
   that something is answering there before it accepts.
2. You land on **Plex Connection**, which is also the sign-in screen.
3. Choose **Sign in with Plex**. A Plex popup opens; sign in and approve
   Marquee there.
4. The popup closes and Marquee takes you to your gallery. The connection
   status in the header names your server from then on; select it any time to
   see the connection or to disconnect.

The address you gave is where ownership is checked, so a wrong one stops the
sign-in rather than the account. You can change it later under **Settings**.

**Sign in with the Plex account that owns the server.** Marquee refuses any
other account, including one your server is shared with.

That is stricter than Plex itself would be, on purpose. Plex would stop a shared
account changing your artwork — but deleting a poster in Marquee is a local
action Plex has no say over, and a poster you never sent to Plex has no copy to
restore. Rather than rely on Plex to limit an account it only partly limits,
Marquee does not accept one.

A refusal tells you which of the two things to fix. If signing in reports that
Marquee could not reach your Plex server, the address is wrong rather than your
account — check it under **Settings** and that Plex is running.

The token is stored at `/config/data/plex-connection.json`, owner-readable only
(`0600`). It is deliberately kept out of `marquee.sqlite`, so deleting the
database is still a safe reset that costs only cached data. Because it lives in
`/config`, treat backups of that directory as you would the token itself.

Your browser talks to Plex directly and Marquee polls for the result, so nothing
has to route back in — this works behind a reverse proxy with no extra setup.

The server address is a setting, not a credential. Signing in supplies the
credential and never the address; if the address is missing, the connection
screen says so rather than offering a sign-in that cannot help.

To disconnect, or to move Marquee to a different Plex account, use
**Disconnect from Plex** on the same screen.

#### Upgrading from a version that used `PLEX_TOKEN`

**`PLEX_TOKEN` is no longer read.** Marquee is in alpha and this is a breaking
change: one way to connect replaced two.

On first start after upgrading you will be redirected to the Plex Connection
screen, which will tell you the variable is no longer used. Sign in there, then
delete `PLEX_TOKEN` from your `docker-compose.yml` and run
`docker compose up -d`.

**Recreating the container is what clears the notice** — `docker compose restart`
is not enough, because the environment is only read when the container starts.
Leaving the variable set changes nothing else: it is never used to reach Plex.

Nothing else is affected: your posters, your Plex item mappings, and your
scheduled auto-import all carry on once you have signed in.

## Usage

1. **Import from Plex.** Choose what you want to import (Movies, TV Shows, TV
   Seasons, or Collections), pick the matching libraries, and run the import.
   Marquee pulls the current poster for each item and remembers which Plex item
   it belongs to.
2. **Refine a poster.** Hover a poster (or tap it on mobile) to open its actions.
   Use **Change poster** to upload a file, paste a URL, or search **Find
   Posters**. Whichever you pick, you see it full screen and confirm before
   anything changes; applying it updates the poster locally, uploads it to Plex,
   and locks it.
3. **Keep Plex and Marquee in sync.** Use **Send to Plex** to re-apply Marquee's
   stored poster (for example after a Plex agent refresh), or **Fetch from Plex**
   to pull the item's current Plex art back into Marquee.
4. **Tidy up.** Open **Orphans** to remove posters whose media no longer exists
   in Plex, or **Poster Wall** for a full-screen slideshow.

## Moving from Posteria

There is no migration path, and none is needed. Marquee treats Plex as the source
of truth, so your posters come from Plex rather than from a Posteria export —
nothing has to be carried across.

1. **Make sure Plex has the posters you want to keep.** Whatever art is on your
   items in Plex right now is what Marquee will import. If Posteria holds a
   poster you care about that Plex doesn't, apply it in Plex before you go any
   further.
2. **Retire Posteria.** Once Plex is up to date, stop and remove your Posteria
   container. Keep its data volume around for a while if you'd like a safety net
   — Marquee never reads it.
3. **Set up Marquee.** Follow the [Quick start](#quick-start-docker-compose)
   above.
4. **Import from Plex.** Run an import for each type you want (Movies, TV Shows,
   TV Seasons, Collections). Marquee pulls the current poster for every item and
   maps it back to the Plex item it belongs to.
5. **Optionally turn on auto-import.** Open **Settings**, enable it, pick how
   often it should run, and tick the types you want, so new media picks up
   posters on its own.

From there, use Marquee as described in [Usage](#usage).

## Updating

```bash
docker compose pull
docker compose up -d
```

If `UPDATE_CHECK_ENABLED` is on, Marquee shows a note in the footer when a newer
release is available.

### Image tags

| Tag | Built from | Use for |
| --- | --- | --- |
| `bozodev/marquee:latest` | the `main` branch (always) | production |
| `bozodev/marquee:dev` | the `dev` branch | testing upcoming changes |
| `bozodev/marquee:<version>` | a push to `main` that bumps the `VERSION` file | pinning a specific release |

Images are built and pushed automatically by GitHub Actions once CI passes
(`.github/workflows/docker-publish.yml`). A versioned release is cut simply by
bumping the `VERSION` file and merging to `main`: that publishes both
`:<version>` and `:latest` and creates the matching git tag + GitHub Release. See
[`docs/development-workflow.md`](docs/development-workflow.md#promoting--releasing).

## FAQ

**Does Marquee change my Plex library?**
Yes — when you change a poster (or use **Send to Plex**), Marquee uploads that
image to the item in Plex and locks the artwork so Plex keeps it.

**Is Marquee a backup of my Plex posters?**
No. Marquee treats Plex as the source of truth: importing pulls the current
poster from Plex and will overwrite what Marquee had for that item. Keep your own
backups of anything you can't recreate. (Marquee only re-downloads posters that
actually changed in Plex, so re-imports are cheap.)

**What is orphan detection?**
It finds posters in Marquee that are no longer linked to any media in Plex —
usually because you removed that content from Plex, or because you excluded that
library. Every poster in Marquee came from an import and stays linked to its
Plex item, so no poster is exempt: replacing one with your own image changes the
artwork, not the link. Deleting an orphan removes the stored poster and that
link; it never touches anything in Plex.

**I excluded a library I'd already imported. What happens to its posters?**
They become orphans. An excluded library doesn't exist as far as Marquee is
concerned, so the posters it left behind are no longer linked to anything —
they show up on the **Orphans** screen, where you can review them and delete
the ones you don't want. Marquee never deletes them on its own. If you change
your mind, remove the library from `EXCLUDED_LIBRARIES`, restart, and its
posters go back to being ordinary posters; anything you already deleted comes
back with a fresh import.

**I fixed a wrong match in Plex. Do I need to do anything in Marquee?**
Just run an import. When you correct a match in Plex — the **Fix Match** option —
the item becomes a different movie or show, and the next import brings Marquee's
copy into line: the poster is renamed to the new title, so it sorts and searches
under that title rather than the old one, and the details behind **Find Posters**
are corrected too. This happens even if the artwork itself didn't change, so a
poster you'd already customised and locked is fixed up as well.

**Something's wrong with my posters. Can I start over?**
Usually you don't need to. If a poster is stale or wrong, run the import again
with **Re-download unchanged posters** checked — that pulls fresh art from Plex
for every item you select, overwriting whatever Marquee is holding. If the
problem is posters left behind by media you removed from Plex, or by a library
you excluded, use **Orphans** instead.

If you really do want a clean slate, stop the container, delete `posters/` and
`data/marquee.sqlite*` from your `/config` volume, start it again, and run an
import. Marquee rebuilds both from scratch. Stopping first matters, and so does
the `*` — it catches the database's companion files, which otherwise survive and
leave you half reset.

Know what that costs before you do it: every poster you'd applied to Plex comes
back, because Plex has it and Marquee locked it there. Anything that only ever
existed in Marquee — art you uploaded but never sent — is gone. Your settings and
your Plex connection are untouched; they live in their own files. Auto-import
forgets when it last ran, so it runs once more than it strictly needed to.

**Can I hand this install to someone else, or start its setup over?**
Yes, and it needs access to the machine — that is the point of the claim code.
Stop the container, delete `data/plex-connection.json` from your `/config`
volume, and start it again. Marquee generates a new claim code and asks to be set
up from scratch.

> ⚠️ **If your Marquee is reachable from the internet, take it off the network
> first.** Between deleting that file and claiming it again, the install is
> unclaimed — and whoever reaches it first can claim it. Your Plex server, your
> token, and your media are *not* exposed: the new claimant's Plex token only
> reaches their own account, and yours is gone with the file you deleted. What is
> exposed is the poster library already on disk. `/config/posters` survives
> independently of the claim, and the Poster Wall deliberately needs no sign-in,
> so the next claimant would see your posters.

**Where do "Find Posters" results come from?**
From [posteria.app](https://posteria.app), an online poster search service.

**What's the difference between "Plex Posters" and "Find Posters"?**
Plex Posters shows what your own server already has for that item. Find Posters
searches the internet for something new.

**Why is "Find Posters" split into sections?**
Each section is one of the services the search draws from — TMDB, TVDB and
fanart.tv — with a count of what that service returned. They're not
interchangeable: fanart.tv is where textless artwork tends to be, TMDB carries
the most language variants, and TVDB has a show's own artwork. The sections
are always in the same order, so once you know where your preferred service sits
it stays there. Whichever one you want, results within a section are still
ranked best-first.

A service that returned nothing for an item is left out rather than shown empty.

**I want a poster back that I used to have**
Use **Plex Posters**. Plex keeps every poster ever uploaded to an item and never
removes them, so a poster you applied months ago is still on your server even if
it's long gone from Marquee — and even if no poster search would turn it up
again. The tab lists your uploads first, and marks the one Plex is using now.
Pick one and Marquee stores it, points Plex at it, and locks it — without
uploading a duplicate, since Plex already has the image.

Below your uploads sits **Offered by Plex** — everything else Plex has for that
item. Some of it is already on your server (what its metadata agent downloaded,
a `poster.jpg` beside the media, an image pulled from the video), and some Plex
would fetch on demand. Marquee only uploads when it has to: if Plex already has
the image, it just points Plex at it.

This group overlaps Find Posters but doesn't replace it — and vice versa. Plex
also offers IMDb and Gracenote artwork, which the poster search doesn't carry,
and everything Plex lists is tied to that exact item with no title matching
involved.

**"Find Posters" isn't returning many results for my library**
Find Posters is most accurate when Plex has matched an item to a known title,
because that lets Marquee ask for that exact movie or show. Without a match it
searches by name instead, which is less precise — similarly named titles can
crowd out the one you wanted.

For some things that's simply how it works, and there's nothing to fix:
collections, personal media libraries such as home videos, and anything Plex
hasn't matched yet. If it's one stubborn item, correcting that item's match in
Plex is usually enough.

If results are poor across a whole library, the likely cause is the metadata
agent that library uses. Libraries created on older versions of Plex keep the
agent they were built with — upgrading Plex doesn't change it — and the older
agents don't give Marquee the information it needs to identify a title.
Switching that library to one of Plex's current agents fixes it from then on.

Do that deliberately, though. Plex re-scans the library afterwards, and that
re-scan can change artwork. Posters you've applied through Marquee are locked in
Plex and stay as they are — but a poster Marquee only imported was never locked,
so Plex can replace it, along with matches you'd corrected by hand. Marquee
still has its copy: use **Send to Plex** to put a poster back, and do that
before your next import, because importing pulls whatever artwork Plex has at
that moment into Marquee.

If an import already overwrote Marquee's copy, check **Plex Posters** before
giving up — the previous artwork is often still sitting on your server even
though Marquee no longer has it.

If Find Posters is already working well for you, there's nothing here you need
to do.

**Does it work on mobile?**
Yes. Marquee is responsive and installable as a PWA. On a phone the gallery stays
front and center: the secondary actions live behind the **⋯** Actions menu,
search and sort stay pinned to the top while you scroll, and tapping a poster
opens a full-size action sheet.

## Security considerations

Marquee is opened by signing in to Plex, and only the account that owns your
configured server is accepted. There is nothing to set up and no password to
choose. All communication with your Plex server uses your Plex authentication
token.

- **Use HTTPS** (behind a reverse proxy) if you expose Marquee to the internet.
- **The session cookie is `HttpOnly` and `SameSite=Lax`**, and signing in issues
  a fresh session identifier. It is deliberately *not* marked `Secure`: Marquee
  is normally reached over plain HTTP on a LAN, and a `Secure` cookie is never
  sent over HTTP, so marking it would stop those installs signing in at all.
- **Every action that changes something carries a token** proving the request
  came from a page Marquee rendered. This matters more than it sounds on a
  self-hosted box: `SameSite=Lax` stops another *site* driving your session, but
  "site" ignores the port, so every other container on the same address counts
  as the same site. The token is what stops one of them acting as you.
- **The Poster Wall needs no sign-in, by design** — it is meant for a spare
  monitor nobody logs in to. That means anyone who can reach `/wall` can see your
  Movie and TV Show poster art and what is currently playing. Season and
  Collection art is not served there, and nothing on the wall can change
  anything. If that is more than you want on your network, do not expose `/wall`.
- **Back up your `/config` directory** regularly — an import can rebuild
  everything Plex already has, but not art you uploaded and never sent there.
  Your Plex token is in there too, so treat those backups as you would the
  token itself.

The login cannot be disabled, and only the Plex account that owns your server can
get past it. Anyone who does acts with your stored Plex connection — overwriting
artwork in your library, deleting posters, and disconnecting the install — so
treat a signed-in browser as you would the Plex account itself.

**Rate limiting on an internet-facing install.** Starting a sign-in is the one
route reachable without a session, and it calls plex.tv. New installs ship an
nginx limit for it. **Existing installs will not pick it up**: the base image
copies its site config into `/config/nginx/site-confs/` only on first run, so
yours is already there and untouched by upgrades. To add it, copy the
`limit_req_zone` line and the `location = /plex/connection/sign-in` block from
[`default.conf.sample`](docker/root/defaults/nginx/site-confs/default.conf.sample)
into `/config/nginx/site-confs/default.conf` and restart. If Marquee sits behind
Cloudflare, Traefik, or Nginx Proxy Manager, their rate limiting is better placed
than this one and worth using instead.

If you want to reach Marquee from outside your network, prefer a VPN over
opening a port to the internet. <a href="https://www.tailscale.com/" target="_blank" rel="noopener">Tailscale™</a>
or a similar solution keeps it off the public internet entirely.

## Development

Requires PHP 8.4+ and Composer.

```bash
composer install

composer test          # PHPUnit
composer stan          # PHPStan (level 10, max)
composer cs            # PHP-CS-Fixer (dry-run)
composer cs:fix        # PHP-CS-Fixer (apply)

# Run locally on http://localhost:8080
php -S localhost:8080 -t public public/index.php
```

See [`docs/development-workflow.md`](docs/development-workflow.md) for the
VSCodium + Claude Code + OpenSpec setup and the `dev`/`main` branch flow,
[`docs/docker.md`](docs/docker.md) for how the image sets the PHP version and
how to smoke-test a Dockerfile change locally, and
[`docs/testing.md`](docs/testing.md) for validating the live Plex round-trip
(poster locking and the Kometa label), including the
[`scripts/marquee-plex-test.py`](scripts/marquee-plex-test.py) tester.

### Tech stack

- **PHP 8.4+**, Composer, PSR-4 autoloading, `strict_types` throughout
- **[Slim 4](https://www.slimframework.com/)** (PSR-7 / PSR-15) with **PHP-DI**
- **Twig** server-rendered templates + **Alpine.js** (no build step)
- **Guzzle** for outbound HTTP, **SQLite** (PDO) for metadata, **Monolog** for logs
- **Docker**: LinuxServer Alpine-nginx base with s6-overlay and cron
- Quality gates: **PHPUnit**, **PHPStan** (level 10, max), **PHP-CS-Fixer**, GitHub Actions

### Spec-driven development with OpenSpec

Every capability is specified before it is built. Specs and change proposals live
under `openspec/`.

```bash
openspec list                 # active changes
openspec validate <change>    # validate a change
openspec archive <change>     # fold an implemented change into the specs
```

## License

[MIT](LICENSE)

## Acknowledgements

Marquee is a rewrite of [Posteria](https://github.com/jeremehancock/Posteria).

**Find Posters** is served by [posteria.app](https://posteria.app), an online
poster search service.

## Support Development

Marquee is free and self-hosted, and it stays that way. If it has been useful to
you and you'd like to help keep it maintained, you can support development at
[getmarquee.now/#support](https://getmarquee.now/#support). Feedback and bug
reports are just as welcome.

## AI Disclosure

This project was created with the help of AI.
