<?php

declare(strict_types=1);

namespace App\Tests\Unit\Poster;

use App\Poster\Source\PosterProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The enum ties three facts together — the slug the poster source sends, the
 * name the user reads, and where the section sits — so each is pinned here.
 */
final class PosterProviderTest extends TestCase
{
    /**
     * The case values are the wire format. A typo here does not fail anywhere
     * else: an unrecognised slug is a legal input that lands in the "Other"
     * section, so the provider's real section would simply stop appearing.
     */
    public function testCaseValuesAreTheSlugsTheSourceSends(): void
    {
        self::assertSame('tmdb', PosterProvider::Tmdb->value);
        self::assertSame('thetvdb', PosterProvider::TheTvdb->value);
        self::assertSame('fanart.tv', PosterProvider::Fanart->value);
    }

    /**
     * TheTVDB is labelled "TVDB" on purpose. Section headings are uppercased in
     * CSS, where the full name collapses to "THETVDB" and loses the camel case
     * that makes it readable. The footer's attribution still carries the real
     * brand, as a logo, so no two spellings meet as words on screen.
     */
    public function testEachProviderIsLabelledForTheHeadingItAppearsIn(): void
    {
        self::assertSame('TMDB', PosterProvider::Tmdb->label());
        self::assertSame('TVDB', PosterProvider::TheTvdb->label());
        self::assertSame('fanart.tv', PosterProvider::Fanart->label());
    }

    /**
     * Fixed, and the same for every item — that consistency is the whole point of
     * the feature. It is also the order the provider attribution credits these
     * three in; the two must not drift apart.
     */
    public function testSectionOrderIsFixed(): void
    {
        self::assertSame(
            [PosterProvider::Tmdb, PosterProvider::TheTvdb, PosterProvider::Fanart],
            PosterProvider::inSectionOrder(),
        );
    }

    /**
     * The whole set is covered, so a provider added to the enum cannot be left
     * out of the ordering and silently lose its section.
     */
    public function testEveryProviderHasAPlaceInTheOrder(): void
    {
        self::assertSame(PosterProvider::cases(), PosterProvider::inSectionOrder());
    }

    /**
     * A slug arrives as an arbitrary string off the network, so these go through
     * a provider rather than being written inline — a literal would be narrowed
     * to null before the call and prove nothing about the runtime path.
     *
     * @return list<array{string}>
     */
    public static function unknownSlugs(): array
    {
        return [
            // A provider the service could add tomorrow.
            ['mediux'],
            // Present but empty.
            [''],
            // Right service, wrong case: the match is exact.
            ['TMDB'],
            // The token the service uses internally for TheTVDB. The wire slug is
            // `thetvdb`; matching the label the user reads would be the wrong
            // test and the wrong behaviour.
            ['tvdb'],
        ];
    }

    #[DataProvider('unknownSlugs')]
    public function testAnUnknownSlugIsNotAnError(string $slug): void
    {
        self::assertNull(PosterProvider::tryFrom($slug));
    }
}
