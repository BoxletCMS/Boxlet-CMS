<?php

namespace App\Modules\Media;

use App\Core\Db;

/**
 * What a picture MEANS, per language: its alt text and its caption (SPEC §5.2).
 *
 * Split from MediaLibrary, which reached the 300-line rule when suggested alt text (D-025)
 * gave both of these more to do. The seam is a real one rather than a line count:
 * MediaLibrary is about the pictures themselves — listing them, finding what uses them,
 * deleting them and their files — while this is about the words attached to them, which
 * live in another table, are edited on a different screen, and are the only part of a
 * picture that differs per language.
 *
 * Static with a Db handed in, which is the idiom the rest of this module already uses
 * (MediaAlt::fill, MediaVariants::of, MediaPicture::resolve): no container entry to add,
 * and nothing here holds state between calls.
 */
final class MediaMeta
{
    /**
     * Alt text and caption for one picture, keyed by locale.
     *
     * `suggested` says Boxlet wrote the alt rather than the owner (D-025), so the screen
     * can ask them to check it instead of presenting a guess as their own words.
     *
     * @return array<string, array{alt: string, caption: string, suggested: bool}>
     */
    public static function forPicture(Db $db, int $mediaId): array
    {
        $meta = [];
        foreach ($db->all('SELECT locale, alt, caption, alt_suggested FROM media_meta WHERE media_id = ?', [$mediaId]) as $row) {
            $meta[(string) $row['locale']] = [
                'alt' => (string) $row['alt'],
                'caption' => (string) ($row['caption'] ?? ''),
                'suggested' => (int) ($row['alt_suggested'] ?? 0) === 1,
            ];
        }

        return $meta;
    }

    /**
     * Saves what a picture means in one locale. An empty alt is meaningful — it says the
     * picture is decorative — so it is stored rather than treated as "not filled in".
     *
     * SAVING IS CONFIRMING (D-025): alt_suggested is cleared whatever the text says, even
     * when the owner leaves a suggestion word for word. Clearing it only on a change would
     * leave an approved suggestion still asking to be checked, with no way to silence the
     * badge except editing words they already agree with.
     */
    public static function save(Db $db, int $mediaId, string $locale, string $alt, string $caption): void
    {
        $existing = $db->one(
            'SELECT id FROM media_meta WHERE media_id = ? AND locale = ?',
            [$mediaId, $locale],
        );

        if ($existing === null) {
            $db->query(
                'INSERT INTO media_meta (media_id, locale, alt, caption, alt_suggested) VALUES (?, ?, ?, ?, 0)',
                [$mediaId, $locale, substr($alt, 0, 255), $caption],
            );

            return;
        }

        $db->query(
            'UPDATE media_meta SET alt = ?, caption = ?, alt_suggested = 0 WHERE media_id = ? AND locale = ?',
            [substr($alt, 0, 255), $caption, $mediaId, $locale],
        );
    }

    /**
     * Copies what one picture means onto another, mark and all (D-026).
     *
     * NOT save(). Saving is the owner confirming a suggestion, so it clears the mark — and
     * that is right for the form, where a person looked at the words and pressed a button.
     * Copying is nobody confirming anything: a crop shows the same subject, so its
     * description comes across, and a guess that was still a guess stays one. Running this
     * through save() would quietly promote every copied suggestion to the owner's own
     * words, which is the opposite of what the badge is for.
     *
     * A row already on the target is overwritten ONLY while it is still a suggestion, which
     * is the rule D-025 already uses for replacing a picture's bytes. That is not a detail:
     * the target has just been created through the ordinary upload path, so it arrives
     * carrying a suggestion invented from the crop's own generated file name — "Tim u uredu
     * crop" — and leaving that in place would throw away the description the owner actually
     * wrote for the picture it was cut from. An alt the owner has confirmed on the target is
     * left alone, because then somebody has spoken and a copy must not argue.
     */
    public static function copy(Db $db, int $fromId, int $toId): void
    {
        foreach (self::forPicture($db, $fromId) as $locale => $meaning) {
            $existing = $db->one(
                'SELECT id, alt_suggested FROM media_meta WHERE media_id = ? AND locale = ?',
                [$toId, $locale],
            );
            $mark = $meaning['suggested'] ? 1 : 0;

            if ($existing === null) {
                $db->query(
                    'INSERT INTO media_meta (media_id, locale, alt, caption, alt_suggested) VALUES (?, ?, ?, ?, ?)',
                    [$toId, $locale, substr($meaning['alt'], 0, 255), $meaning['caption'], $mark],
                );

                continue;
            }

            if ((int) ($existing['alt_suggested'] ?? 0) !== 1) {
                continue;
            }

            $db->query(
                'UPDATE media_meta SET alt = ?, caption = ?, alt_suggested = ? WHERE media_id = ? AND locale = ?',
                [substr($meaning['alt'], 0, 255), $meaning['caption'], $mark, $toId, $locale],
            );
        }
    }
}
