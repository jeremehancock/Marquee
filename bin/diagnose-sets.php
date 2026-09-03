<?php

declare(strict_types=1);

/**
 * Report what Marquee knows about poster sets, and what Plex reports about
 * collection membership.
 *
 * Related posters shows the set a poster belongs to — a show with its seasons, a
 * film with its collection — and falls back to searching the poster's title when
 * no set is recorded. Both states look the same from the gallery, which makes
 * "it is still searching" impossible to act on from the outside: the film may
 * genuinely belong to no collection, the import may not have recorded anything,
 * or the server may not answer the route membership is read from.
 *
 * This says which. Run it inside the container:
 *
 *     docker exec -it <container> php /app/www/bin/diagnose-sets.php
 *     docker exec -it <container> php /app/www/bin/diagnose-sets.php "Jackass"
 *
 * With an argument it also lists the stored posters whose title contains it,
 * with the set each records.
 *
 * Reads only. It imports nothing, writes nothing, and changes no poster.
 */

use App\Database\PlexItemRepository;
use App\Plex\PlexClient;

use function App\buildContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = buildContainer();

/** @var PlexClient $plex */
$plex = $container->get(PlexClient::class);
/** @var PlexItemRepository $items */
$items = $container->get(PlexItemRepository::class);

$out = static fn (string $line = ''): int|false => fwrite(STDOUT, $line . PHP_EOL);

$out('== What Plex reports ==');

// A server that cannot be reached is worth reporting, but it must not stop the
// second half: what is already recorded is the more direct answer to "why is
// this poster still searching", and it needs no server at all.
$libraries = [];
if (!$plex->isConfigured()) {
    $out('  Plex is not configured.');
} else {
    try {
        $libraries = $plex->libraries();
    } catch (Throwable $e) {
        $out('  Could not list libraries: ' . $e->getMessage());
    }
}

foreach ($libraries as $library) {
    $out(sprintf('  %s (%s)', $library->title, $library->type));

    if (!$library->isMovie()) {
        $out('    a show library — its sets come from the shows themselves, not from collections');
        continue;
    }

    try {
        $collections = $plex->collections($library);
    } catch (Throwable $e) {
        $out('    could not list collections: ' . $e->getMessage());
        continue;
    }

    if ($collections === []) {
        $out('    no collections — every film here will fall back to a title search');
        continue;
    }

    $out(sprintf('    %d collection(s)', count($collections)));
    foreach ($collections as $collection) {
        try {
            $members = $plex->collectionChildren($collection);
        } catch (Throwable $e) {
            $out(sprintf('      %-6s %-40s FAILED: %s', $collection->ratingKey, $collection->title, $e->getMessage()));
            continue;
        }

        $out(sprintf(
            '      %-6s %-40s %d member(s)%s',
            $collection->ratingKey,
            $collection->title,
            count($members),
            $members === [] ? '   <-- Plex answered but listed nothing' : '',
        ));
        foreach (array_slice($members, 0, 5) as $member) {
            $out(sprintf('               %-6s %s', $member->ratingKey, $member->title));
        }
        if (count($members) > 5) {
            $out(sprintf('               ... and %d more', count($members) - 5));
        }
    }
}

$out();
$out('== What Marquee has recorded ==');

$rows = $items->all();
$withSet = array_filter($rows, static fn ($row): bool => $row->setKeys !== []);
$out(sprintf('  %d of %d stored posters record a set.', count($withSet), count($rows)));

if (count($withSet) === 0 && count($rows) > 0) {
    $out('  None at all. Run an ordinary import (no re-download) and try again;');
    $out('  if it is still none, the membership read above is what to look at.');
}

$out();
$out('== The shape of this library ==');
$out('  What the ordering and "also in" rules were designed against. Reads the');
$out('  recorded rows only; no server is needed for this section.');
$out();

// Release order sorts on `year`, and on `season_number` within a year. How often
// each is absent decides whether "unknown first" is a corner or a whole block,
// and whether a show's seasons really do tie with the show on year.
$categories = [];
foreach ($rows as $row) {
    $categories[$row->category][] = $row;
}
ksort($categories);

$out('  Recorded release year, by category:');
foreach ($categories as $category => $inCategory) {
    $withoutYear = array_filter($inCategory, static fn ($row): bool => $row->year === null);
    $out(sprintf(
        '    %-14s %4d row(s), %4d with no year (%d%%)',
        $category,
        count($inCategory),
        count($withoutYear),
        count($inCategory) === 0 ? 0 : (int) round(100 * count($withoutYear) / count($inCategory)),
    ));
}

// The one that cannot be checked from outside a real server: whether Plex
// reports a year on a collection at all. Unknown-first is chosen to be correct
// either way, so this confirms a design assumption rather than deciding one.
$collectionRows = $categories['collections'] ?? [];
$datedCollections = array_filter($collectionRows, static fn ($row): bool => $row->year !== null);
$out(sprintf(
    '    -> %d of %d collections carry a year%s',
    count($datedCollections),
    count($collectionRows),
    $collectionRows === []
        ? ''
        : (count($datedCollections) === 0
            ? '; a collection sorts ahead of its films on "unknown first"'
            : '; a collection sorts among its films by that year'),
));

$seasons = $categories['tv-seasons'] ?? [];
$numberless = array_filter($seasons, static fn ($row): bool => $row->seasonNumber === null);
$out(sprintf(
    '  Seasons with no recorded season number: %d of %d%s',
    count($numberless),
    count($seasons),
    $numberless === [] ? '' : '   <-- these tie with their show rather than following it',
));

// The "also in" line names the sets ONE poster belongs to. This is the number
// that says whether that list stays short on a real library.
$bySetCount = [];
foreach ($rows as $row) {
    $bySetCount[count($row->setKeys)] = ($bySetCount[count($row->setKeys)] ?? 0) + 1;
}
ksort($bySetCount);
$out('  Sets recorded per poster:');
foreach ($bySetCount as $count => $howMany) {
    $out(sprintf(
        '    in %d set(s):  %4d poster(s)%s',
        $count,
        $howMany,
        $count === 0 ? '   (falls back to a title search)' : '',
    ));
}
$most = max(array_keys($bySetCount) ?: [0]);
if ($most > 1) {
    $out(sprintf('    -> the longest "also in" line names %d other set(s)', $most - 1));
}

// A set can be named from the naming item's own poster row. Where that poster
// was never imported, the set reads "this set" until its name is recorded
// separately — which is the case the plex_sets table exists for.
$recordedKeys = [];
foreach ($rows as $row) {
    $recordedKeys[$row->ratingKey] = true;
}
$allSets = [];
foreach ($rows as $row) {
    foreach ($row->setKeys as $key) {
        $allSets[$key] = true;
    }
}
$nameless = array_filter(array_keys($allSets), static fn (string $key): bool => !isset($recordedKeys[$key]));
$out(sprintf(
    '  Sets with no naming poster imported: %d of %d%s',
    count($nameless),
    count($allSets),
    $nameless === [] ? '' : '   <-- these read "this set" until names are recorded',
));

$needle = $argv[1] ?? '';
if ($needle === '') {
    $out();
    $out('  Pass a title fragment to list individual posters, e.g.:');
    $out('      php bin/diagnose-sets.php "Jackass"');
    exit(0);
}

$out();
$out(sprintf('== Stored posters matching "%s" ==', $needle));

$matches = array_filter(
    $rows,
    static fn ($row): bool => stripos($row->title, $needle) !== false,
);

if ($matches === []) {
    $out('  none');
    exit(0);
}

foreach ($matches as $row) {
    $out(sprintf(
        '  %-6s %-14s %-40s set=%s',
        $row->ratingKey,
        $row->category,
        $row->title,
        $row->setKeys === [] ? "-  <-- falls back to a title search" : implode(', ', $row->setKeys),
    ));
}

$sets = [];
foreach ($matches as $row) {
    foreach ($row->setKeys as $key) {
        $sets[$key] = true;
    }
}

foreach (array_keys($sets) as $key) {
    $inSet = array_filter($rows, static fn ($row): bool => in_array($key, $row->setKeys, true));
    $out();
    $out(sprintf('  Set %s holds %d poster(s):', $key, count($inSet)));
    foreach ($inSet as $row) {
        $out(sprintf('    %-14s %s', $row->category, $row->title));
    }
}
