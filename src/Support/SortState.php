<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\SortField;
use App\Poster\SortOrder;

/**
 * The gallery's sort as the view needs it: the order in force, and enough
 * besides to render a control whose buttons each know what they say and what
 * they do.
 *
 * The current order alone cannot render the control. Each inactive button has to
 * show the direction its own field was last left in, and those directions are
 * not recoverable from the active order — hence `remembered`.
 *
 * This held a single `alternate` while there were two fields, which was the same
 * idea written for a control that could only ever have one inactive button. With
 * three fields there is no "the other one", so every field's remembered order is
 * carried and the active one simply overrides its own.
 */
final class SortState
{
    /** The active field running the other way: what activating it applies. */
    public readonly SortOrder $toggled;

    /**
     * @param array<string, SortOrder> $remembered each field's last-used order,
     *        keyed by the field's value; a field absent here falls back to its
     *        own default direction
     */
    public function __construct(
        /** The order the listing is in, and the one carried through URLs. */
        public readonly SortOrder $current,
        private readonly array $remembered = [],
    ) {
        $this->toggled = $current->flipped();
    }

    /**
     * One button per field in a fixed order, so the control does not reshuffle
     * itself as the user sorts.
     *
     * @return list<SortButton>
     */
    public function buttons(): array
    {
        $buttons = [];
        foreach (SortField::all() as $field) {
            if ($field === $this->current->field()) {
                $buttons[] = new SortButton($this->current, $this->toggled, true);
                continue;
            }

            $order = $this->remembered[$field->value] ?? $field->defaultOrder();
            $buttons[] = new SortButton($order, $order, false);
        }

        return $buttons;
    }
}
