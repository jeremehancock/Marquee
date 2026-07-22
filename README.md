# Marquee

> ⚠️ **Early Alpha — not ready for general use.** Marquee is under active
> development. Things may change, break, or behave unexpectedly without notice.
> Don't point it at a Plex library you aren't willing to experiment on, and keep
> your own backups. Testing and feedback are very welcome — but treat it as
> experimental for now.

Marquee is a self-hosted web app for managing your Plex media posters — for
Movies, TV Shows, TV Seasons, and Collections. Import every poster from Plex,
then refine each one in place: upload your own art, paste an image URL, or pick a
replacement from an online poster search. When you update a poster, Marquee sends
it back to Plex and locks it so Plex keeps your choice.

Marquee is a ground-up rewrite of [Posteria](https://github.com/jeremehancock/Posteria):
same idea, cleaner code, built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec).

## Features

- **Import from Plex** — pull the current poster for every Movie, TV Show, TV
  Season, and Collection. A step-by-step picker asks what you want first, then
  shows only the libraries that can provide it.
- **Edit posters in place** — for any poster:
  - **Change poster** by uploading a file, pasting an image URL (Mediux URLs
    included), or choosing from **Find Posters** (an online poster search).
  - **Send to Plex** — re-apply the poster Marquee has stored, and lock it.
  - **Fetch from Plex** — pull the item's current poster from Plex.
  - **Download**, **Copy URL**, view **Full screen**, or **Delete**.
- **Plex is the source of truth** — changing a poster uploads it to Plex and
  locks the artwork so Plex won't overwrite it.
- **Efficient imports** — Marquee skips re-downloading posters that haven't
  changed in Plex (with an option to force a full refresh), reducing load on your
  Plex server.
- **Auto-import** — optionally re-import on a schedule (1h / 3h / 6h / 12h / 24h).
- **Orphan detection** — find and remove posters whose media no longer exists in
  Plex. Posters you added yourself are never treated as orphans.
- **Poster Wall** — a full-screen, slideshow-style view of your library.
- **Fast, modern UI** — search as you type, background updates without full page
  reloads, and a touch-friendly action sheet on mobile.
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
      # EXCLUDED_LIBRARIES: "4K Movies,Kids"

      # --- Optional tweaks ---
      # SITE_TITLE: "Marquee"
      # IMAGES_PER_PAGE: "24"
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
| `SITE_TITLE` | Title shown in the UI | `Marquee` |
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
| `EXCLUDED_LIBRARIES` | Library names to skip, comma-separated | _(none)_ |
| `IMAGES_PER_PAGE` | Posters shown per gallery page | `24` |
| `MAX_FILE_SIZE` | Maximum upload size, in bytes | `5242880` |
| `IGNORE_ARTICLES_IN_SORT` | Ignore leading "a/an/the" when sorting | `true` |
| `POSTER_SOURCE_URL` | Base URL of the poster search service used by **Find Posters** | `https://posteria.app` |
| `UPDATE_CHECK_ENABLED` | Check GitHub for a newer release | `false` |
| `UPDATE_REPO` | Repository to check for releases (`owner/repo`) | `jeremehancock/Posteria-II` |

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
   Posters**. Applying a new poster updates it locally, uploads it to Plex, and
   locks it.
3. **Keep Plex and Marquee in sync.** Use **Send to Plex** to re-apply Marquee's
   stored poster (for example after a Plex agent refresh), or **Fetch from Plex**
   to pull the item's current Plex art back into Marquee.
4. **Tidy up.** Open **Orphans** to remove posters whose media no longer exists
   in Plex, or **Poster Wall** for a full-screen slideshow.

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
| `bozodev/marquee:latest` | the `main` branch | production |
| `bozodev/marquee:dev` | the `dev` branch | testing upcoming changes |
| `bozodev/marquee:X.Y.Z` | a `vX.Y.Z` git tag | pinning a specific release |

Images are built and pushed automatically by GitHub Actions
(`.github/workflows/docker-publish.yml`).

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
usually because you removed that content from Plex. Posters you uploaded yourself
are never treated as orphans.

**Where do "Find Posters" results come from?**
From the poster search service at `POSTER_SOURCE_URL` (default
[posteria.app](https://posteria.app)), which aggregates online poster sources.
Point it at your own instance if you self-host the service.

**Does it work on mobile?**
Yes. Marquee is responsive and installable as a PWA; on touch devices, tapping a
poster opens a full-size action sheet.

## Security considerations

- **Change the default username and password.**
- **Use HTTPS** (behind a reverse proxy) if you expose Marquee to the internet.
- **Back up your `/config` directory** regularly.

Only enable `AUTH_BYPASS` on a network you fully trust — it disables login
entirely.

## Development

Requires PHP 8.3+ and Composer.

```bash
composer install

composer test          # PHPUnit
composer stan          # PHPStan (level 8)
composer cs            # PHP-CS-Fixer (dry-run)
composer cs:fix        # PHP-CS-Fixer (apply)

# Run locally on http://localhost:8080
php -S localhost:8080 -t public public/index.php
```

See [`docs/development-workflow.md`](docs/development-workflow.md) for the
VSCodium + Claude Code + OpenSpec setup and the `dev`/`main` branch flow, and
[`docs/testing.md`](docs/testing.md) for validating the live Plex round-trip
(poster locking and the Kometa label), including the
[`scripts/marquee-plex-test.py`](scripts/marquee-plex-test.py) tester.

### Tech stack

- **PHP 8.3+**, Composer, PSR-4 autoloading, `strict_types` throughout
- **[Slim 4](https://www.slimframework.com/)** (PSR-7 / PSR-15) with **PHP-DI**
- **Twig** server-rendered templates + **Alpine.js** (no build step)
- **Guzzle** for outbound HTTP, **SQLite** (PDO) for metadata, **Monolog** for logs
- **Docker**: LinuxServer Alpine-nginx base with s6-overlay and cron
- Quality gates: **PHPUnit**, **PHPStan** (level 8), **PHP-CS-Fixer**, GitHub Actions

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

Marquee is a rewrite of [Posteria](https://github.com/jeremehancock/Posteria) by
Jereme Hancock. Built with the help of AI.
