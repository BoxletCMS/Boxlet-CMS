<?php

namespace App\Modules\Update;

use App\Core\Db;
use App\Core\Migrator;
use RuntimeException;
use Throwable;

/**
 * Updating an existing install (PLAN.md D-019).
 *
 * New code can carry migrations the database has not applied. Until the owner presses
 * the button, the admin shows one screen and the public site answers 503: a visitor's
 * request never runs a migration, and nothing runs on its own.
 *
 * DETECTION IS CHEAP BY DEFAULT. Every public request would otherwise pay a directory
 * scan plus a query to learn that nothing has changed. Instead a marker in storage
 * records the newest migration filename and how many there are, and a request that finds
 * the marker matching the directory asks the database nothing at all.
 *
 * Both halves are needed. The filename alone misses a file back-ported below the newest
 * one, which is how a release that fixes an older migration arrives; the count alone
 * misses a rename. Together they change whenever the set does.
 */
final class Update
{
    private const MARKER = 'migrations.state';
    private const LOCK = 'update.lock';

    /**
     * @param string|null $sqlitePath the database file to copy before running; null on
     *                                MySQL, where a backup is the host's job until
     *                                Slice 8
     */
    public function __construct(
        private readonly Db $db,
        private readonly string $migrationsPath,
        private readonly string $storagePath,
        private readonly ?string $sqlitePath = null,
    ) {
    }

    /**
     * The files waiting to be applied, in filename order; empty when up to date.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        if ($this->marker() === $this->readMarker()) {
            return [];
        }

        $pending = (new Migrator($this->db, $this->migrationsPath))->pending();
        if ($pending === []) {
            // Up to date, and now recorded as such: the next request skips the query.
            $this->writeMarker();
        }

        return $pending;
    }

    /**
     * Applies every pending migration.
     *
     * @return list<string> the files applied
     * @throws RuntimeException naming the file that failed
     */
    public function run(): array
    {
        $lock = $this->storagePath . '/' . self::LOCK;
        // Atomic. 'x' creates the file only if it does not exist, in one operation, so two
        // requests cannot both find it absent and both run — which is exactly what asking
        // is_file() first would allow.
        //
        // Not @-suppressed: both the front controller and the test runner install error
        // handlers that turn warnings into ErrorException, so the suppression does nothing
        // and the stream message would escape instead of the sentence written for this.
        // Caught here and named properly.
        try {
            $handle = fopen($lock, 'x');
        } catch (Throwable) {
            throw new RuntimeException(t('update.locked'));
        }
        if ($handle === false) {
            throw new RuntimeException(t('update.locked'));
        }
        fwrite($handle, gmdate('Y-m-d H:i:s'));

        try {
            $this->backup();
            $applied = (new Migrator($this->db, $this->migrationsPath))->migrate();


            // Only a run that finished may record the directory as seen. In the finally
            // below it recorded it whatever happened, so a failed migration left the
            // site reporting itself up to date: the gate lifted, the public site came
            // back, and the file that failed was forgotten until someone added another.
            $this->writeMarker();

            return $applied;
        } finally {
            // Released whatever happened, so a failure the owner fixes can be retried
            // without deleting a file by hand. Files already applied stay recorded, so a
            // retry resumes rather than repeats.
            fclose($handle);
            @unlink($lock);
        }
    }

    /**
     * Copies a SQLite database into storage/backups/ before anything runs.
     *
     * MySQL gets no copy here: a real backup needs the host's tools and arrives with
     * Slice 8, so the screen tells the owner to take one first. Doing it badly — a
     * mysqldump shelled out from PHP on shared hosting — would be a backup nobody
     * should trust.
     */
    public function backup(): ?string
    {
        if ($this->db->driver !== 'sqlite') {
            return null;
        }

        if ($this->sqlitePath === null || !is_file($this->sqlitePath)) {
            return null;
        }
        $source = $this->sqlitePath;

        $directory = $this->storagePath . '/backups';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(t('update.backup_failed'));
        }

        $target = $directory . '/' . gmdate('Ymd-His') . '-' . basename($source);
        if (!@copy($source, $target)) {
            throw new RuntimeException(t('update.backup_failed'));
        }

        return $target;
    }

    /**
     * What the migrations directory looks like right now: the newest filename and how
     * many files there are.
     */
    private function marker(): string
    {
        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files);
        $newest = $files === [] ? '' : basename((string) end($files));

        return count($files) . ':' . $newest;
    }

    private function readMarker(): string
    {
        $file = $this->storagePath . '/' . self::MARKER;

        return is_file($file) ? trim((string) @file_get_contents($file)) : '';
    }

    private function writeMarker(): void
    {
        try {
            @file_put_contents($this->storagePath . '/' . self::MARKER, $this->marker());
        } catch (Throwable) {
            // An unwritable storage directory is the installer's problem to report, not
            // a reason to fail an update that has already succeeded. Without the marker
            // the next request simply asks the database again.
        }
    }
}
