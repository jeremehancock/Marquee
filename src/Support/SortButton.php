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
        /** What the button reads: its label, its arrow, its text alternative. */
        public readonly SortOrder $shown,
        /** What activating it applies, and so the slug its link carries. */
        public readonly SortOrder $target,
        public readonly bool $active,
    ) {
    }
}
