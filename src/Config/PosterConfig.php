<?php

declare(strict_types=1);

namespace App\Config;

use App\Poster\SortOrder;
use App\Settings\SettingKey;
use App\Settings\SettingsStore;

/**
 * Immutable poster/gallery configuration, built once at bootstrap.
 */
final class PosterConfig
{
    /**
     * The fewest posters a page will resolve to.
     *
     * A page of zero posters renders nothing and paginates forever. The settings
     * screen applies this same constant, so a value it accepts is never one
     * bootstrap corrects.
     */
    public const MINIMUM_PER_PAGE = 1;

    /**
     * The smallest maximum upload size this will resolve, in bytes.
     *
     * Zero would reject every upload while reading as "no limit".
     */
    public const MINIMUM_FILE_SIZE = 1;

    /**
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        public readonly int $perPage,
        public readonly int $maxFileSize,
        public readonly array $allowedExtensions,
        public readonly bool $ignoreArticlesInSort,
        public readonly SortOrder $defaultSort,
    ) {
    }

    public static function resolve(SettingsStore $store): self
    {
        return new self(
            perPage: max(self::MINIMUM_PER_PAGE, $store->int(SettingKey::ImagesPerPage)),
            maxFileSize: max(self::MINIMUM_FILE_SIZE, $store->int(SettingKey::MaxFileSize)),
            // Not a setting. The list is what the image pipeline can actually
            // decode, so offering it as a choice would let an install ask for a
            // format nothing downstream can read.
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            ignoreArticlesInSort: $store->bool(SettingKey::IgnoreArticlesInSort),
            // Unset, empty, or unrecognized falls back to A–Z.
            defaultSort: SortOrder::fromSlug($store->string(SettingKey::DefaultSort)) ?? SortOrder::default(),
        );
    }
}
