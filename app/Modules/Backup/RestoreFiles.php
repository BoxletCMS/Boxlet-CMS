<?php

namespace App\Modules\Backup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * The files half of a restore (PLAN.md D-139): the originals and their sizes, emptied and
 * written again from the archive. Only entries under storage/uploads/ and public/m/ are
 * written, and only inside those folders; anything else in the archive is not a file of the
 * site and is passed over.
 */
final class RestoreFiles
{
    public function __construct(
        private readonly string $uploads,
        private readonly string $media,
    ) {
    }

    /**
     * The archive's files from entry $entry on, written where they belong, until $until
     * (at least one, however little time there is).
     *
     * @return int the entry to carry on from
     */
    public function from(ZipArchive $zip, int $entry, float $until): int
    {
        $first = true;
        while ($entry < $zip->numFiles && ($first || microtime(true) < $until)) {
            $first = false;
            $name = (string) $zip->getNameIndex($entry);
            $target = $this->target($name);
            if ($target !== null) {
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }
                $stream = $zip->getStream($name);
                $out = fopen($target . '.restoring', 'wb');
                if ($stream === false || $out === false) {
                    throw new RuntimeException(t('backups.file_failed', ['file' => $name]));
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
                rename($target . '.restoring', $target);
            }
            $entry++;
        }

        return $entry;
    }

    /** Where an archive entry goes on this site, or null for one that is not a file of it. */
    private function target(string $name): ?string
    {
        if ($name === '' || str_contains('/' . $name . '/', '/../') || str_contains($name, "\0") || str_starts_with($name, '/')) {
            return null;
        }
        foreach (['storage/uploads/' => $this->uploads, 'public/m/' => $this->media] as $prefix => $directory) {
            if (str_starts_with($name, $prefix) && strlen($name) > strlen($prefix) && !str_ends_with($name, '/')) {
                return $directory . '/' . substr($name, strlen($prefix));
            }
        }

        return null;
    }

    /** Removes every file in both folders but their dot files (.htaccess, .gitkeep). */
    public function empty(): void
    {
        $this->clear($this->uploads);
        $this->clear($this->media);
    }

    private function clear(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);

            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                // Left standing when it still holds a dot file.
                if (count(scandir($item->getPathname()) ?: []) === 2) {
                    rmdir($item->getPathname());
                }
            } elseif (!str_starts_with($item->getFilename(), '.')) {
                unlink($item->getPathname());
            }
        }
    }
}
