<?php

namespace App\Modules\Backup;

use App\Core\Db;
use App\Support\Version;
use App\Support\ZipWriter;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Making a backup, in as many steps as the host's time limit makes it take (PLAN.md D-139).
 *
 * WHAT IT HOLDS:
 *   manifest.json                what it is: version, driver, migrations, counts, key's print
 *   database/{table}.ndjson      every row of every table (BackupTables), one JSON per line
 *   storage/uploads/…            the originals
 *   public/m/…                   every size made from them
 *   .env                         the site's settings, among them the key that seals secrets
 *
 * Rows rather than SQL, so it restores on either driver, into code as new as or newer than
 * the code that made it.
 *
 * IN STEPS. start() lists the work; each step() does what fits in its budget and writes where
 * it stopped, together with the archive's own checkpoint, in one file. A step cut short by the
 * host costs what it did since the last checkpoint and nothing more. Only one backup runs at a
 * time: starting while one is unfinished carries on with that one.
 */
final class Backup
{
    /** How often a step writes down where it is, in rows or files. */
    private const CHECKPOINT_EVERY = 200;

    public function __construct(
        private readonly Db $db,
        private readonly string $root,
        private readonly string $backups,
        private readonly string $uploads,
        private readonly string $media,
        private readonly string $env,
        private readonly string $key,
    ) {
    }

    /**
     * The backup being made, if one is: its name. Null when none is unfinished.
     */
    public function running(): ?string
    {
        // A backup's own name only: a restore keeps its state beside these, as restore.state.json.
        foreach (glob($this->backups . '/*.state.json') ?: [] as $state) {
            if (Backups::validName(basename($state, '.state.json'))) {
                return basename($state, '.state.json');
            }
        }

        return null;
    }

    /**
     * Begins a backup, or returns the one already under way.
     *
     * @param 'manual'|'update'|'restore' $kind what it is for: the ones made before an
     *        update or a restore are pruned to the last few, the owner's own never are
     */
    public function start(string $kind): string
    {
        $running = $this->running();
        if ($running !== null) {
            return $running;
        }
        if (!is_dir($this->backups) && !mkdir($this->backups, 0770, true) && !is_dir($this->backups)) {
            throw new RuntimeException("Cannot make {$this->backups}.");
        }
        $name = gmdate('Y-m-d-His') . '-' . $kind;
        $work = $this->backups . '/' . $name . '.work';
        if (!is_dir($work) && !mkdir($work, 0770) && !is_dir($work)) {
            throw new RuntimeException("Cannot make {$work}.");
        }

        // The files are listed once, now, one relative path a line. What is uploaded after
        // this is in the next backup; what is deleted before it is reached is skipped.
        $list = fopen($work . '/files.txt', 'wb') ?: throw new RuntimeException("Cannot write in {$work}.");
        $files = 0;
        foreach (['storage/uploads' => $this->uploads, 'public/m' => $this->media] as $prefix => $directory) {
            foreach (self::walk($directory) as $relative) {
                fwrite($list, $prefix . '/' . $relative . "\n");
                $files++;
            }
        }
        fclose($list);

        $this->save($name, [
            'kind' => $kind,
            'started' => gmdate('Y-m-d H:i:s'),
            'tables' => BackupTables::all($this->db),
            'table' => 0,
            'after' => null,
            'offset' => 0,
            'rows' => [],
            'part' => 0,
            'files' => $files,
            'file' => 0,
            'listAt' => 0,
            'zip' => null,
        ]);

        return $name;
    }

    /**
     * Does what fits in $budget seconds (null: no limit) of the backup under way.
     *
     * @return array{done: bool, name: string, tables: int, tablesDone: int, files: int, filesDone: int}
     */
    public function step(?float $budget): array
    {
        $name = $this->running() ?? throw new RuntimeException('No backup is being made.');
        $state = $this->load($name);
        $work = $this->backups . '/' . $name . '.work';
        $zip = new ZipWriter($this->backups . '/' . $name . '.zip.part', $state['zip']);
        $until = $budget === null ? INF : microtime(true) + $budget;
        $since = 0;
        // Every step does at least one piece, however small its budget, or a host with
        // too little time would step for ever and never move.
        $moved = false;

        // The tables: a piece at a time into a file of rows, which goes into the archive once
        // the table is read to its end.
        while ($state['table'] < count($state['tables']) && (!$moved || microtime(true) < $until)) {
            $moved = true;
            $table = $state['tables'][$state['table']];
            $key = BackupTables::key($this->db, $table);
            $rows = BackupTables::read($this->db, $table, $key, $state['after'], $state['offset']);
            $file = $work . '/' . $table . '.ndjson';
            $out = fopen($file, 'c+b') ?: throw new RuntimeException("Cannot write {$file}.");
            ftruncate($out, $state['part']);
            fseek($out, $state['part']);
            foreach ($rows as $row) {
                fwrite($out, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
            $state['part'] = (int) ftell($out);
            fclose($out);
            $state['rows'][$table] = ($state['rows'][$table] ?? 0) + count($rows);
            $state['offset'] += count($rows);
            if ($key !== null && $rows !== []) {
                $state['after'] = $rows[count($rows) - 1][$key];
            }
            if (count($rows) < BackupTables::ROWS_PER_READ) {
                $zip->addFile('database/' . $table . '.ndjson', $file);
                unlink($file);
                $state = ['table' => $state['table'] + 1, 'after' => null, 'offset' => 0, 'part' => 0] + $state;
            }
            $state['zip'] = $zip->checkpoint();
            $this->save($name, $state);
        }

        // The files, from the list made at the start.
        if ($state['table'] >= count($state['tables']) && $state['file'] < $state['files']) {
            $list = fopen($work . '/files.txt', 'rb') ?: throw new RuntimeException("Cannot read {$work}/files.txt.");
            fseek($list, $state['listAt']);
            while ($state['file'] < $state['files'] && (!$moved || microtime(true) < $until) && ($line = fgets($list)) !== false) {
                $moved = true;
                $relative = rtrim($line, "\n");
                $absolute = $this->absolute($relative);
                if (is_file($absolute)) {
                    $zip->addFile($relative, $absolute);
                }
                $state['file']++;
                $state['listAt'] = (int) ftell($list);
                if (++$since % self::CHECKPOINT_EVERY === 0) {
                    $state['zip'] = $zip->checkpoint();
                    $this->save($name, $state);
                }
            }
            fclose($list);
            $state['zip'] = $zip->checkpoint();
            $this->save($name, $state);
        }

        $done = $state['table'] >= count($state['tables']) && $state['file'] >= $state['files'];
        if ($done) {
            $this->finish($name, $state, $zip);
        }

        return [
            'done' => $done,
            'name' => $name,
            'tables' => count($state['tables']),
            'tablesDone' => min($state['table'], count($state['tables'])),
            'files' => $state['files'],
            'filesDone' => $state['file'],
        ];
    }

    /**
     * The settings file and the manifest, the directory, and the finished archive in place
     * of the part.
     *
     * @param array<string, mixed> $state
     */
    private function finish(string $name, array $state, ZipWriter $zip): void
    {
        if (is_file($this->env)) {
            $zip->addFile('.env', $this->env);
        }
        $migrations = array_map(
            static fn (array $row): string => (string) $row['filename'],
            $this->db->all('SELECT filename FROM migrations ORDER BY filename'),
        );
        $zip->addString('manifest.json', json_encode([
            'boxlet' => 1,
            'kind' => $state['kind'],
            'version' => Version::current($this->root),
            'driver' => $this->db->driver,
            'started' => $state['started'],
            'finished' => gmdate('Y-m-d H:i:s'),
            'migrations' => $migrations,
            'rows' => $state['rows'],
            'files' => $state['files'],
            // Which key sealed the secrets in these rows, without the key itself: a restore
            // onto a site whose key differs must bring this one with it (D-139).
            'key' => self::fingerprint($this->key),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->finish();

        rename($this->backups . '/' . $name . '.zip.part', $this->backups . '/' . $name . '.zip');
        unlink($this->backups . '/' . $name . '.state.json');
        $work = $this->backups . '/' . $name . '.work';
        foreach (glob($work . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($work);
    }

    public static function fingerprint(string $key): string
    {
        return substr(hash('sha256', 'boxlet-backup-key:' . $key), 0, 16);
    }

    /** Where an archive path lives on this site. */
    private function absolute(string $relative): string
    {
        return str_starts_with($relative, 'storage/uploads/')
            ? $this->uploads . '/' . substr($relative, strlen('storage/uploads/'))
            : $this->media . '/' . substr($relative, strlen('public/m/'));
    }

    /**
     * Every file under $directory, as paths relative to it, dot files left out (.gitkeep,
     * .htaccess: the install brings its own).
     *
     * @return iterable<string>
     */
    private static function walk(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && !str_starts_with($file->getFilename(), '.')) {
                yield substr($file->getPathname(), strlen($directory) + 1);
            }
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function save(string $name, array $state): void
    {
        // Written beside and moved over, so a step killed mid-write leaves the last whole one.
        $file = $this->backups . '/' . $name . '.state.json';
        file_put_contents($file . '.tmp', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        rename($file . '.tmp', $file);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $name): array
    {
        $state = json_decode((string) file_get_contents($this->backups . '/' . $name . '.state.json'), true);
        if (!is_array($state)) {
            throw new RuntimeException("The backup {$name} cannot be carried on: its state is unreadable.");
        }

        return $state;
    }
}
