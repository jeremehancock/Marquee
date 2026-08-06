<?php

declare(strict_types=1);

use App\Plex\Import\AutoImportService;
use App\Plex\PlexFailureMessage;

use function App\buildContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = buildContainer();
$service = $container->get(AutoImportService::class);

try {
    $result = $service->run();
    fwrite(STDOUT, ($result?->summary() ?? 'Auto-import skipped.') . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    // Same source-aware remedy the interface gives. This log is where an
    // operator looks when scheduled imports stop working, so "check PLEX_TOKEN"
    // has to be wrong here too when the token came from signing in.
    $message = $container->get(PlexFailureMessage::class);
    fwrite(STDERR, 'Auto-import failed: ' . $message->for($e) . PHP_EOL);
    exit(1);
}
