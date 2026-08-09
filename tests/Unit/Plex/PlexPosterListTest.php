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

    public function testKeepsOnlyServerHeldPosters(): void
    {
        // Eight in, three remote dropped.
        self::assertCount(5, $this->realResponse()->candidates);
    }

    public function testDropsRemoteProviderArtwork(): void
    {
        foreach ($this->realResponse()->candidates as $candidate) {
            self::assertStringStartsWith('/', $candidate->path);
            self::assertStringStartsWith('/', $candidate->thumbPath);
        }
    }

    /**
     * The property the filter tests is the same one the image proxy enforces, so
     * a candidate that survives here is by construction one the proxy can serve.
     */
    public function testEveryKeptCandidateIsProxyable(): void
    {
        $signer = new \App\Plex\SignedImagePath('secret');

        foreach ($this->realResponse()->candidates as $candidate) {
            self::assertSame($candidate->path, $signer->pathFor($signer->sign($candidate->path)));
        }
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
     * poster file, and an image embedded in the media. All are "on the server
     * but not uploaded", which is the only distinction the tab draws.
     */
    public function testEverythingElseServerHeldGoesInTheSecondGroup(): void
    {
        self::assertCount(3, $this->realResponse()->server());
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

    public function testAnItemOfferingOnlyRemoteArtworkReadsAsEmpty(): void
    {
        $list = $this->list(
            '<Photo key="https://image.tmdb.org/x.jpg" ratingKey="https://image.tmdb.org/x.jpg"'
            . ' thumb="https://images.plex.tv/photo?url=x" selected="0" provider="tmdb" />'
        );

        self::assertTrue($list->isEmpty());
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
