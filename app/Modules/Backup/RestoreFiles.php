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
 *
 * And the design sets the owner dropped into designs/custom/ (D-155), written back but never
 * emptied first: they are the owner's own files, put there by hand, and a file there that the
 * backup does not hold was not Boxlet's to take away.
 */
final class RestoreFiles
{
    private readonly BackupFolders $folders;

    public function __construct(
        private readonly string $uploads,
        private readonly string $media,
        string $customDesigns = '',
    ) {
        $this->folders = new BackupFolders($uploads, $media, $customDesigns);
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
            $target = $this->folders->where($name);
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
