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

- **Import from Plex** — pull the current poster for every Movie, TV Show, TV
  Season, and Collection. A step-by-step picker asks what you want first, then
  shows only the libraries that can provide it.
- **Edit posters in place** — for any poster:
  - **Change poster** by uploading a file, pasting an image URL, or choosing
    from **Find Posters** — an online poster search served by
    [posteria.app](https://posteria.app).
  - **Send to Plex** — re-apply the poster Marquee has stored, and lock it.
  - **Fetch from Plex** — pull the item's current poster from Plex.
  - **Download**, **Copy URL**, view **Full screen**, or **Delete**.
- **Plex is the source of truth** — changing a poster uploads it to Plex and
  locks the artwork so Plex won't overwrite it.
- **Efficient imports** — Marquee skips re-downloading posters that haven't
  changed in Plex (with an option to force a full refresh), reducing load on your
  Plex server.
- **Auto-import** — optionally re-import on a schedule (1h / 3h / 6h / 12h / 24h).
- **Library exclusions** — hide chosen Plex libraries from Marquee entirely, in
  the UI and in every import.
- **Orphan detection** — find and remove posters whose media no longer exists in
  Plex, or whose library is now excluded.
- **Browse by type or all at once** — switch between Movies, TV Shows, TV
  Seasons, and Collections, or use the **All** view (the default) to see your
  whole library in one grid, each poster tagged with its type.
- **Sort your way** — order the gallery alphabetically or by when each item was
  added to Plex (newest first). Toggle it in the gallery, or set the install
  default with `DEFAULT_SORT`.
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
  with search and sort pinned to the top as you scroll.
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
      # --- Container (LinuxServer base) ---
      PUID: "1000"                    # match your host user (id -u)
      PGID: "1000"                    # match your host group (id -g)
      TZ: "Etc/UTC"

      # --- Authentication (CHANGE THESE) ---
      AUTH_USERNAME: "admin"
      AUTH_PASSWORD: "change-me"
      # AUTH_BYPASS: "false"          # "true" disables login — trusted LAN only

      # --- Plex (required for import / send / fetch / orphans) ---
      PLEX_SERVER_URL: "http://192.168.1.10:32400"
      PLEX_TOKEN: "your-plex-token"
      # PLEX_REMOVE_OVERLAY_LABEL: "false"   # "true" if you use Kometa overlays

      # --- Auto-import (optional) ---
      # AUTO_IMPORT_ENABLED: "false"
      # AUTO_IMPORT_SCHEDULE: "24h"   # 1h | 3h | 6h | 12h | 24h
      # AUTO_IMPORT_MOVIES: "true"
      # AUTO_IMPORT_SHOWS: "true"
      # AUTO_IMPORT_SEASONS: "false"
      # AUTO_IMPORT_COLLECTIONS: "false"

      # --- Libraries to hide from Marquee entirely (optional) ---
      # Library names as they appear in Plex, not section ids.
      # EXCLUDED_LIBRARIES: "4K Movies,Kids"

      # --- Optional tweaks ---
      # SITE_TITLE: "Marquee"
      # IMAGES_PER_PAGE: "24"
      # DEFAULT_SORT: "alphabetical"  # alphabetical | date_added
      # UPDATE_CHECK_ENABLED: "false"
    volumes:
      - ./marquee/config:/config
    restart: unless-stopped
```

Then start it:

```bash
docker compose up -d
```

Open `http://<host>:1818`, log in with the credentials you set, and go to
**Import from Plex** to pull in your posters.

The `/config` volume holds everything Marquee needs to persist:

- `/config/posters` — the poster images, grouped by category
- `/config/data` — the SQLite database and logs

Back this directory up if you want to keep your poster selections.

## Configuration

Everything is configured with environment variables. All are optional except the
credentials you should change and the Plex settings needed to talk to your
server.

| Variable | Description | Default |
| --- | --- | --- |
| `PUID` / `PGID` | User / group id that owns the `/config` volume | `911` |
| `TZ` | Timezone (e.g. `America/New_York`) | `Etc/UTC` |
| `SITE_TITLE` | Site name shown in the header and browser tab. Does not rename the installed app, which is always "Marquee". | `Marquee` |
| `AUTH_USERNAME` | Login username | `admin` |
| `AUTH_PASSWORD` | Login password | `changeme` |
| `AUTH_BYPASS` | Disable authentication entirely (trusted LAN only) | `false` |
| `SESSION_DURATION` | Login session lifetime, in seconds | `3600` |
| `PLEX_SERVER_URL` | Plex Media Server URL, e.g. `http://10.0.0.5:32400` | _(unset)_ |
| `PLEX_TOKEN` | Plex authentication token (`X-Plex-Token`) | _(unset)_ |
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
| `DEFAULT_SORT` | Preferred gallery sort: `alphabetical` or `date_added` (by date added to Plex, newest first). Users can toggle it in the gallery. | `alphabetical` |
| `UPDATE_CHECK_ENABLED` | Check GitHub for a newer release | `false` |
| `UPDATE_REPO` | Repository to check for releases (`owner/repo`) | `jeremehancock/Marquee` |

### Finding your Plex token

1. Log in to your Plex Web App.
2. Browse to any media item.
3. Click the **⋯** menu and choose **Get Info**.
4. In the info dialog, click **View XML**.
5. In the URL of the new tab, copy the value of the `X-Plex-Token=` parameter.

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
5. **Optionally turn on auto-import.** Set `AUTO_IMPORT_ENABLED=true`, pick a
   schedule with `AUTO_IMPORT_SCHEDULE`, and enable the types you want with the
   `AUTO_IMPORT_*` variables so new media picks up posters on its own.

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
existed in Marquee — art you uploaded but never sent — is gone.

**Where do "Find Posters" results come from?**
From [posteria.app](https://posteria.app), an online poster search service.

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

If Find Posters is already working well for you, there's nothing here you need
to do.

**Does it work on mobile?**
Yes. Marquee is responsive and installable as a PWA. On a phone the gallery stays
front and center: the secondary actions live behind the **⋯** Actions menu,
search and sort stay pinned to the top while you scroll, and tapping a poster
opens a full-size action sheet.

## Security considerations

Marquee protects your poster collection with basic authentication — set your
username and password in `docker-compose.yml` before you start it. All
communication with your Plex server is done securely using your Plex
authentication token.

- **Change the default username and password**, and pick a strong one.
- **Use HTTPS** (behind a reverse proxy) if you expose Marquee to the internet.
- **Back up your `/config` directory** regularly — an import can rebuild
  everything Plex already has, but not art you uploaded and never sent there.

Only enable `AUTH_BYPASS` on a network you fully trust — it disables login
entirely.

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
