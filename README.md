# Marquee

Marquee is a self-hosted web app for managing custom media posters for a Plex
library — movies, TV shows, TV seasons, and collections. Import posters from
Plex, customize them (upload from disk/URL or fetch from TMDB/TVDB/Fanart/Mediux),
and sync them back to Plex with poster locking.

> **Status: early rewrite in progress.** Marquee is a clean-room rebuild of an
> older monolithic PHP app. It is built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec)
> and developed capability by capability. This repository currently contains the
> **application shell** (Phase 0/1): routing, configuration, authentication,
> logging, the container image, and CI. Poster features land in subsequent phases.

## Tech stack

- **PHP 8.3+**, Composer, PSR-4 autoloading, PSR-12 (`strict_types` everywhere)
- **[Slim 4](https://www.slimframework.com/)** (PSR-7 / PSR-15) with **PHP-DI**
- **Twig** server-rendered templates + **Alpine.js** (no build step)
- **Guzzle** for outbound HTTP; **SQLite** (PDO) for metadata; **Monolog** for logs
- **Docker**: LinuxServer Alpine-nginx base with s6-overlay
- Quality gates: **PHPUnit**, **PHPStan** (level 8), **PHP-CS-Fixer**, GitHub Actions

## Development

Requires PHP 8.3+ and Composer.

```bash
composer install

composer test          # PHPUnit
composer stan          # PHPStan static analysis
composer cs            # PHP-CS-Fixer (dry-run)
composer cs:fix        # PHP-CS-Fixer (apply)

# Run the app locally on http://localhost:8080
php -S localhost:8080 -t public public/index.php
```

## Docker

```bash
docker build -t marquee .
docker run -d --name marquee -p 1818:80 \
  -e AUTH_USERNAME=admin -e AUTH_PASSWORD=change-me \
  -v ./config:/config \
  marquee
```

The `/config` volume holds poster images (`/config/posters`) and application
data and logs (`/config/data`).

### Configuration (current phase)

| Variable           | Description                              | Default    |
| ------------------ | ---------------------------------------- | ---------- |
| `SITE_TITLE`       | Site title shown in the UI               | `Marquee`  |
| `AUTH_USERNAME`    | Admin username                           | `admin`    |
| `AUTH_PASSWORD`    | Admin password                           | `changeme` |
| `AUTH_BYPASS`      | Skip authentication (trusted LAN only)   | `false`    |
| `SESSION_DURATION` | Login session lifetime, in seconds       | `3600`     |
| `PUID` / `PGID`    | User/group id for the `/config` volume   | `911`      |
| `TZ`               | Timezone                                 | `Etc/UTC`  |

More variables (Plex, auto-import, poster sources) arrive with their features.

## Spec-driven development with OpenSpec

Every capability is defined as an OpenSpec spec before it is built. Specs live
under `openspec/`, and each feature flows through a change proposal.

```bash
openspec list                 # active changes
openspec validate <change>    # validate a change
openspec archive <change>     # fold an implemented change into the specs
```

Project context and conventions for AI-assisted work live in
`openspec/config.yaml`.

## License

[MIT](LICENSE)
