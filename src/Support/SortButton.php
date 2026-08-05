<?php

declare(strict_types=1);

namespace App\Support;

use App\Poster\SortOrder;

/**
 * One button of the gallery's sort control, resolved so the template decides
 * nothing.
 *
 * The two orders differ only on the active button, and that difference is the
 * whole toggle: the label describes the order the gallery is in now, while
 * activating it applies the reverse. On an inactive button they are the same
 * order — the direction that field was last left in, which is both what the
 * button reads and what activating it applies.
 */
final class SortButton
{
    public function __construct(
        /** What the button reads: its label and its arrow. */
        public readonly SortOrder $shown,
        /** What activating it applies, and so the slug its link carries. */
        public readonly SortOrder $target,
        public readonly bool $active,
    ) {
    }

    /**
     * The button's accessible name.
     *
     * "Activate" rather than "click" because a name is read wherever the control
     * is, including where there is no pointer to click with — it is the verb ARIA
     * itself uses for the same reason.
     */
    public function description(): string
    {
        return $this->sentence('activate');
    }

    /**
     * The button's hover text.
     *
     * "Click" rather than "activate" because a tooltip only ever appears on
     * hover, so a pointer is the one thing its reader is guaranteed to have.
     */
    public function tooltip(): string
    {
        return $this->sentence('click');
    }

    /**
     * The active button has to say two things, because it shows one order and
     * applies another. Saying only the first reads as an instruction to sort the
     * way the gallery is already sorted, which is the opposite of what the button
     * does; saying only the second leaves the current order stated by nothing,
     * the arrow being silent and the date button's label never changing. So it
     * says both, and the inactive button — where the two orders are the same —
     * simply names the one.
     */
    private function sentence(string $verb): string
    {
        if (!$this->active) {
            return $this->shown->actionLabel();
        }

        return sprintf(
            '%s — %s for %s',
            $this->shown->stateLabel(),
            $verb,
            $this->target->directionPhrase(),
        );
    }
}
