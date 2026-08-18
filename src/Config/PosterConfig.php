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
            perPage: max(1, $store->int(SettingKey::ImagesPerPage)),
            maxFileSize: max(1, $store->int(SettingKey::MaxFileSize)),
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
