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
 * The current order alone cannot render the control. The inactive button has to
 * show the direction its own field was last left in, and that direction is not
 * recoverable from the active order — hence `alternate`.
 */
final class SortState
{
    /** The active field running the other way: what activating it applies. */
    public readonly SortOrder $toggled;

    public function __construct(
        /** The order the listing is in, and the one carried through URLs. */
        public readonly SortOrder $current,
        /** The other field at the direction it was last left in. */
        public readonly SortOrder $alternate,
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
        foreach ([SortField::Alphabetical, SortField::DateAdded] as $field) {
            $buttons[] = $field === $this->current->field()
                ? new SortButton($this->current, $this->toggled, true)
                : new SortButton($this->alternate, $this->alternate, false);
        }

        return $buttons;
    }
}
