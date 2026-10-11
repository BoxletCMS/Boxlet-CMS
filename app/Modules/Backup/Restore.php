<?php

namespace App\Modules\Backup;

use App\Core\Db;
use App\Core\Migrator;
use App\Modules\Design\Design;
use App\Modules\Pages\Sitemap;
use App\Modules\Update\Maintenance;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Putting a backup back (PLAN.md D-139), in steps, behind maintenance.
 *
 *   schema   every table dropped, and made again by the migrations the backup's site had
 *            applied: no more, so the rows fit the tables they came out of
 *   rows     each table's rows loaded, a piece at a time
 *   files    the originals and their sizes emptied and written again from the archive
 *   finish   the key, if the backup's differs; the migrations this code adds; the design's
 *            stylesheet and the sitemap; maintenance off
 *
 * Only the key comes from the backup's .env. The rest of this server's .env stays: its
 * database settings are the ones that work here. A backup of the site as it was is made
 * before a restore begins (RestoreController), so a restore can itself be undone.
 */
final class Restore
{
    private const STATE = 'restore.state.json';

    private readonly RestoreRows $rows;
    private readonly Backups $kept;
    private readonly RestoreFiles $files;

    public function __construct(
        private readonly Db $db,
        private readonly string $root,
        private readonly string $backups,
        private readonly string $uploads,
        private readonly string $media,
        private readonly string $env,
        private readonly string $key,
        private readonly string $storage,
        private readonly string $cache,
        private readonly string $public,
        string $customDesigns = '',
    ) {
        $this->rows = new RestoreRows($db, $root, $backups . '/restore.work');
        $this->kept = new Backups($backups);
        $this->files = new RestoreFiles($uploads, $media, $customDesigns);
    }

    public function running(): bool
    {
        return is_file($this->backups . '/' . self::STATE);
    }

    /**
     * Why the backup named $name cannot be restored here, or null when it can.
     */
    public function problem(string $name): ?string
    {
        if ($this->kept->path($name) === null) {
            return t('backups.not_found');
        }
        try {
            $manifest = $this->kept->manifest($name);
        } catch (RuntimeException) {
            return t('backups.unreadable');
        }
        $unknown = array_diff($manifest['migrations'], (new Migrator($this->db, $this->root . '/migrations'))->available());
        if ($unknown !== []) {
            return t('backups.newer', ['version' => (string) ($manifest['version'] ?? '')]);
        }

        return null;
    }

    public function start(string $name): void
    {
        $problem = $this->problem($name);
        if ($problem !== null) {
            throw new RuntimeException($problem);
        }
        $maintenance = new Maintenance($this->storage);
        $wasOn = $maintenance->isOn();
        if (!$wasOn) {
            $maintenance->turnOn(Maintenance::UPDATE);
        }
        $work = $this->backups . '/restore.work';
        if (!is_dir($work) && !mkdir($work, 0770) && !is_dir($work)) {
            throw new RuntimeException("Cannot make {$work}.");
        }
        $this->save([
            'name' => $name,
            'phase' => 'schema',
            'maintenance' => $wasOn,
            // The admin's own table first: see step().
            'tables' => self::adminFirst(array_keys($this->kept->manifest($name)['rows'])),
            'table' => 0,
            'at' => 0,
            'entry' => 0,
        ]);
    }

    /**
     * @return array{done: bool, phase: string, tables: int, tablesDone: int, entries: int, entriesDone: int}
     */
    public function step(?float $budget): array
    {
        $state = $this->load();
        $zip = new ZipArchive();
        if ($zip->open((string) $this->kept->path($state['name'])) !== true) {
            throw new RuntimeException(t('backups.unreadable'));
        }
        $until = $budget === null ? INF : microtime(true) + $budget;
        $moved = false;

        try {
            if ($state['phase'] === 'schema') {
                // The key first, so the backup's two-step secret opens from the next login on.
                if (($this->kept->manifest($state['name'])['key'] ?? '') !== Backup::fingerprint($this->key)) {
                    $this->takeKey((string) $zip->getFromName('.env'));
                }
                $this->rows->schema($this->kept->manifest($state['name'])['migrations']);
                // And the admin's table loaded in the same request that emptied it. Between
                // the two there is no account at all; a step cut short there would leave a
                // closed site nobody could log in to carry the restore on.
                $state['phase'] = 'rows';
                while ($state['table'] === 0) {
                    $state = $this->rows($zip, $state);
                }
                $moved = true;
                $this->save($state);
            }
            while ($state['phase'] === 'rows' && (!$moved || microtime(true) < $until)) {
                $moved = true;
                $state = $this->rows($zip, $state);
                $this->save($state);
            }
            while ($state['phase'] === 'files' && (!$moved || microtime(true) < $until)) {
                $moved = true;
                if ($state['entry'] === 0) {
                    // Emptied before the first file is written back; again, harmlessly, if
                    // the step that did it was cut short before it wrote any.
                    $this->files->empty();
                }
                $state = $this->files($zip, $state, $until);
                $this->save($state);
            }
            if ($state['phase'] === 'finish') {
                $this->finish($zip, $state);

                return ['done' => true, 'phase' => 'finish', 'tables' => count($state['tables']), 'tablesDone' => count($state['tables']), 'entries' => $zip->numFiles, 'entriesDone' => $zip->numFiles];
            }

            return [
                'done' => false,
                'phase' => $state['phase'],
                'tables' => count($state['tables']),
                'tablesDone' => min($state['table'], count($state['tables'])),
                'entries' => $zip->numFiles,
                'entriesDone' => $state['entry'],
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * One piece of one table's rows (RestoreRows), and on to the files when every table is in.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function rows(ZipArchive $zip, array $state): array
    {
        if ($state['table'] >= count($state['tables'])) {
            return ['phase' => 'files', 'entry' => 0] + $state;
        }
        $at = $this->rows->piece($zip, (string) $state['tables'][$state['table']], (int) $state['at']);

        return $at === null ? ['table' => $state['table'] + 1, 'at' => 0] + $state : ['at' => $at] + $state;
    }

    /**
     * The files from entry $state['entry'] on (RestoreFiles), and on to the finish when the
     * archive's last entry is written.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function files(ZipArchive $zip, array $state, float $until): array
    {
        $state['entry'] = $this->files->from($zip, (int) $state['entry'], $until);
        if ($state['entry'] >= $zip->numFiles) {
            $state['phase'] = 'finish';
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function finish(ZipArchive $zip, array $state): void
    {
        (new Migrator($this->db, $this->root . '/migrations'))->migrate();
        Design::stylesheet($this->db, $this->cache);
        Sitemap::publish($this->db, $this->public);

        if (!$state['maintenance']) {
            (new Maintenance($this->storage))->turnOff(Maintenance::UPDATE);
        }
        unlink($this->backups . '/' . self::STATE);
        $work = $this->backups . '/restore.work';
        foreach (glob($work . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($work)) {
            rmdir($work);
        }
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     */
    private static function adminFirst(array $tables): array
    {
        return in_array('admin', $tables, true)
            ? array_merge(['admin'], array_values(array_diff($tables, ['admin'])))
            : $tables;
    }

    /**
     * The backup's APP_KEY into this site's .env, in place of the line there: the secrets in
     * the restored rows were sealed with it. Everything else in .env stays as it is.
     */
    private function takeKey(string $backupEnv): void
    {
        if (preg_match('~^APP_KEY=.*$~m', $backupEnv, $line) !== 1 || !is_file($this->env)) {
            return;
        }
        $current = (string) file_get_contents($this->env);
        $updated = preg_match('~^APP_KEY=.*$~m', $current) === 1
            ? (string) preg_replace('~^APP_KEY=.*$~m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line[0]), $current)
            : rtrim($current, "\n") . "\n" . $line[0] . "\n";
        file_put_contents($this->env . '.restoring', $updated);
        rename($this->env . '.restoring', $this->env);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function save(array $state): void
    {
        $file = $this->backups . '/' . self::STATE;
        file_put_contents($file . '.tmp', json_encode($state, JSON_THROW_ON_ERROR));
        rename($file . '.tmp', $file);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $state = is_file($this->backups . '/' . self::STATE)
            ? json_decode((string) file_get_contents($this->backups . '/' . self::STATE), true)
            : null;
        if (!is_array($state)) {
            throw new RuntimeException(t('backups.no_restore'));
        }

        return $state;
    }
}
