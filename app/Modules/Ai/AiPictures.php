<?php

namespace App\Modules\Ai;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Media\MediaReference;
use App\Modules\Pages\PageDocument;
use Closure;

/**
 * A PAGE'S PICTURES DESCRIBED IN ITS LANGUAGE (PLAN.md D-260, the owner): the alt text of each
 * picture the page shows, translated from the original's language into the page's, where the
 * picture has none in it yet.
 *
 * Alt text lives with the picture in the library, one row per language (media_meta), not with
 * the page; a page in a language with no row of its own shows the main language's words
 * (MediaPicture). So this writes only a MISSING row: an alt the owner wrote in that language is
 * never touched, nor is an empty one, which says the picture is decoration. A picture whose
 * original alt is empty, or has none, is passed over too: there is nothing to translate.
 *
 * The rows are the library's, and live at once, unlike a page's words, which wait in its draft.
 * That is never worse than before: a translation in place of the main language's words. NOT
 * marked a suggestion: alt_suggested (D-025) is the main language's guess from a file name, the
 * one row a replaced file may refresh (MediaAlt::fill, suggestedIds), and a translation is
 * neither. The owner changes it on the picture's screen like any other description. A picture
 * on several pages is translated once:
 * the first page's part writes its row, and the next finds it there.
 *
 * @phpstan-import-type Document from PageDocument
 */
final class AiPictures
{
    /**
     * The pictures on a page with an alt in $from and none in $to: media id => the alt in $from.
     *
     * @param Document $document
     * @return array<int, string>
     */
    public static function missing(Db $db, Blocks $registry, array $document, string $from, string $to): array
    {
        $ids = [];
        foreach ($document['blocks'] as $block) {
            if ($block['content'] !== null) {
                foreach (MediaReference::idsIn($registry, $block['type'], $block['content']) as $id) {
                    $ids[$id] = $id;
                }
            }
        }
        if ($ids === [] || $from === $to) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $rows = $db->all("SELECT media_id, locale, alt FROM media_meta WHERE locale IN (?, ?) AND media_id IN ({$marks})", array_merge([$from, $to], array_values($ids)));
        $there = [];
        $alts = [];
        foreach ($rows as $row) {
            if ($row['locale'] === $to) {
                $there[(int) $row['media_id']] = true;
            } elseif (trim((string) $row['alt']) !== '') {
                $alts[(int) $row['media_id']] = (string) $row['alt'];
            }
        }
        $missing = [];
        foreach ($ids as $id) {
            if (isset($alts[$id]) && !isset($there[$id])) {
                $missing[$id] = $alts[$id];
            }
        }

        return $missing;
    }

    /**
     * Translates what is missing and writes each. Returns how many kept no
     * translation (a check failed), for the caller to say.
     *
     * @param Document $document
     * @param array{keep: string, voice: string} $words
     * @param Closure(string, string): array{text: string, in: int, out: int} $ask
     */
    public static function run(Db $db, Blocks $registry, array $document, string $from, string $to, string $fromName, string $toName, array $words, Closure $ask): int
    {
        $missing = self::missing($db, $registry, $document, $from, $to);
        if ($missing === []) {
            return 0;
        }
        $texts = [];
        foreach ($missing as $id => $alt) {
            $texts['m' . $id] = ['text' => $alt, 'html' => false];
        }
        $result = AiTranslator::translate($texts, $fromName, $toName, $words, $ask);
        AiSettings::addUsage($db, $result['in'], $result['out']);
        foreach ($result['done'] as $key => $alt) {
            $id = (int) substr($key, 1);
            // Asked again at the moment of writing: the owner may have written one meanwhile.
            if ($db->one('SELECT id FROM media_meta WHERE media_id = ? AND locale = ?', [$id, $to]) === null) {
                $db->query(
                    'INSERT INTO media_meta (media_id, locale, alt, caption, alt_suggested) VALUES (?, ?, ?, NULL, 0)',
                    [$id, $to, mb_substr($alt, 0, 255)],
                );
            }
        }

        return count($result['failed']);
    }
}
