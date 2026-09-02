# Marquee

Marquee is a self-hosted web app for managing your Plex media posters — for
Movies, TV Shows, TV Seasons, and Collections. Import every poster from Plex,
then refine each one in place: upload your own art, paste an image URL, or pick a
replacement from an online poster search powered by
[posteria.app](https://posteria.app). When you update a poster, Marquee sends
it back to Plex and locks it so Plex keeps your choice.

Marquee is a ground-up rewrite of [Posteria](https://github.com/jeremehancock/Posteria):
same idea, cleaner code, built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec).

## Features

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
  - **Related posters** — jump to everything sharing the poster's title: a show
    together with all of its seasons, or a film with the rest of its trilogy and
    its collection. Useful for making a set match.
  - **Download**, view **Full screen**, or **Delete**.
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
  the UI and in every import. Select the ones to hide from the list your server
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
  reloads, and a mobile experience built around trays: an actions menu, importing,
  orphans, settings, your Plex connection and a full-size poster action sheet all
  open over the gallery rather than taking you away from it, with search and sort
  pinned to the top as you scroll. Swipe left or right anywhere on the grid to
  move between categories — the next one follows your thumb and you can change
  your mind halfway. On a phone the header,
  the toolbar, and the tab bar are translucent, so your posters stay visible
  passing behind them; dialogs, trays, buttons, and poster cards all animate
  rather than snap. If your system asks for reduced motion, Marquee drops the
  movement and keeps the progress indicators. Every dialog and tray works from
  the keyboard: opening one moves focus into it, Tab stays inside it, and
  closing it puts you back where you were.
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

      # Your Plex server's address — required, and read on every start.
      PLEX_SERVER_URL: "http://192.168.1.10:32400"
      # No token, no username, no password — you sign in to Plex from the app.
    volumes:
      - ./marquee/config:/config
    restart: unless-stopped
```

**That is the whole file.** Everything else — the site title, page size, sort
order, timeouts, auto-import, library exclusions — is a field on the **Settings**
screen once Marquee is running.

Then start it:

```bash
docker compose up -d
```

Open `http://<host>:1818`. Marquee asks you to sign in to Plex — that one step
is both your login and the connection to your server. Then go to **Import from
Plex** to pull in your posters.

The `/config` volume holds everything Marquee needs to persist:

- `/config/posters` — the poster images, grouped by category
- `/config/data` — your settings, your Plex connection, the SQLite database, and
  logs
- `/config/sessions` — your signed-in sessions, so updating Marquee doesn't sign
  you out

Back this directory up if you want to keep your poster selections.

## Configuration

**Marquee is configured in the app, not in a file.** Open **Settings** — behind
the **⋯** menu in the header, or the actions menu on a phone — and change what you
want; it takes effect on the next page you load, with no restart and nothing to
edit on disk. See [Settings](#settings) below for what it covers.

Your compose file keeps only what cannot be a setting: the container's own
options, and your Plex server's address.

| Variable | Description | Default |
| --- | --- | --- |
| `PUID` / `PGID` | User / group id that owns the `/config` volume | `911` |
| `TZ` | Timezone (e.g. `America/New_York`) | `Etc/UTC` |
| `PLEX_SERVER_URL` | Plex Media Server URL, e.g. `http://10.0.0.5:32400`. **Required.** Read on every start rather than seeded once, so changing your Plex address is a compose edit and a restart. See [Connecting to Plex](#connecting-to-plex) for why it is not a setting. | _(unset)_ |
| `SESSION_DIR` | Where sessions are stored. The default is on the `/config` volume, so staying signed in survives updating the container. Point it at `/tmp` if your `/config` is a network share whose file locking misbehaves — sessions then last only until the container is recreated. | `/config/sessions` |

There are no credentials to set. You sign in to Plex.

> **Already have a compose file full of variables?** Nothing is broken. Marquee
> imported them into its own settings store the first time it started, so your
> install behaves exactly as it did — and from then on the store is what it
> reads. Editing one of those variables now has no effect.
>
> The Settings screen lists every variable it no longer reads, so you can delete
> them. Recreating the container is what clears the notice.

**Pre-configuring a brand-new install** from its compose file is still possible —
useful for a template you deploy more than once. See
[docs/configuration.md](docs/configuration.md) for every variable the store is
seeded from, and the rules about when seeding applies.

### Settings

**Settings** is where configuration lives once an install is running. Nothing here
needs the container restarted or recreated. It sits behind the **⋯** menu in the
header, beside Support Development and Log out; on a phone it opens as a tray over
the gallery, and saving closes the tray and reloads the page so your change is
visible straight away.

| Group | What you can change |
| --- | --- |
| Presentation | Site title, posters per page, default sort, whether leading articles are ignored when sorting, maximum upload size (in MB) |
| Plex | Connect and request timeouts, whether Kometa's `Overlay` label is removed when a poster is sent |
| Auto-import | Whether to import on a schedule, how often, and which types |
| Session | How long you stay signed in, in whole days |
| Updates | Whether to check GitHub for a newer release |
| Libraries | Which Plex libraries to exclude |

Everything takes effect on the next page you load, with one exception that the
screen states: **auto-import applies from the next scheduled run.** Marquee
checks once an hour whether a run is due, so a change you make is picked up by
that check rather than immediately.

**Excluded libraries are selected from the libraries your server actually
reports**, not a list of names to type. An excluded library is invisible to the
whole of Marquee: it is not offered for import, nothing is imported from it, and
posters already imported from it turn up as orphans.

If a library you have excluded is no longer on your server — renamed, removed, or
the server is not answering — Marquee keeps the exclusion and shows it separately
rather than quietly dropping it. Clearing it is a selection of its own.

**The Plex server address is not on this screen, and will not be.** It stays in
your compose file because setting it is what proves the server is yours — see
[Connecting to Plex](#connecting-to-plex).

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

1. Set `PLEX_SERVER_URL` to your server's address and start Marquee.
2. Open Marquee. You land on **Plex Connection**, which is also the sign-in
   screen.
3. Choose **Sign in with Plex**. A Plex popup opens; sign in and approve
   Marquee there.
4. The popup closes and Marquee takes you to your gallery. The connection
   status in the header names your server from then on; select it any time to
   see the connection or to disconnect.

If Marquee cannot reach the address you set, the sign-in is refused and the
screen says so, naming `PLEX_SERVER_URL` — ownership is checked against your
server, so a wrong address stops the sign-in rather than the account.

**Sign in with the Plex account that owns the server.** Marquee refuses any
other account, including one your server is shared with.

That is stricter than Plex itself would be, on purpose. Plex would stop a shared
account changing your artwork — but deleting a poster in Marquee is a local
action Plex has no say over, and a poster you never sent to Plex has no copy to
restore. Rather than rely on Plex to limit an account it only partly limits,
Marquee does not accept one.

A refusal tells you which of the two things to fix. If signing in reports that
Marquee could not reach your Plex server, the address is wrong rather than your
account — check `PLEX_SERVER_URL` and that Plex is running.

The token is stored at `/config/data/plex-connection.json`, owner-readable only
(`0600`). It is deliberately kept out of `marquee.sqlite`, so deleting the
database is still a safe reset that costs only cached data. Because it lives in
`/config`, treat backups of that directory as you would the token itself.

Your browser talks to Plex directly and Marquee polls for the result, so nothing
has to route back in — this works behind a reverse proxy with no extra setup.

`PLEX_SERVER_URL` stays an environment variable. The others that do — the
container's options and the `/config` paths — stay because they are not settings
at all; this one is, and it is held back deliberately. Signing in supplies a
credential, never an address; if the address is missing, the connection screen
says so rather than offering a sign-in that cannot help.

It stays in your compose file because it is what decides *who gets in*. Marquee
admits the Plex account that owns the server at that address, so setting the
address is really an assertion about which server is yours — and setting an
environment variable takes access to the host running Marquee. If the address
could be typed into a browser instead, the first stranger to find an
unconfigured install could point it at their own server and become its owner.
Changing your Plex address is therefore a compose edit and a restart, which is
the same access you needed to install Marquee in the first place.

To disconnect, or to move Marquee to a different Plex account, use
**Disconnect from Plex** on the same screen.

#### Upgrading from a version that used `PLEX_TOKEN`

**`PLEX_TOKEN` is no longer read.** Signing in to Plex from the app replaced it:
one way to connect, rather than two.

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
   often it should run, and select the types you want, so new media picks up
   posters on its own.

From there, use Marquee as described in [Usage](#usage).

## Updating

```bash
docker compose pull
docker compose up -d
```

With the update check turned on under **Settings**, Marquee shows a note in the
footer when a newer release is available.

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
your mind, deselect the library under **Settings** — no restart — and its posters
go back to being ordinary posters; anything you already deleted comes back with a
fresh import.

**I fixed a wrong match in Plex. Do I need to do anything in Marquee?**
Just run an import. When you correct a match in Plex — the **Fix Match** option —
the item becomes a different movie or show, and the next import brings Marquee's
copy into line: the recorded title is corrected, so the poster is captioned and
found under the new title rather than the old one, the file is renamed so it sorts
there too, and the details behind **Find Posters** are corrected as well. This happens even if the artwork itself didn't change, so a
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

**Where do "Find Posters" results come from?**
From [posteria.app](https://posteria.app), an online poster search service.

**What's the difference between "Plex Posters" and "Find Posters"?**
Plex Posters shows what your own server already has for that item. Find Posters
searches the internet for something new.

**Why is "Find Posters" split into sections?**
Each section is one of the services the search draws from — TMDB, TVDB,
fanart.tv and TVmaze — with a count of what that service returned. They're not
interchangeable: fanart.tv is where textless artwork tends to be, TMDB carries
the most language variants, TVDB has a show's own artwork, and TVmaze is worth
checking on a season, where it often holds an image none of the others do. The
sections are always in the same order, so once you know where your preferred
service sits it stays there. Whichever one you want, results within a section are
still ranked best-first.

A service that returned nothing for an item is left out rather than shown empty.

**Why does TVmaze never appear on a movie?**
It only covers television. On a movie or a collection its section is simply
absent — that's the expected result, not a failure, so nothing is reported.

**What's the small link in the corner of each result?**
It opens that service's own page for the show, season or film — TMDB, TheTVDB,
fanart.tv or TVmaze — so you can see the artwork in context before you commit to
it. The same link appears under the full-screen preview, reading "View on TMDB"
and so on.

It opens in a new tab and doesn't choose the poster; tapping the poster still
previews it as usual.

For most services this is just a convenience. For some it isn't optional: TVmaze
licenses its artwork under CC BY-SA, which asks that Marquee link back wherever it
shows the image. Those links look the same as the rest, but they're the ones that
have to be there.

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
front and center: the secondary actions live behind the **⋯** Actions menu, and
importing, orphans, settings and your Plex connection all open as trays over the
gallery rather than navigating away from it. Search and sort stay pinned to the
top while you scroll, and tapping a poster opens a full-size action sheet.

To move between categories you can tap the bottom tab bar or just swipe the grid
sideways. The swipe follows your thumb rather than firing when you let go, so you
can see the next category arriving and abandon the gesture by sliding back — and
a swipe past the first or last category resists instead of doing nothing, so you
can feel that there is nothing there.

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
- **A poster URL is fetched only from the public internet.** When you change a
  poster by pasting an address — or by applying a **Find Posters** result, which
  travels the same way — Marquee resolves it first and refuses anything on a
  private, loopback, or link-local address, at every redirect, on ports 80 and
  443 only. Without that, anyone who got hold of a signed-in session could use
  the feature to probe your network from inside it. This does not affect
  reaching your own Plex server: `PLEX_SERVER_URL` is normally a private address
  and is deliberately exempt.
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
[getmarquee.now/#support](https://getmarquee.now/#support), or from inside the app
itself — **Support Development**, behind the **⋯** menu in the header, opens the
same ask over the page you are on rather than sending you to the website.
Feedback and bug reports are just as welcome.

[![Buy Me A Coffee](https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png)](https://www.buymeacoffee.com/jeremehancock)

## AI Disclosure

This project was created with the help of AI.
