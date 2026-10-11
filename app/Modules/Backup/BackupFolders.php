<?php

namespace App\Modules\Backup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The folders a backup carries, and where each archive entry lives on this site (PLAN.md
 * D-139, D-155): the originals, the sizes made from them, and the design sets an owner
 * dropped into designs/custom/.
 *
 * Split from Backup when the third folder arrived and the same list stood written twice,
 * once for making a backup and once in RestoreFiles for restoring one. One list, read by
 * both, so a folder cannot be backed up and then passed over on the way back.
 */
final class BackupFolders
{
    public function __construct(
        private readonly string $uploads,
        private readonly string $media,
        private readonly string $customDesigns = '',
    ) {
    }

    /**
     * Each folder by the path it has in the archive.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return ['storage/uploads' => $this->uploads, 'public/m' => $this->media]
            + ($this->customDesigns !== '' ? ['designs/custom' => $this->customDesigns] : []);
    }

    /**
     * Every file in every folder, as its path in the archive, dot files left out (.gitkeep,
     * .htaccess: the install brings its own).
     *
     * @return iterable<string>
     */
    public function files(): iterable
    {
        foreach ($this->all() as $prefix => $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && !str_starts_with($file->getFilename(), '.')) {
                    yield $prefix . '/' . substr($file->getPathname(), strlen($directory) + 1);
                }
            }
        }
    }

    /**
     * Where an archive entry goes on this site, or null for one that is not a file of it: a
     * path outside these folders, one that climbs out, a folder, or a name with a NUL in it.
     */
    public function where(string $entry): ?string
    {
        if ($entry === '' || str_contains('/' . $entry . '/', '/../') || str_contains($entry, "\0") || str_starts_with($entry, '/')) {
            return null;
        }
        foreach ($this->all() as $prefix => $directory) {
            if (str_starts_with($entry, $prefix . '/') && strlen($entry) > strlen($prefix) + 1 && !str_ends_with($entry, '/')) {
                return $directory . '/' . substr($entry, strlen($prefix) + 1);
            }
        }

        return null;
    }
}
