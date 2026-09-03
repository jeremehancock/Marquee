<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * When a set says it might be missing something.
 *
 * The rule this replaces was "an exact set is offered nothing, because it is
 * exact and there is nothing to widen". That conflated two facts. Membership IS
 * exact — Plex was asked and answered. But a Plex collection holds what somebody
 * put in it, so a collection of eight where the library holds nine is not a
 * contradiction; it is what forgetting to add a film looks like, and nothing on
 * screen said so.
 *
 * The replacement is one condition: offer the best shorter query only when it
 * would find MORE posters than the set holds. That single comparison does all
 * the suppression, and the tests below are arranged to prove it suppresses in
 * the right places rather than merely somewhere — a show's set, a collection
 * whose films share no words, and a complete set are each offered nothing for
 * the same reason, without any of them being special-cased.
 */
final class SetBroaderOfferTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    /** @var App<\Psr\Container\ContainerInterface|null>|null */
    private ?App $built = null;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        foreach (['movies', 'tv-shows', 'tv-seasons', 'collections'] as $category) {
            mkdir($this->postersDir . '/' . $category, 0o775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->postersDir);
        $this->built = null;
    }

    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    private function app(): App
    {
        if ($this->built === null) {
            $this->built = $this->makeSignedInApp([
                'POSTERS_DIR' => $this->postersDir,
                'DATA_DIR' => $this->makeTempDir(),
            ]);
        }

        return $this->built;
    }

    private function items(): PlexItemRepository
    {
        $container = $this->app()->getContainer();
        self::assertNotNull($container);
        /** @var PlexItemRepository $items */
        $items = $container->get(PlexItemRepository::class);

        return $items;
    }

    /**
     * @param list<string> $sets
     */
    private function poster(
        string $key,
        string $category,
        string $title,
        array $sets = [],
        ?int $seasonNumber = null,
        string $parentTitle = '',
    ): string {
        $filename = preg_replace('/[^A-Za-z0-9]+/', '_', $title) . '.png';
        $this->writePng($this->postersDir . '/' . $category . '/' . $filename);
        $this->items()->upsert(new PlexItemRecord(
            $key,
            'movie',
            $category,
            'Movies',
            $title,
            $filename,
            time(),
            seasonNumber: $seasonNumber,
            parentTitle: $parentTitle,
            setKeys: $sets,
        ));

        return $category . '/' . $filename;
    }

    private function body(string $set, string $origin): string
    {
        return (string) $this->get(
            $this->app(),
            '/library/all?set=' . $set . '&from=' . rawurlencode($origin),
        )->getBody();
    }

    /**
     * The headline case. A collection of two "Jackass" films while the library
     * holds a third that was never added to it.
     *
     * The origin is the SUBTITLED film deliberately. The candidates come from
     * BroaderQuery, which cuts at a subtitle separator or a trailing instalment
     * and does nothing else — so "Jackass: Best and Last" reaches "Jackass" and
     * "Jackass Forever" would reach nothing. See the limitation test below.
     */
    public function testAnIncompleteCollectionIsOfferedItsSeries(): void
    {
        $origin = $this->poster('1', 'movies', 'Jackass: Best and Last', ['50']);
        $this->poster('2', 'movies', 'Jackass Number Two', ['50']);
        // In the library, but not in the collection — the film someone forgot.
        $this->poster('3', 'movies', 'Jackass The Movie');

        $body = $this->body('50', $origin);

        self::assertStringContainsString('Missing something?', $body);
        self::assertStringContainsString('Jackass', $body);
        self::assertStringContainsString('finds 3', $body);
    }

    /**
     * The limit, stated as a test rather than left for someone to discover.
     *
     * The offer can only be as good as the candidates, and those come from cuts
     * at a subtitle separator or a trailing instalment number. A title with
     * neither — "Jackass Forever" — has nothing to cut, so an incomplete
     * collection opened from THAT film is offered nothing even though opening it
     * from a subtitled sibling would offer plenty.
     *
     * Narrow rather than wrong: the set shown is correct either way, and no
     * shorter query is invented to fill the gap. Widening BroaderQuery — say, by
     * dropping any last word — would change the typed search's offer too, which
     * is a separate decision about a different feature.
     */
    public function testAnOriginTitleWithNothingToCutIsOfferedNothing(): void
    {
        $origin = $this->poster('1', 'movies', 'Jackass Forever', ['50']);
        $this->poster('2', 'movies', 'Jackass Number Two', ['50']);
        $this->poster('3', 'movies', 'Jackass The Movie');

        self::assertStringNotContainsString('Missing something?', $this->body('50', $origin));
    }

    /**
     * The set shown is unchanged by the offer's presence. It is a suggestion,
     * never an application — the same bargain the typed search's offer makes.
     */
    public function testTheSetShownIsUnchangedByTheOffer(): void
    {
        $origin = $this->poster('1', 'movies', 'Jackass: Best and Last', ['50']);
        $this->poster('2', 'movies', 'Jackass Number Two', ['50']);
        $this->poster('3', 'movies', 'Jackass The Movie');

        $body = $this->body('50', $origin);

        self::assertStringContainsString('2 posters', $body, 'the set still holds its two');
        self::assertStringNotContainsString('Jackass The Movie', $body);
    }

    /**
     * A set already holding everything its title would find is offered nothing.
     */
    public function testACompleteSetIsOfferedNothing(): void
    {
        $origin = $this->poster('1', 'movies', 'Jackass: Best and Last', ['50']);
        $this->poster('2', 'movies', 'Jackass Number Two', ['50']);
        $this->poster('3', 'movies', 'Jackass The Movie', ['50']);

        self::assertStringNotContainsString('Missing something?', $this->body('50', $origin));
    }

    /**
     * A show's set is offered nothing, because a search for the show's title
     * finds exactly the show and its seasons — which the set already holds.
     * Nothing special-cases shows; the count comparison simply comes out equal.
     */
    public function testAShowsSetIsOfferedNothing(): void
    {
        $this->poster('10', 'tv-shows', 'Breaking Bad', ['10']);
        $origin = $this->poster('11', 'tv-seasons', 'Breaking Bad - Season 1', ['10'], 1, 'Breaking Bad');
        $this->poster('12', 'tv-seasons', 'Breaking Bad - Season 2', ['10'], 2, 'Breaking Bad');

        self::assertStringNotContainsString('Missing something?', $this->body('10', $origin));
    }

    /**
     * The case that would have needed special-casing under any other rule: a
     * collection whose films share no words. No title query can find more of the
     * MCU than the MCU already holds, so the comparison suppresses it with no
     * knowledge of what kind of collection it is.
     */
    public function testACollectionWhoseFilmsShareNoWordsIsOfferedNothing(): void
    {
        $origin = $this->poster('20', 'movies', 'Iron Man', ['60']);
        $this->poster('21', 'movies', 'Thor', ['60']);
        $this->poster('22', 'movies', 'Black Widow', ['60']);

        self::assertStringNotContainsString('Missing something?', $this->body('60', $origin));
    }

    /**
     * With no origin poster there is no title to derive a candidate from, so a
     * bookmarked or shared set link is offered nothing rather than guessing from
     * whichever member happens to sort first.
     */
    public function testASetWithNoOriginIsOfferedNothing(): void
    {
        $this->poster('1', 'movies', 'Jackass: Best and Last', ['50']);
        $this->poster('3', 'movies', 'Jackass The Movie');

        $body = (string) $this->get($this->app(), '/library/all?set=50')->getBody();

        self::assertStringNotContainsString('Missing something?', $body);
    }

    /**
     * The two offers are worded for the questions they ask, and neither borrows
     * the other's words.
     */
    public function testTheSetsOfferReadsDifferentlyFromTheSearchsOffer(): void
    {
        $origin = $this->poster('1', 'movies', 'Jackass: Best and Last', ['50']);
        $this->poster('2', 'movies', 'Jackass Number Two', ['50']);
        $this->poster('3', 'movies', 'Jackass The Movie');

        $set = $this->body('50', $origin);
        $search = (string) $this->get($this->app(), '/library/all?q=Jackass%3A+Best+and+Last')->getBody();

        self::assertStringContainsString('Missing something?', $set);
        self::assertStringNotContainsString('Looking for the rest of a series?', $set);

        self::assertStringContainsString('Looking for the rest of a series?', $search);
        self::assertStringNotContainsString('Missing something?', $search);
    }
}
