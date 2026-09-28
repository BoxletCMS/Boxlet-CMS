<?php

namespace App\Modules\Update;

use RuntimeException;

/**
 * Maintenance mode (PLAN.md D-021).
 *
 * One mechanism, two triggers: this flag, and a pending migration (D-019). Both show the
 * same page, so there is no second "site is down" path to keep in step.
 *
 * The state is a file rather than a settings row on purpose. Maintenance is exactly the
 * situation where the database may be unavailable, mid-update, or about to be replaced
 * by a ZIP upload (Slice 8) — a flag that needs a working database to be read is a flag
 * that fails when it is needed.
 *
 * The file records WHY it is on. Slice 8 will switch it on as 'update' and off again when
 * the upload finishes, and must not switch off a 'manual' one the owner set on purpose
 * and is still working behind.
 */
final class Maintenance
{
    private const FILE = 'maintenance.flag';

    /** Set by the owner from the dashboard; only the owner clears it. */
    public const MANUAL = 'manual';

    /** Set around an update by ZIP upload (Slice 8); cleared when that finishes. */
    public const UPDATE = 'update';

    public function __construct(private readonly string $storagePath)
    {
    }

    public function isOn(): bool
    {
        return is_file($this->path());
    }

    /**
     * Why it is on: 'manual', 'update', or null when it is off. An unreadable or empty
     * file counts as manual — it is on, and the safe reading of "who may turn it off" is
     * the one that waits for a person.
     */
    public function reason(): ?string
    {
        if (!$this->isOn()) {
            return null;
        }
        $reason = trim((string) @file_get_contents($this->path()));

        return $reason === self::UPDATE ? self::UPDATE : self::MANUAL;
    }

    /**
     * @throws RuntimeException when the flag cannot be written
     */
    public function turnOn(string $reason = self::MANUAL): void
    {
        // Not suppressed. A flag that silently fails to write leaves the owner believing
        // their site is closed while it is open to everyone — the worst possible outcome
        // for this feature, and invisible. An unwritable storage directory is worth an
        // error the owner can see and fix.
        if (@file_put_contents($this->path(), $reason === self::UPDATE ? self::UPDATE : self::MANUAL) === false) {
            throw new RuntimeException(t('maintenance.failed'));
        }
    }

    /**
     * Switches it off.
     *
     * $only limits this to a flag set for that reason: Slice 8 finishing an upload passes
     * 'update', so it cannot clear a manual flag the owner set and is still working
     * behind. The owner's own toggle passes nothing and clears whatever is there.
     */
    /**
     * @throws RuntimeException when the flag exists and cannot be removed
     */
    public function turnOff(?string $only = null): void
    {
        if ($only !== null && $this->reason() !== $only) {
            return;
        }
        if (!$this->isOn()) {
            return;
        }
        // Not suppressed, for the mirror of the reason turnOn() is not: a flag that fails
        // to clear leaves the site closed to everyone while the dashboard says it is open.
        // The owner would be looking at a working admin and an unreachable site.
        if (!@unlink($this->path())) {
            throw new RuntimeException(t('maintenance.failed'));
        }
    }

    private function path(): string
    {
        return $this->storagePath . '/' . self::FILE;
    }
}
