<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Database\PlexItemRecord;
use App\Database\PlexItemRepository;
use App\Tests\AppTestCase;
use App\Tests\Support\MakesImages;
use Slim\App;

/**
 * What a set is called, and how it says which other sets its poster is in.
 *
 * A film in two collections could only ever open one of them, and did so
 * silently: "Godzilla vs. Kong" is in both King Kong and MonsterVerse, and the
 * card's link took whichever was recorded first. The set view now names the
 * others, which needs two things — knowing which poster the set was opened from,
 * and knowing what the other sets are called even when their own posters were
 * never imported.
 *
 * Both directions of every rule are asserted, because the last change to sets
 * was undone three times by designs that looked right against data chosen to
 * suit them: a film in two collections AND a film in one, a set with a recorded
 * name AND one without, an origin that resolves AND one that does not.
 */
final class SetNamingTest extends AppTestCase
{
    use MakesImages;

    private string $postersDir;

    /** @var App<\Psr\Container\ContainerInterface|null>|null */
    private ?App $built = null;

    protected function setUp(): void
    {
        $this->postersDir = $this->makeTempDir();
        foreach (['movies', 'collections'] as $category) {
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
            // A data directory of its own: this class writes set names, and a
            // shared mapping database would carry them into other tests.
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
    private function film(string $key, string $title, array $sets): void
    {
        $filename = str_replace(' ', '_', $title) . '.png';
        $this->writePng($this->postersDir . '/movies/' . $filename);
        $this->items()->upsert(new PlexItemRecord(
            $key,
            'movie',
            'movies',
            'Movies',
            $title,
            $filename,
            time(),
            setKeys: $sets,
        ));
    }

    private function origin(string $title): string
    {
        return 'movies/' . str_replace(' ', '_', $title) . '.png';
    }

    /**
     * The headline case. Godzilla vs. Kong is in both; the link opened one of
     * them and gave no sign the other existed.
     */
    public function testAFilmInTwoCollectionsNamesTheOther(): void
    {
        $this->film('10', 'Godzilla vs Kong', ['100', '200']);
        $this->items()->rememberSetName('100', 'King Kong');
        $this->items()->rememberSetName('200', 'MonsterVerse');

        $body = (string) $this->get(
            $this->app(),
            '/library/all?set=100&from=' . rawurlencode($this->origin('Godzilla vs Kong')),
        )->getBody();

        // The FILM is named. "Also in MonsterVerse" alone gives no clue which of
        // the posters on screen it is about, and following the link makes it
        // worse — the next set says the same thing about a film the reader can no
        // longer identify.
        self::assertStringContainsString('Godzilla vs Kong is also in', $body);
        self::assertStringContainsString('MonsterVerse', $body);
        self::assertStringContainsString('set=200', $body);
        self::assertStringNotContainsString('>King Kong</a>', $body, 'the set being shown is not "also"');
    }

    /**
     * Following it carries the origin onward, so a film in several collections
     * can be walked between them rather than reached once and lost.
     */
    public function testFollowingItNamesTheFirstSetBack(): void
    {
        $this->film('10', 'Godzilla vs Kong', ['100', '200']);
        $this->items()->rememberSetName('100', 'King Kong');
        $this->items()->rememberSetName('200', 'MonsterVerse');

        $body = (string) $this->get(
            $this->app(),
            '/library/all?set=200&from=' . rawurlencode($this->origin('Godzilla vs Kong')),
        )->getBody();

        self::assertStringContainsString('Godzilla vs Kong is also in', $body);
        self::assertStringContainsString('King Kong', $body);
        self::assertStringContainsString('set=100', $body);
    }

    /**
     * The other direction, and the reason the link opens a set directly rather
     * than offering a choice: one collection is the ordinary case, and it must
     * cost the reader nothing.
     */
    public function testAFilmInOneCollectionIsToldNothing(): void
    {
        $this->film('11', 'Solaris', ['300']);
        $this->items()->rememberSetName('300', 'Tarkovsky');

        $body = (string) $this->get(
            $this->app(),
            '/library/all?set=300&from=' . rawurlencode($this->origin('Solaris')),
        )->getBody();

        self::assertStringNotContainsString('is also in', $body);
    }

    /**
     * A set address with no origin renders exactly as it always did. This is
     * what makes the parameter safe to add: every bookmarked and shared set link
     * that predates it still works.
     */
    public function testASetWithNoOriginRendersWithoutTheLine(): void
    {
        $this->film('10', 'Godzilla vs Kong', ['100', '200']);
        $this->items()->rememberSetName('200', 'MonsterVerse');

        $body = (string) $this->get($this->app(), '/library/all?set=100')->getBody();

        self::assertStringContainsString('Godzilla vs Kong', $body);
        self::assertStringNotContainsString('is also in', $body);
    }

    /**
     * An origin naming a poster that has since been deleted must change nothing
     * at all — not the members, not the absence of an error.
     */
    public function testAnOriginThatNoLongerResolvesIsIgnored(): void
    {
        $this->film('10', 'Godzilla vs Kong', ['100', '200']);
        $this->items()->rememberSetName('200', 'MonsterVerse');

        $withGhost = $this->get($this->app(), '/library/all?set=100&from=movies/Deleted.png');
        $without = $this->get($this->app(), '/library/all?set=100');

        self::assertSame(200, $withGhost->getStatusCode());
        self::assertStringContainsString('Godzilla vs Kong', (string) $withGhost->getBody());
        self::assertStringNotContainsString('is also in', (string) $withGhost->getBody());
        self::assertSame($without->getStatusCode(), $withGhost->getStatusCode());
    }

    /**
     * An origin naming a category that does not exist is not an error either —
     * it is a link somebody edited, and it resolves to nothing.
     */
    public function testAnOriginNamingAnUnknownCategoryIsIgnored(): void
    {
        $this->film('10', 'Godzilla vs Kong', ['100', '200']);

        $response = $this->get($this->app(), '/library/all?set=100&from=nonsense/Whatever.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('is also in', (string) $response->getBody());
    }

    /**
     * The case plex_sets exists for: a user who imports films but not collection
     * artwork has no poster row for the collection, so before this the set could
     * only be described.
     */
    public function testACollectionWithNoImportedPosterIsStillNamed(): void
    {
        $this->film('12', 'Dune', ['400']);
        $this->items()->rememberSetName('400', 'Villeneuve');

        $body = (string) $this->get($this->app(), '/library/all?set=400')->getBody();

        self::assertStringContainsString('in Villeneuve', $body);
        self::assertStringNotContainsString('in this set', $body);
    }

    /**
     * And the other direction: with no name known anywhere, the set is described
     * rather than left blank, and its members and clear control are unchanged.
     */
    public function testASetWithNoKnownNameIsDescribed(): void
    {
        $this->film('13', 'Stalker', ['500']);

        $body = (string) $this->get($this->app(), '/library/all?set=500')->getBody();

        self::assertStringContainsString('in this set', $body);
        self::assertStringContainsString('Stalker', $body);
        self::assertStringContainsString('Clear', $body);
    }

    /**
     * An unnamed OTHER set is still offered as a link. The link is the useful
     * part; the name is the courtesy, and dropping the link to avoid an awkward
     * label would hide the thing the reader is looking for.
     */
    public function testAnUnnamedOtherSetIsStillOfferedAsALink(): void
    {
        $this->film('14', 'Arrival', ['600', '700']);
        $this->items()->rememberSetName('600', 'Sci-Fi');

        $body = (string) $this->get(
            $this->app(),
            '/library/all?set=600&from=' . rawurlencode($this->origin('Arrival')),
        )->getBody();

        self::assertStringContainsString('Arrival is also in', $body);
        self::assertStringContainsString('set=700', $body);
        self::assertStringContainsString('another set', $body);
    }

    /**
     * The poster's own row wins over the recorded name, so a set cannot be
     * called one thing in its summary and another on the card that names it.
     */
    public function testAnImportedPostersOwnTitleWinsOverTheRecordedName(): void
    {
        $this->writePng($this->postersDir . '/collections/Trilogy.png');
        $this->items()->upsert(new PlexItemRecord(
            '800',
            'collection',
            'collections',
            'Movies',
            'The Apu Trilogy',
            'Trilogy.png',
            time(),
            setKeys: ['800'],
        ));
        $this->items()->rememberSetName('800', 'Stale Name From An Older Import');

        $body = (string) $this->get($this->app(), '/library/all?set=800')->getBody();

        self::assertStringContainsString('in The Apu Trilogy', $body);
        self::assertStringNotContainsString('Stale Name', $body);
    }

    /**
     * A renamed collection is corrected rather than left under its old name.
     */
    public function testARecordedNameIsUpdatedWhenPlexReportsANewOne(): void
    {
        $this->film('15', 'Persona', ['900']);
        $this->items()->rememberSetName('900', 'Old Name');
        $this->items()->rememberSetName('900', 'New Name');

        $body = (string) $this->get($this->app(), '/library/all?set=900')->getBody();

        self::assertStringContainsString('in New Name', $body);
        self::assertStringNotContainsString('Old Name', $body);
    }
}
