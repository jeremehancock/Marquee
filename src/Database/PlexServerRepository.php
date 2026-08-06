<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Scalar;

/**
 * Caches the connected Plex server's friendly name.
 *
 * The name is what the application shows to describe the connection, and it is
 * read from here on every page render. Caching it is what keeps that promise
 * cheap: contacting Plex to render a page would stall every page for the
 * connect timeout whenever the server is down, which is precisely when the
 * interface most needs to stay responsive.
 *
 * The cached name is therefore the configured connection, not a claim that Plex
 * is reachable right now. It goes stale only when a server is renamed, and is
 * refreshed whenever the connection panel is opened.
 */
final class PlexServerRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function name(): ?string
    {
        $stmt = $this->database->pdo()->query('SELECT friendly_name FROM plex_server WHERE id = 1');
        $row = $stmt === false ? false : $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $name = Scalar::string($row['friendly_name'] ?? '');

        return $name !== '' ? $name : null;
    }

    public function remember(string $name): void
    {
        if ($name === '') {
            return;
        }

        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO plex_server (id, friendly_name, updated_at)
             VALUES (1, :name, :updated_at)
             ON CONFLICT(id) DO UPDATE SET
                friendly_name = excluded.friendly_name,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([':name' => $name, ':updated_at' => time()]);
    }

    /**
     * Forget the cached name, so a disconnected Marquee stops naming a server
     * it is no longer talking to.
     */
    public function forget(): void
    {
        $this->database->pdo()->exec('DELETE FROM plex_server');
    }
}
