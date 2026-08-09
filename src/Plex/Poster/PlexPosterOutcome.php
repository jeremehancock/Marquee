<?php

declare(strict_types=1);

namespace App\Plex\Poster;

/**
 * Why listing an item's Plex-held posters produced what it did.
 *
 * Three cases, where a title search has six. A rating key either resolves or
 * the server cannot be reached: there is no matching step, so there is no
 * no-match, no rate limit, and no identifier to correct. Reporting outcomes a
 * source cannot produce is how a user learns to distrust the messages, so this
 * enum stays as small as the source actually is — see
 * {@see \App\Poster\Source\PosterSearchOutcome} for the other one, deliberately
 * kept separate.
 */
enum PlexPosterOutcome
{
    /** Candidates found. */
    case Ok;

    /** Plex answered, but holds no posters of its own for this item. */
    case None;

    /** The poster has no linked Plex item, so there is nothing to ask about. */
    case NotLinked;

    /** Plex could not be reached, or rejected the request. */
    case Unavailable;
}
