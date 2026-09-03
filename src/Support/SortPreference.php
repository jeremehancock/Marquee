<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\SortDirection;
use App\Poster\SortField;
use App\Poster\SortOrder;
use App\Support\Session\SessionInterface;

/**
 * Resolves the gallery sort for a request and remembers the user's choices for
 * the session. A `?sort=` query parameter (when valid) wins and is stored;
 * otherwise a previously stored choice applies; otherwise the configured default
 * (`DEFAULT_SORT`) is used.
 *
 * Alongside the order in force, each field's direction is remembered separately,
 * so leaving a field and coming back to it restores the way it was last running
 * rather than resetting it.
 */
final class SortPreference
{
    private const KEY = 'sort_order';

    private const DIRECTION_KEY_PREFIX = 'sort_direction_';

    /**
     * @param array<array-key, mixed> $queryParams
     * @param SortOrder|null          $viewDefault the order this particular view
     *        opens in when the address names none — a set opens in release order.
     *        It outranks the session's stored choice deliberately: otherwise a
     *        user who once picked Date added would never see a set in the order
     *        it was released, which is the whole point of opening one.
     * @param bool $remember whether a chosen order becomes the session's
     *        preference. False while a set is being shown — see below.
     */
    public static function resolve(
        SessionInterface $session,
        array $queryParams,
        SortOrder $default,
        ?SortOrder $viewDefault = null,
        bool $remember = true,
    ): SortState {
        $current = self::requested($queryParams);
        if ($current !== null) {
            // A chosen order is the one moment the user states a direction, so
            // it is also the only moment worth recording one.
            //
            // Except inside a set. A set is a question about one work, and the
            // answer must not outlive it: sorting a set by date added and then
            // clearing it would otherwise leave the whole library sorted that
            // way, which nobody asked for. The address still carries the choice,
            // so it survives paging and tab switches within the set without the
            // session's help.
            if ($remember) {
                $session->set(self::KEY, $current->value);
                $session->set(self::directionKey($current->field()), $current->direction()->value);
            }
        } else {
            $current = $viewDefault ?? self::stored($session) ?? $default;
        }

        return new SortState($current, self::rememberedAll($session));
    }

    /**
     * Each field at the direction it was last left in, for the control's
     * inactive buttons.
     *
     * @return array<string, SortOrder>
     */
    private static function rememberedAll(SessionInterface $session): array
    {
        $remembered = [];
        foreach (SortField::all() as $field) {
            $remembered[$field->value] = self::remembered($session, $field);
        }

        return $remembered;
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function requested(array $queryParams): ?SortOrder
    {
        $requested = $queryParams['sort'] ?? null;

        return is_string($requested) ? SortOrder::fromSlug($requested) : null;
    }

    private static function stored(SessionInterface $session): ?SortOrder
    {
        $stored = $session->get(self::KEY);

        return is_string($stored) ? SortOrder::fromSlug($stored) : null;
    }

    /**
     * A field at the direction it was last left in. An absent or unreadable
     * value falls back to the field's own default, which is what makes a session
     * holding only the older bare `sort_order` value keep working untouched.
     */
    private static function remembered(SessionInterface $session, SortField $field): SortOrder
    {
        $stored = $session->get(self::directionKey($field));
        $direction = is_string($stored) ? SortDirection::fromSlug($stored) : null;

        return $direction === null ? $field->defaultOrder() : $field->order($direction);
    }

    private static function directionKey(SortField $field): string
    {
        return self::DIRECTION_KEY_PREFIX . $field->value;
    }
}
