<?php

declare(strict_types=1);

namespace App\Plex\Import;

use App\Config\AutoImportConfig;
use App\Plex\PlexClient;
use Psr\Log\LoggerInterface;

/**
 * Runs one unattended import of the configured media types across every Plex
 * library the client reports — excluded libraries are already filtered out
 * there. Scheduling is handled by the container.
 */
final class AutoImportService
{
    public function __construct(
        private readonly PlexClient $plex,
        private readonly ImportService $import,
        private readonly AutoImportConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(): ?ImportResult
    {
        if (!$this->config->enabled) {
            $this->logger->info('Auto-import is disabled; skipping.');

            return null;
        }

        if (!$this->plex->isConfigured()) {
            $this->logger->warning('Auto-import skipped: Plex is not configured.');

            return null;
        }

        $mediaTypes = $this->config->mediaTypes();
        if ($mediaTypes === []) {
            $this->logger->info('Auto-import skipped: no media types are enabled.');

            return null;
        }

        // libraries() already omits excluded libraries, so an empty list here
        // means there is genuinely nothing to import.
        $sectionKeys = [];
        foreach ($this->plex->libraries() as $library) {
            $sectionKeys[] = $library->key;
        }

        if ($sectionKeys === []) {
            $this->logger->info('Auto-import skipped: no libraries to import.');

            return null;
        }

        $result = $this->import->import($sectionKeys, $mediaTypes);
        $this->logger->info('Auto-import complete. ' . $result->summary());

        return $result;
    }
}
