<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * Why a poster search produced the candidates it did.
 *
 * The distinction the old contract lacked: a title that did not resolve, a work
 * that resolved but has no artwork, and a service that could not be reached are
 * three different situations, and only one of them is the user's to act on.
 */
enum PosterSearchOutcome
{
    /** Candidates found, every source healthy. */
    case Ok;

    /** Candidates found, but one or more sources failed — results may be thin. */
    case Partial;

    /** No work matched the title. */
    case NoMatch;

    /** The work matched but has no artwork of its own. */
    case NoArtwork;

    /** Too many searches; the service asked us to wait. */
    case RateLimited;

    /** The service could not be reached, or rejected the request. */
    case Unavailable;

    /**
     * Whether candidates are worth rendering. Partial is a success that also
     * carries a warning, so it belongs here alongside Ok.
     */
    public function hasCandidates(): bool
    {
        return $this === self::Ok || $this === self::Partial;
    }
}
