<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Scalar;

/**
 * The auto-import scheduler's memory: which slot it last completed, and whether
 * a run is in progress right now.
 *
 * Two values rather than one table each, because they are read together on every
 * tick and written by the same run. A tick asks "is a slot outstanding, and is
 * anyone already working on it".
 *
 * The slot is recorded on completion, never on start. A run killed part-way
 * therefore leaves its slot outstanding and is retried at the next tick, which
 * is the behaviour wanted: a half-finished import is not an import.
 */
final class AutoImportScheduleRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * The last slot completed, as the identifier
     * {@see \App\Plex\Import\AutoImportSchedule::slotFor()} produces, or null
     * when nothing has ever completed.
     *
     * Null is ordinary rather than exceptional: it is a fresh install, and it is
     * also a deleted database. Both mean the same thing to the scheduler — the
     * current slot is outstanding.
     */
    public function lastCompletedSlot(): ?int
    {
        $row = $this->row();
        if ($row === null) {
            return null;
        }

        return Scalar::intOrNull($row['last_slot'] ?? null);
    }

    /**
     * Record a slot as completed.
     */
    public function recordCompleted(int $slot): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO auto_import_schedule (id, last_slot, running_since)
             VALUES (1, :slot, NULL)
             ON CONFLICT(id) DO UPDATE SET last_slot = excluded.last_slot'
        );

        $stmt->execute([':slot' => $slot]);
    }

    /**
     * When the run in progress started, or null when none is.
     */
    public function runningSince(): ?int
    {
        $row = $this->row();
        if ($row === null) {
            return null;
        }

        return Scalar::intOrNull($row['running_since'] ?? null);
    }

    /**
     * Mark a run as started.
     */
    public function markRunning(int $at): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO auto_import_schedule (id, last_slot, running_since)
             VALUES (1, NULL, :at)
             ON CONFLICT(id) DO UPDATE SET running_since = excluded.running_since'
        );

        $stmt->execute([':at' => $at]);
    }

    /**
     * Mark the run as finished, whether it succeeded or not.
     *
     * Releasing on failure as well as on success is deliberate: a failed import
     * must not leave the scheduler believing a run is still in progress, or one
     * unreachable Plex server would stop auto-import until the container was
     * restarted.
     */
    public function clearRunning(): void
    {
        $this->database->pdo()->exec('UPDATE auto_import_schedule SET running_since = NULL WHERE id = 1');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function row(): ?array
    {
        $stmt = $this->database->pdo()->query(
            'SELECT last_slot, running_since FROM auto_import_schedule WHERE id = 1'
        );
        $row = $stmt === false ? false : $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
