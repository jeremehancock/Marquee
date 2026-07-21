<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Immutable poster/gallery configuration, built once from the environment.
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
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            perPage: max(1, Env::int('IMAGES_PER_PAGE', 24)),
            maxFileSize: max(1, Env::int('MAX_FILE_SIZE', 5_242_880)),
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            ignoreArticlesInSort: Env::bool('IGNORE_ARTICLES_IN_SORT', true),
        );
    }
}
