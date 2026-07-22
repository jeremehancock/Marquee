<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\PosterCategory;
use App\Support\Session\SessionInterface;

/**
 * Remembers the library section the user was last viewing so that pages reached
 * from the gallery (Orphans, Import) can send them back to it.
 */
final class LastCategory
{
    private const KEY = 'last_category';

    public static function remember(SessionInterface $session, PosterCategory $category): void
    {
        $session->set(self::KEY, $category->value);
    }

    /**
     * The back-to-library URL for the remembered section, falling back to the
     * default category when nothing is remembered or the value is unknown.
     */
    public static function backUrl(SessionInterface $session): string
    {
        $stored = $session->get(self::KEY);
        $category = is_string($stored) ? PosterCategory::fromSlug($stored) : null;

        return '/library/' . ($category ?? PosterCategory::default())->value;
    }
}
