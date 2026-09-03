<?php

declare(strict_types=1);

/**
 * Measure what one gallery render costs.
 *
 * Rendering a view reads each category's recorded Plex facts — the title shown
 * on the card, the release year beside it, the season number, the related title
 * the Related posters action searches, the sets the poster belongs to, and the
 * Plex "added at" timestamp the date sort needs. How many reads that takes is
 * the number this exists to watch: it should be decided by how many categories
 * the view holds, and by nothing else.
 *
 * Run it inside the container:
 *
 *     docker exec -it <container> php /app/www/bin/bench-gallery.php
 *     docker exec -it <container> php /app/www/bin/bench-gallery.php 20
 *
 * The argument is the number of iterations to average over (default 10).
 *
 * Reads only. It imports nothing, writes nothing, and changes no poster. It
 * drives the real gallery controller rather than a copy of what it does, so it
 * cannot drift from the thing it is measuring.
 *
 * The numbers are wall time on the machine it runs on and are only worth
 * comparing against numbers from the same machine and the same library. Run it
 * before a change and after, and quote both.
 */

use App\Controller\GalleryController;
use App\Database\Database;
use App\Support\Session\ArraySession;
use App\Support\Session\SessionInterface;

use function App\buildContainer;

use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 10));

// An in-memory session: this is not a signed-in browser, and the sort
// preference a real session would carry is not what is being measured.
$container = buildContainer([
    SessionInterface::class => static fn (): SessionInterface => new ArraySession(),
]);

/** @var GalleryController $gallery */
$gallery = $container->get(GalleryController::class);
/** @var Database $database */
$database = $container->get(Database::class);

$out = static fn (string $line = ''): int|false => fwrite(STDOUT, $line . PHP_EOL);

// PDO exposes no statement counter, so counting is done by a statement subclass
// installed for the length of this script. It is not part of the application and
// never reaches an install's request path.
//
// The count is taken where a statement is CREATED rather than where it is
// executed, so that both `prepare()` and `query()` are seen — the repository
// uses each. A statement prepared once and executed twice would count once, and
// nothing in the render path does that.
$counter = new BenchCounter();
$database->pdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BenchStatement::class, [$counter]]);

$sets = $container->get(App\Database\PlexItemRepository::class)->all();
$total = count($sets);
$aSet = '';
foreach ($sets as $row) {
    if ($row->setKeys !== []) {
        $aSet = $row->setKeys[0];
        break;
    }
}

$out(sprintf('Library: %d mapped poster(s). Averaging over %d iteration(s).', $total, $iterations));
if ($aSet === '') {
    $out('No poster records a set; the set view is measured as an empty one.');
}
$out();

/**
 * One measured view: the real controller, rendering to a real response.
 *
 * @param array<string, string> $query
 */
$measure = static function (string $label, string $slug, array $query) use (
    $gallery,
    $counter,
    $iterations,
    $out,
): void {
    $requests = new ServerRequestFactory();
    $responses = new ResponseFactory();

    // One warm-up outside the measurement: the first render pays for Twig
    // compiling its templates, which an install pays once and not per view.
    $request = $requests->createServerRequest('GET', '/library/' . $slug)->withQueryParams($query);
    $gallery->show($request, $responses->createResponse(), ['category' => $slug]);

    $before = $counter->count;
    $started = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $request = $requests->createServerRequest('GET', '/library/' . $slug)->withQueryParams($query);
        $gallery->show($request, $responses->createResponse(), ['category' => $slug]);
    }
    $elapsed = (hrtime(true) - $started) / 1e6;
    $reads = $counter->count - $before;

    $out(sprintf(
        '  %-34s %7.1f ms   %3d read(s)',
        $label,
        $elapsed / $iterations,
        (int) round($reads / $iterations),
    ));
};

$out('Per render:');
$measure('All, unfiltered', 'all', []);
$measure('All, unfiltered, by date added', 'all', ['sort' => 'date_added']);
$measure('All, filtered by a query', 'all', ['q' => 'the']);
$measure('All, showing a set', 'all', ['set' => $aSet]);
$measure('Movies, unfiltered', 'movies', []);
$out();
$out('Reads are queries against the poster mapping. A view should cost one per');
$out('category it holds — four for All, one for a single category — whatever the');
$out('sort order is and whether or not a query or a set is active.');

/**
 * A tally the statement class below writes into.
 */
class BenchCounter
{
    public int $count = 0;
}

/**
 * Counts every statement created on the connection it is installed on.
 */
class BenchStatement extends PDOStatement
{
    protected function __construct(BenchCounter $counter)
    {
        $counter->count++;
    }
}
