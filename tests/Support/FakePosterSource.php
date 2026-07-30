<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Poster\Source\PosterQuery;
use App\Poster\Source\PosterSearchResult;
use App\Poster\Source\PosterSource;

/**
 * A poster source that returns a fixed result and records what it was asked for.
 */
final class FakePosterSource implements PosterSource
{
    public ?PosterQuery $asked = null;

    public function __construct(private readonly PosterSearchResult $result)
    {
    }

    public function find(PosterQuery $query): PosterSearchResult
    {
        $this->asked = $query;

        return $this->result;
    }
}
