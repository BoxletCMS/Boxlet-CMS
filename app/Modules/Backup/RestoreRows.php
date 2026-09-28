<?php

namespace App\Modules\Backup;

use App\Core\Db;
use App\Core\Migrator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * The database half of a restore (PLAN.md D-139): the tables made again as the backup's site
 * had them, and its rows loaded into them a piece at a time.
 */
final class RestoreRows
{
    private const ROWS_PER_INSERT = 200;

    public function __construct(
        private readonly Db $db,
        private readonly string $root,
        private readonly string $work,
    ) {
    }

    /**
     * Every table dropped, then made again by exactly the backup's migrations, and the
     * record of them emptied so the backup's own record can be loaded in its place.
     *
     * @param list<string> $migrations
     */
    public function schema(array $migrations): void
    {
        $this->foreignKeys(false);
        try {
            foreach (array_merge(BackupTables::all($this->db), BackupTables::LEFT_OUT) as $table) {
                $this->db->query('DROP TABLE IF EXISTS ' . BackupTables::quote($this->db, $table));
            }
            (new Migrator($this->db, $this->root . '/migrations'))->migrate($migrations);
            // Emptied, the record of migrations among them: some migrations seed rows (the
            // page templates), and the backup brings those rows as they stood on its site.
            foreach (BackupTables::all($this->db) as $table) {
                $this->db->query('DELETE FROM ' . BackupTables::quote($this->db, $table));
            }
        } finally {
            $this->foreignKeys(true);
        }
    }

    /**
     * Foreign keys off while tables are dropped and loaded, since a table's rows arrive before
     * or after the rows they point at, in no order the keys would accept; on again after.
     * Set outside any transaction: SQLite ignores the pragma inside one.
     */
    private function foreignKeys(bool $on): void
    {
        $this->db->pdo()->exec($this->db->driver === 'mysql'
            ? 'SET FOREIGN_KEY_CHECKS = ' . ($on ? '1' : '0')
            : 'PRAGMA foreign_keys = ' . ($on ? 'ON' : 'OFF'));
    }

    /**
     * One piece of $table's rows, from byte $at of its file. The file is taken out of the
     * archive once, when $at is 0, and read from where the last piece stopped.
     *
     * @return int|null where the next piece starts, or null when the table is all in
     */
    public function piece(ZipArchive $zip, string $table, int $at): ?int
    {
        $file = $this->work . '/' . $table . '.ndjson';
        if ($at === 0) {
            $stream = $zip->getStream('database/' . $table . '.ndjson');
            if ($stream === false) {
                throw new RuntimeException(t('backups.unreadable'));
            }
            $out = fopen($file, 'wb') ?: throw new RuntimeException("Cannot write {$file}.");
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
        }

        $size = (int) filesize($file);
        $in = fopen($file, 'rb') ?: throw new RuntimeException("Cannot read {$file}.");
        fseek($in, $at);
        $pdo = $this->db->pdo();
        $this->foreignKeys(false);
        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < self::ROWS_PER_INSERT && ($line = fgets($in)) !== false; $i++) {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $columns = array_keys($row);
                $this->db->query(
                    'INSERT INTO ' . BackupTables::quote($this->db, $table)
                    . ' (' . implode(', ', array_map(fn (int|string $c): string => BackupTables::quote($this->db, (string) $c), $columns)) . ')'
                    . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                    array_values($row),
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fclose($in);
            $this->foreignKeys(true);
            throw new RuntimeException(t('backups.row_failed', ['table' => $table, 'error' => $e->getMessage()]), 0, $e);
        }
        $this->foreignKeys(true);
        $at = (int) ftell($in);
        fclose($in);
        if ($at >= $size) {
            unlink($file);

            return null;
        }

        return $at;
    }
}
