<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\SortOrder;
use App\Support\Session\SessionInterface;

/**
 * Resolves the gallery sort order for a request and remembers the user's choice
 * for the session. A `?sort=` query parameter (when valid) wins and is stored;
 * otherwise a previously stored choice applies; otherwise the configured default
 * (`DEFAULT_SORT`) is used.
 */
final class SortPreference
{
    private const KEY = 'sort_order';

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function resolve(SessionInterface $session, array $queryParams, SortOrder $default): SortOrder
    {
        $requested = $queryParams['sort'] ?? null;
        if (is_string($requested)) {
            $order = SortOrder::fromSlug($requested);
            if ($order !== null) {
                $session->set(self::KEY, $order->value);

                return $order;
            }
        }

        $stored = $session->get(self::KEY);
        $order = is_string($stored) ? SortOrder::fromSlug($stored) : null;

        return $order ?? $default;
    }
}
