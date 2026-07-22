<?php

declare(strict_types=1);

use App\Plex\Import\AutoImportService;

use function App\buildContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

$service = buildContainer()->get(AutoImportService::class);

try {
    $result = $service->run();
    fwrite(STDOUT, ($result?->summary() ?? 'Auto-import skipped.') . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Auto-import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
