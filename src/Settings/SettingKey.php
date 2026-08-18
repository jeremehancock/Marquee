<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * Every setting the store owns, with the environment variable that seeds it and
 * the default that applies when neither has a value.
 *
 * One enum rather than defaults scattered across the configuration objects,
 * because three things have to agree about each setting and used to agree only
 * by coincidence: what it is called in the store, which variable seeds it, and
 * what it falls back to. A default that lives beside its key cannot drift from
 * the key.
 *
 * This is deliberately not every variable Marquee reads. `DATA_DIR`,
 * `POSTERS_DIR`, and `SESSION_DIR` locate the store itself, so resolving them
 * from it would be circular; `DISPLAY_ERRORS` has to work when the store is
 * what broke; and `UPDATE_REPO` and `POSTER_SOURCE_URL` are development
 * overrides rather than settings an install is offered. Those stay on
 * {@see \App\Support\Env}.
 *
 * `PLEX_SERVER_URL` stays there too, and for a reason that is not like the
 * others: it is a security control rather than a preference. Marquee admits the
 * Plex account that owns the configured server, so the address decides who is
 * let in, and while it comes from the environment, choosing it takes access to
 * the host. That access is the assertion which stops the first stranger who
 * reaches a publicly reachable install from becoming its owner. Anywhere a
 * browser could set the address it would prove nothing, because whoever typed
 * it chose the server it names.
 *
 * This was tried the other way. The address was a case here, and replacing the
 * property it had been providing took a first-run claim code, a rate limiter, a
 * server probe, a middleware outside authentication, and a marker that had to
 * survive disconnecting — all so the user could read a code out of `docker logs`
 * and still type the address into a browser afterwards. Keeping one line in a
 * compose file is cheaper and gives the same guarantee. Do not add the case back
 * for consistency with the settings around it.
 *
 * Floors and fallbacks are not here either. The default is what applies when
 * nothing is stored; deciding that a stored session duration below sixty
 * seconds is a lockout, or that an unrecognised sort slug means A–Z, stays in
 * the configuration object that owns the meaning. Keeping correction in one
 * place is what guarantees a value corrected at bootstrap and a value rejected
 * at input cannot disagree.
 */
enum SettingKey: string
{
    case SiteTitle = 'site_title';
    case SessionDuration = 'session_duration';

    case PlexConnectTimeout = 'plex_connect_timeout';
    case PlexRequestTimeout = 'plex_request_timeout';
    case PlexRemoveOverlayLabel = 'plex_remove_overlay_label';

    case AutoImportEnabled = 'auto_import_enabled';
    case AutoImportSchedule = 'auto_import_schedule';
    case AutoImportMovies = 'auto_import_movies';
    case AutoImportShows = 'auto_import_shows';
    case AutoImportSeasons = 'auto_import_seasons';
    case AutoImportCollections = 'auto_import_collections';

    case ExcludedLibraries = 'excluded_libraries';

    case ImagesPerPage = 'images_per_page';
    case MaxFileSize = 'max_file_size';
    case IgnoreArticlesInSort = 'ignore_articles_in_sort';
    case DefaultSort = 'default_sort';

    case UpdateCheckEnabled = 'update_check_enabled';

    /**
     * The environment variable this setting is seeded from.
     */
    public function variable(): string
    {
        return match ($this) {
            self::SiteTitle => 'SITE_TITLE',
            self::SessionDuration => 'SESSION_DURATION',
            self::PlexConnectTimeout => 'PLEX_CONNECT_TIMEOUT',
            self::PlexRequestTimeout => 'PLEX_REQUEST_TIMEOUT',
            self::PlexRemoveOverlayLabel => 'PLEX_REMOVE_OVERLAY_LABEL',
            self::AutoImportEnabled => 'AUTO_IMPORT_ENABLED',
            self::AutoImportSchedule => 'AUTO_IMPORT_SCHEDULE',
            self::AutoImportMovies => 'AUTO_IMPORT_MOVIES',
            self::AutoImportShows => 'AUTO_IMPORT_SHOWS',
            self::AutoImportSeasons => 'AUTO_IMPORT_SEASONS',
            self::AutoImportCollections => 'AUTO_IMPORT_COLLECTIONS',
            self::ExcludedLibraries => 'EXCLUDED_LIBRARIES',
            self::ImagesPerPage => 'IMAGES_PER_PAGE',
            self::MaxFileSize => 'MAX_FILE_SIZE',
            self::IgnoreArticlesInSort => 'IGNORE_ARTICLES_IN_SORT',
            self::DefaultSort => 'DEFAULT_SORT',
            self::UpdateCheckEnabled => 'UPDATE_CHECK_ENABLED',
        };
    }

    /**
     * The value that applies when nothing is stored and nothing was seeded.
     *
     * @return string|int|bool|list<string>
     */
    public function default(): string|int|bool|array
    {
        return match ($this) {
            // Not AppConfig::APP_NAME: this names the install, and the product
            // name is only its starting value.
            self::SiteTitle => 'Marquee',
            // Thirty days, matching the sliding window's default.
            self::SessionDuration => 2592000,
            self::PlexConnectTimeout => 10,
            self::PlexRequestTimeout => 60,
            self::PlexRemoveOverlayLabel => false,
            self::AutoImportEnabled => false,
            self::AutoImportSchedule => '24h',
            self::AutoImportMovies => false,
            self::AutoImportShows => false,
            self::AutoImportSeasons => false,
            self::AutoImportCollections => false,
            self::ExcludedLibraries => [],
            self::ImagesPerPage => 24,
            self::MaxFileSize => 5_242_880,
            self::IgnoreArticlesInSort => true,
            // Empty rather than a slug: SortOrder owns which order is default,
            // and duplicating it here would let the two disagree.
            self::DefaultSort => '',
            self::UpdateCheckEnabled => false,
        };
    }

    /**
     * Every key, for seeding and for reporting superseded variables.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
