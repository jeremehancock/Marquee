<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;

/**
 * A shape tripwire for the one asymmetry this change exists to remove, with the
 * same standing as {@see TabSwipeTest}: there is no browser in CI, so what is
 * caught here is a later edit quietly undoing the arrangement rather than the
 * behaviour itself.
 *
 * The asymmetry was this. A query lives in the search input, which every
 * navigation path reads, so it survived a tab tap and a sort press without
 * anyone arranging for it to. A set lived only in whatever the caller passed to
 * categoryUrl(), and the tab tap passed nothing — so Related posters kept its
 * result when it fell back to a title search and lost it when it found a set.
 * One action, two behaviours, decided by something the user cannot see.
 *
 * The fix is that the set is read from the ADDRESS, which every path already
 * updates. These assertions pin that, and pin the three places a set has to be
 * carried, dropped, or compared.
 */
final class SetPersistenceTest extends TestCase
{
    private function gallerySource(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/gallery.js';
        $source = file_get_contents($path);
        self::assertIsString($source, 'gallery.js must be readable at ' . $path);

        // Comments stripped, and not as a tidiness measure: gallery.js explains
        // itself at length, and several of these assertions name the very things
        // its comments discuss — "the a[data-sort] branch", "categoryUrl()".
        // Matched against the raw file they would find the prose about the code
        // rather than the code, and pass or fail on how it is described.
        $lines = preg_replace('#^\s*//.*$#m', '', $source);
        self::assertIsString($lines);

        return $lines;
    }

    /**
     * The address is the record. Holding the set in a variable instead would be
     * a second copy to keep in step with the back button, the swipe, paging and
     * every no-reload update — which is the bug this replaced, in a new place.
     */
    public function testTheActiveSetIsReadFromTheAddress(): void
    {
        $source = $this->gallerySource();

        self::assertMatchesRegularExpression(
            '/function activeSet\(\)\s*\{[^}]*window\.location\.search/s',
            $source,
            'activeSet() must read the live address, not a held copy',
        );
    }

    /**
     * Carried by the routine every category change goes through, so the tab tap
     * and the swipe's commit both get it without either being taught.
     */
    public function testCategoryUrlCarriesTheActiveSet(): void
    {
        $source = $this->gallerySource();

        self::assertMatchesRegularExpression(
            '/function categoryUrl\([^)]*\)\s*\{[^}]*activeSet\(\)/s',
            $source,
            'categoryUrl() must fall back to the address when no set is passed',
        );
    }

    /**
     * The sort control was an accidental way out of a set: it rebuilt the
     * address from the pathname, the sort and the query, and a set carried by
     * none of those simply vanished.
     */
    public function testTheSortLinkCarriesTheActiveSet(): void
    {
        $source = $this->gallerySource();

        $branch = $this->sliceAfter($source, 'a[data-sort]');
        self::assertStringContainsString('activeSet()', $branch, 'the sort branch must carry the set');
        self::assertStringContainsString(
            "'&set='",
            $branch,
            'the set must reach the address the sort control navigates to',
        );
    }

    /**
     * A set and a query are alternatives the server never applies together, so
     * the sort link must carry exactly one. Carrying both would send a request
     * whose two halves disagree about what is being shown.
     */
    public function testTheSortLinkCarriesTheSetOrTheQueryButNotBoth(): void
    {
        $branch = $this->sliceAfter($this->gallerySource(), 'a[data-sort]');

        self::assertMatchesRegularExpression(
            '/sortSet\s*\?.*?:\s*\(?sortQ\s*\?/s',
            $branch,
            'the set must be chosen INSTEAD OF the query, not alongside it',
        );
    }

    /**
     * The one gesture that must drop a set rather than carry it. Typing is a new
     * intent; a query does not narrow a set, it replaces it. This is why the
     * live-search path builds its address itself instead of going through
     * categoryUrl().
     */
    public function testTypingAQueryDropsTheSet(): void
    {
        $source = $this->gallerySource();

        $handler = $this->sliceAfter($source, "search.addEventListener('input'");
        self::assertStringNotContainsString(
            'categoryUrl(',
            $handler,
            'the search handler must not carry the set forward',
        );
        self::assertStringContainsString("'?q='", $handler);
    }

    /**
     * The held neighbour used by the swipe. Without the set in the comparison,
     * opening a set and swiping shows a copy fetched before it was opened — the
     * whole unfiltered category, with nothing on screen to say so.
     */
    public function testTheNeighbourCacheComparesTheActiveSet(): void
    {
        $source = $this->gallerySource();

        self::assertMatchesRegularExpression(
            '/function cachedView\([^)]*\)\s*\{.*?held\.set !== activeSet\(\)/s',
            $source,
            'a copy held under a different set must be discarded',
        );
    }

    /**
     * And the other half: a copy is stamped with the set it was fetched under.
     * A comparison against a field nothing writes would always discard, which
     * looks like it works and quietly turns the cache off.
     */
    public function testHeldCopiesAreStampedWithTheSetTheyWereFetchedUnder(): void
    {
        $source = $this->gallerySource();

        // Both places a copy enters the cache: the idle prefetch and the
        // mid-gesture fetch a miss triggers.
        self::assertSame(
            2,
            preg_match_all('/neighbourCache\[path\] = \{[^}]*set:/s', $source),
            'every write to the neighbour cache must record the set',
        );
    }

    private function sliceAfter(string $source, string $needle): string
    {
        $at = strpos($source, $needle);
        self::assertIsInt($at, 'gallery.js must still contain ' . $needle);

        return substr($source, $at, 1200);
    }
}
