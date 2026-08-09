<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plex;

use App\Plex\Poster\PlexPosterList;
use App\Plex\Poster\PlexPosterOrigin;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Parsing Plex's `/library/metadata/{id}/posters` answer.
 *
 * The fixture below is trimmed from a real response for a movie with forty
 * posters — nine uploads, five other server-held images, and twenty-six remote
 * provider URLs. Every shape Plex used in it is represented here, because the
 * one rule this class enforces (server-held only) is decided entirely by which
 * of those shapes a poster has.
 */
final class PlexPosterListTest extends TestCase
{
    private function list(string $photos): PlexPosterList
    {
        return PlexPosterList::fromXml(new SimpleXMLElement(
            '<MediaContainer>' . $photos . '</MediaContainer>'
        ));
    }

    private function realResponse(): PlexPosterList
    {
        return $this->list(<<<'XML'
            <Photo key="/library/metadata/78657/file?url=metadata%3A%2F%2Fposters%2F0e049fb3"
                   ratingKey="metadata://posters/0e049fb3"
                   thumb="/library/metadata/78657/file?url=metadata%3A%2F%2Fposters%2F0e049fb3"
                   selected="0" provider="local" />
            <Photo key="/library/metadata/78657/file?url=metadata%3A%2F%2Fposters%2Ftv%2Eplex%2Eagents%2Emovie_8a69ac86"
                   ratingKey="metadata://posters/tv.plex.agents.movie_8a69ac86"
                   thumb="/library/metadata/78657/file?url=metadata%3A%2F%2Fposters%2Ftv%2Eplex%2Eagents%2Emovie_8a69ac86"
                   selected="0" provider="tmdb" />
            <Photo key="https://image.tmdb.org/t/p/original/2Y44Ncb.jpg"
                   ratingKey="https://image.tmdb.org/t/p/original/2Y44Ncb.jpg"
                   thumb="https://images.plex.tv/photo?url=https%3A%2F%2Fimage%2Etmdb%2Eorg"
                   selected="0" provider="tmdb" />
            <Photo key="https://assets.fanart.tv/fanart/the-burbs-521caeee.jpg"
                   ratingKey="https://assets.fanart.tv/fanart/the-burbs-521caeee.jpg"
                   thumb="https://assets.fanart.tv/preview/the-burbs-521caeee.jpg"
                   selected="0" provider="fanarttv" />
            <Photo key="https://artworks.thetvdb.com/banners/movies/5869/posters/5869.jpg"
                   ratingKey="https://artworks.thetvdb.com/banners/movies/5869/posters/5869.jpg"
                   thumb="https://artworks.thetvdb.com/banners/movies/5869/posters/5869_t.jpg"
                   selected="0" provider="tvdb" />
            <Photo key="/library/metadata/78657/file?url=upload%3A%2F%2Fposters%2F5df82737"
                   ratingKey="upload://posters/5df82737"
                   thumb="/library/metadata/78657/file?url=upload%3A%2F%2Fposters%2F5df82737"
                   selected="0" />
            <Photo key="/library/metadata/78657/file?url=upload%3A%2F%2Fposters%2Ff2e5bb9a"
                   ratingKey="upload://posters/f2e5bb9a"
                   thumb="/library/metadata/78657/file?url=upload%3A%2F%2Fposters%2Ff2e5bb9a"
                   selected="1" />
            <Photo key="/library/metadata/78657/file?url=media%3A%2F%2F9%2F51f87476%2Ebundle%2FContents%2FThumbnails%2Fthumb1%2Ejpg"
                   ratingKey="media://9/51f87476.bundle/Contents/Thumbnails/thumb1.jpg"
                   thumb="/library/metadata/78657/file?url=media%3A%2F%2F9%2F51f87476%2Ebundle%2FContents%2FThumbnails%2Fthumb1%2Ejpg"
                   selected="0" />
            XML);
    }

    public function testClassifiesEveryPosterPlexReports(): void
    {
        $list = $this->realResponse();

        self::assertCount(8, $list->candidates);
        self::assertCount(2, $list->uploaded());
        self::assertCount(3, $list->server());
        self::assertCount(3, $list->offered());
    }

    /**
     * The property that classifies a poster as held is the same one the image
     * proxy enforces, so a held candidate is by construction one the proxy can
     * serve — and an offered one is by construction one it would refuse.
     */
    public function testHeldCandidatesAreProxyableAndOfferedOnesAreNot(): void
    {
        $signer = new \App\Plex\SignedImagePath('secret');
        $list = $this->realResponse();

        foreach (array_merge($list->uploaded(), $list->server()) as $candidate) {
            self::assertStringStartsWith('/', $candidate->path);
            self::assertStringStartsWith('/', $candidate->thumbPath);
            self::assertSame($candidate->path, $signer->pathFor($signer->sign($candidate->path)));
        }

        foreach ($list->offered() as $candidate) {
            self::assertStringStartsWith('https://', $candidate->path);
            self::assertNull($signer->pathFor($signer->sign($candidate->path)));
        }
    }

    /**
     * An offered candidate goes into the page as an image address and is applied
     * as one, so anything that is not an ordinary web URL is dropped rather than
     * trusted.
     */
    public function testDropsAnOfferedCandidateThatIsNotAWebUrl(): void
    {
        $list = $this->list(
            '<Photo key="javascript:alert(1)" ratingKey="javascript:alert(1)"'
            . ' thumb="javascript:alert(1)" selected="0" />'
            . '<Photo key="ftp://host/p.jpg" ratingKey="ftp://host/p.jpg" thumb="ftp://host/p.jpg" selected="0" />'
        );

        self::assertTrue($list->isEmpty());
    }

    /**
     * Offered artwork keeps its smaller preview — fanart.tv and TheTVDB supply
     * real ones, and Plex proxies TMDB's through its own resizer.
     */
    public function testOfferedCandidatesKeepTheirRemotePreview(): void
    {
        $offered = $this->realResponse()->offered();

        self::assertSame('https://assets.fanart.tv/preview/the-burbs-521caeee.jpg', $offered[1]->thumbPath);
        self::assertSame('https://assets.fanart.tv/fanart/the-burbs-521caeee.jpg', $offered[1]->path);
    }

    public function testUploadsAreRecognisedByTheirRatingKey(): void
    {
        $uploaded = $this->realResponse()->uploaded();

        self::assertCount(2, $uploaded);
        foreach ($uploaded as $candidate) {
            self::assertSame(PlexPosterOrigin::Uploaded, $candidate->origin);
        }
    }

    /**
     * The second group holds three unlike things — an agent download, a local
     * poster file, and an image embedded in the media. All are "held but not
     * uploaded", which is the only distinction that group draws.
     */
    public function testEverythingElseServerHeldGoesInTheSecondGroup(): void
    {
        foreach ($this->realResponse()->server() as $candidate) {
            self::assertSame(PlexPosterOrigin::Server, $candidate->origin);
            self::assertTrue($candidate->origin->isHeldOnServer());
        }
    }

    /**
     * Applying resolves a signed path back to a candidate, and only a held one
     * can be selected — an offered poster is not on the server to select.
     */
    public function testOnlyHeldCandidatesResolveByPath(): void
    {
        $list = $this->realResponse();

        self::assertNotNull($list->withPath($list->uploaded()[0]->path));
        self::assertNull($list->withPath($list->offered()[0]->path));
    }

    public function testTheSelectedPosterIsMarked(): void
    {
        $selected = array_values(array_filter(
            $this->realResponse()->candidates,
            static fn ($c): bool => $c->selected,
        ));

        self::assertCount(1, $selected);
        self::assertStringContainsString('f2e5bb9a', $selected[0]->path);
    }

    public function testNoSelectionMarksNothing(): void
    {
        $list = $this->list(
            '<Photo key="/a" ratingKey="upload://posters/a" thumb="/a" selected="0" />'
            . '<Photo key="/b" ratingKey="upload://posters/b" thumb="/b" selected="0" />'
        );

        foreach ($list->candidates as $candidate) {
            self::assertFalse($candidate->selected);
        }
    }

    public function testAnItemHoldingNothingStillOffersArtwork(): void
    {
        $list = $this->list(
            '<Photo key="https://image.tmdb.org/x.jpg" ratingKey="https://image.tmdb.org/x.jpg"'
            . ' thumb="https://images.plex.tv/photo?url=x" selected="0" provider="tmdb" />'
        );

        self::assertFalse($list->isEmpty());
        self::assertSame([], $list->uploaded());
        self::assertSame([], $list->server());
        self::assertCount(1, $list->offered());
    }

    public function testAnEmptyContainerIsEmpty(): void
    {
        self::assertTrue($this->list('')->isEmpty());
    }

    /**
     * A remote `thumb` on a server-held poster falls back to the full-resolution
     * path rather than dropping the candidate: it belongs in the list, and the
     * proxy could not serve the remote preview anyway.
     */
    public function testARemoteThumbFallsBackToTheServerPath(): void
    {
        $list = $this->list(
            '<Photo key="/library/metadata/1/file?url=upload%3A%2F%2Fa" ratingKey="upload://posters/a"'
            . ' thumb="https://images.plex.tv/photo?url=x" selected="0" />'
        );

        self::assertSame('/library/metadata/1/file?url=upload%3A%2F%2Fa', $list->candidates[0]->thumbPath);
    }
}
