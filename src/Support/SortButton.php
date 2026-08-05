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
     * The button in a sentence — its text alternative and its tooltip.
     *
     * The active button has to say two things, because it shows one order and
     * applies another. Saying only the first reads as an instruction to sort that
     * way, which is the opposite of what activating it does; saying only the
     * second leaves the current order announced by nothing, the arrow being
     * silent. So it says both, and the inactive button — where the two orders are
     * the same — simply names the one.
     */
    public function description(): string
    {
        if (!$this->active) {
            return $this->shown->actionLabel();
        }

        return sprintf(
            '%s — activate for %s',
            $this->shown->stateLabel(),
            $this->target->directionPhrase(),
        );
    }
}
