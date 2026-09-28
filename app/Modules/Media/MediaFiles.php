<?php

namespace App\Modules\Media;

use App\Core\Blocks;
use App\Core\Db;
use App\Support\Bytes;

/**
 * The library's FILES as blocks and fields need them (PLAN.md D-127): the choices a `file`
 * field offers, and what a Downloads block draws for each file it names.
 *
 * Beside MediaPicture, which does the same two jobs for pictures, and apart from it: a
 * picture becomes a <picture> of sizes, a file becomes a name, a type, a size and the
 * address it is downloaded from — nothing the two could share but the table they live in.
 */
final class MediaFiles
{
    /**
     * Every file a field may choose, newest first, as id => what the select shows.
     *
     * @return array<int, string>
     */
    public static function choices(Db $db, int $limit = 200): array
    {
        $choices = [];
        foreach ($db->all("SELECT id, filename, path, size FROM media WHERE kind = 'file' ORDER BY id DESC LIMIT " . $limit) as $row) {
            $extension = strtolower(pathinfo((string) $row['path'], PATHINFO_EXTENSION));
            $choices[(int) $row['id']] = (string) $row['filename'] . '.' . $extension . ' · ' . Bytes::human((int) $row['size']);
        }

        return $choices;
    }

    /**
     * Every file the blocks name, as id => what a template draws — one query for the page.
     *
     * @param list<array<string, mixed>> $blocks
     * @return array<int, array{name: string, extension: string, size: string, url: string}>
     */
    public static function forBlocks(Db $db, Blocks $registry, array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            foreach (MediaReference::idsIn($registry, $type, $content, ['file']) as $id) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $files = [];
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        foreach ($db->all("SELECT id, filename, path, size FROM media WHERE kind = 'file' AND id IN ({$placeholders})", array_keys($ids)) as $row) {
            $extension = strtolower(pathinfo((string) $row['path'], PATHINFO_EXTENSION));
            $files[(int) $row['id']] = [
                'name' => (string) $row['filename'],
                'extension' => $extension,
                'size' => Bytes::human((int) $row['size']),
                'url' => DownloadController::url((int) $row['id'], (string) $row['filename']),
            ];
        }

        return $files;
    }
}
