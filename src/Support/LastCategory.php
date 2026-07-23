<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\GalleryView;
use App\Support\Session\SessionInterface;

/**
 * Remembers the library section the user was last viewing so that pages reached
 * from the gallery (Orphans, Import) can send them back to it. The section may
 * be a single category or the aggregate All view.
 */
final class LastCategory
{
    private const KEY = 'last_category';

    public static function remember(SessionInterface $session, GalleryView $view): void
    {
        $session->set(self::KEY, $view->value);
    }

    /**
     * The back-to-library URL for the remembered section, falling back to the
     * All view when nothing is remembered or the value is unknown.
     */
    public static function backUrl(SessionInterface $session): string
    {
        $stored = $session->get(self::KEY);
        $view = is_string($stored) ? GalleryView::fromSlug($stored) : null;

        return '/library/' . ($view ?? GalleryView::all())->value;
    }
}
