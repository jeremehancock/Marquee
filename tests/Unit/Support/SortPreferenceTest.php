<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Poster\SortOrder;
use App\Support\Session\ArraySession;
use App\Support\SortPreference;
use App\Support\SortState;
use PHPUnit\Framework\TestCase;

final class SortPreferenceTest extends TestCase
{
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private function resolve(array $queryParams, SortOrder $default = SortOrder::Alphabetical): SortState
    {
        return SortPreference::resolve($this->session, $queryParams, $default);
    }

    public function testQueryParameterWinsAndIsRemembered(): void
    {
        self::assertSame(SortOrder::DateAdded, $this->resolve(['sort' => 'date_added'])->current);
        self::assertSame(SortOrder::DateAdded, $this->resolve([])->current);
    }

    public function testStoredChoiceBeatsTheConfiguredDefault(): void
    {
        $this->resolve(['sort' => 'date_added']);

        self::assertSame(SortOrder::DateAdded, $this->resolve([], SortOrder::Alphabetical)->current);
    }

    public function testFallsBackToTheConfiguredDefault(): void
    {
        self::assertSame(SortOrder::DateAdded, $this->resolve([], SortOrder::DateAdded)->current);
    }

    public function testUnrecognizedQueryValueIsIgnored(): void
    {
        self::assertSame(SortOrder::Alphabetical, $this->resolve(['sort' => 'newest'])->current);
    }

    public function testToggledIsTheCurrentOrderReversed(): void
    {
        $state = $this->resolve(['sort' => 'alphabetical']);

        self::assertSame(SortOrder::AlphabeticalDesc, $state->toggled);
    }

    public function testAlternateStartsAtTheOtherFieldsDefaultDirection(): void
    {
        $state = $this->resolve(['sort' => 'alphabetical']);

        self::assertSame(SortOrder::DateAdded, $state->alternate);
    }

    /**
     * The point of remembering a direction per field: leaving a field and coming
     * back to it restores the way it was running, rather than resetting it.
     */
    public function testEachFieldRemembersItsOwnDirection(): void
    {
        $this->resolve(['sort' => 'alphabetical_desc']);
        $this->resolve(['sort' => 'date_added_asc']);

        // On the date field, the title button offers the direction it was left in.
        $onDate = $this->resolve([]);
        self::assertSame(SortOrder::DateAddedAsc, $onDate->current);
        self::assertSame(SortOrder::AlphabeticalDesc, $onDate->alternate);

        // Going back gives Z–A, and the date button still offers oldest first.
        $backOnTitle = $this->resolve(['sort' => $onDate->alternate->value]);
        self::assertSame(SortOrder::AlphabeticalDesc, $backOnTitle->current);
        self::assertSame(SortOrder::DateAddedAsc, $backOnTitle->alternate);
    }

    public function testChangingOneFieldsDirectionLeavesTheOtherAlone(): void
    {
        $this->resolve(['sort' => 'date_added_asc']);
        $this->resolve(['sort' => 'alphabetical_desc']);
        $this->resolve(['sort' => 'alphabetical']);

        self::assertSame(SortOrder::DateAddedAsc, $this->resolve([])->alternate);
    }

    /**
     * A session written before directions existed holds a bare slug and no
     * direction keys at all. It must keep working and read as today's behaviour.
     */
    public function testLegacySessionValueUpgradesSilently(): void
    {
        $this->session->set('sort_order', 'alphabetical');

        $state = $this->resolve([]);

        self::assertSame(SortOrder::Alphabetical, $state->current);
        self::assertSame(SortOrder::DateAdded, $state->alternate);
    }

    public function testUnreadableDirectionFallsBackToTheFieldsDefault(): void
    {
        $this->session->set('sort_direction_date_added', 'sideways');

        self::assertSame(SortOrder::DateAdded, $this->resolve(['sort' => 'alphabetical'])->alternate);
    }

    /**
     * The toggle, stated as the control renders it: the active button reads as
     * the order in force but applies its reverse, while the inactive one reads
     * and applies the same order.
     */
    public function testActiveButtonAppliesTheReverseOfWhatItReads(): void
    {
        $buttons = $this->resolve(['sort' => 'alphabetical'])->buttons();

        self::assertTrue($buttons[0]->active);
        self::assertSame(SortOrder::Alphabetical, $buttons[0]->shown);
        self::assertSame(SortOrder::AlphabeticalDesc, $buttons[0]->target);

        self::assertFalse($buttons[1]->active);
        self::assertSame($buttons[1]->shown, $buttons[1]->target);
    }

    /**
     * The active button shows one order and applies another, so describing it
     * with a single "Sort by …" reads as an instruction to sort the way it is
     * already sorted — the misreading that made hovering it look like it would
     * do the opposite of what it does. It states the current order and names the
     * one activating it applies.
     */
    public function testActiveButtonDescribesBothWhatItIsAndWhatItDoes(): void
    {
        $buttons = $this->resolve(['sort' => 'alphabetical'])->buttons();

        self::assertSame('Sorted by title, A to Z — activate for Z to A', $buttons[0]->description());
    }

    /**
     * The inactive button shows and applies the same order, so it has only the
     * one thing to say, and says it as the instruction it is.
     */
    public function testInactiveButtonDescribesOnlyWhatItDoes(): void
    {
        $buttons = $this->resolve(['sort' => 'alphabetical'])->buttons();

        self::assertSame('Sort by date added, newest first', $buttons[1]->description());
    }

    /**
     * Whatever the state, a button never claims that activating it applies the
     * order it is already showing.
     */
    public function testNoButtonEverInstructsTheOrderItAlreadyShows(): void
    {
        foreach (['alphabetical', 'alphabetical_desc', 'date_added', 'date_added_asc'] as $slug) {
            foreach ($this->resolve(['sort' => $slug])->buttons() as $button) {
                if ($button->active) {
                    self::assertStringNotContainsString($button->shown->actionLabel(), $button->description());
                    self::assertStringContainsString($button->target->directionPhrase(), $button->description());
                } else {
                    self::assertSame($button->shown->actionLabel(), $button->description());
                }
            }
        }
    }

    /**
     * Title first, date second, whichever is active — a control that reordered
     * itself as you used it would move the button out from under the pointer.
     */
    public function testButtonOrderIsFixed(): void
    {
        foreach (['alphabetical', 'date_added'] as $slug) {
            $buttons = $this->resolve(['sort' => $slug])->buttons();

            self::assertCount(2, $buttons);
            self::assertSame('sort-title', $buttons[0]->shown->field()->glyph());
            self::assertSame('sort-date', $buttons[1]->shown->field()->glyph());
        }
    }
}
