<?php

declare(strict_types=1);

namespace App\Plex\Import;

/**
 * Which collections each movie in a library belongs to, and whether that answer
 * can be trusted to be complete.
 *
 * The second half is the point. A movie that belongs to no collection and a
 * movie whose collections could not be read both produce an empty list, and the
 * right response to each is the opposite of the other:
 *
 *   - taken off a collection in Plex  ->  its set must be removed, or the film
 *                                         stays in a collection it has left
 *   - the read failed                 ->  its set must be kept, or one failed
 *                                         request empties every film's set
 *
 * So a read that could not list a collection reports itself incomplete, and the
 * caller keeps what it already had. Only a complete read is allowed to remove a
 * membership.
 *
 * This distinction was missing at first, under the rule the other recorded facts
 * follow — a release year Plex has momentarily stopped reporting is better held
 * stale than lost. Membership is not that kind of fact. A year is a property of
 * the work that Plex may fail to mention; a collection is a relationship a user
 * removes on purpose, and "no longer in one" is something Plex says by omission
 * rather than by reporting anything.
 */
final class CollectionMembership
{
    /**
     * @param array<string, list<string>> $byMovie   collection rating keys, keyed
     *                                               by the movie's rating key
     * @param bool                        $complete  whether every collection in
     *                                               the library was listed
     */
    private function __construct(
        private readonly array $byMovie,
        private readonly bool $complete,
    ) {
    }

    /**
     * @param array<string, list<string>> $byMovie
     */
    public static function complete(array $byMovie): self
    {
        return new self($byMovie, true);
    }

    /**
     * A read that could not list at least one collection. What it did gather is
     * still worth recording — a film it found in a collection is in one — but it
     * may not be used to conclude that a film is in none.
     *
     * @param array<string, list<string>> $byMovie
     */
    public static function partial(array $byMovie): self
    {
        return new self($byMovie, false);
    }

    /**
     * The sets to record for a movie, or null when nothing may be concluded and
     * whatever is already recorded should stand.
     *
     * An empty list is a real answer — "in no collection" — and only a complete
     * read can give one.
     *
     * @return list<string>|null
     */
    public function setsFor(string $ratingKey): ?array
    {
        $sets = $this->byMovie[$ratingKey] ?? [];

        if ($sets !== []) {
            return $sets;
        }

        return $this->complete ? [] : null;
    }
}
