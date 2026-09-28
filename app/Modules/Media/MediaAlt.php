<?php

namespace App\Modules\Media;

use App\Core\Db;

/**
 * A suggested alt text for a picture that has none (PLAN.md D-025).
 *
 * WHY GUESS AT ALL. The earlier rule was "no alt rather than a file name", and it was
 * right about device names and wrong about everything else: a photograph exported as
 * `tim-u-uredu_2024.jpg` already carries a description its owner wrote, and throwing it
 * away left the library full of pictures with nothing to say. So a meaningful name is
 * used, a device-generated one still is not, and every guess is MARKED as a guess until
 * the owner confirms it.
 *
 * Its own class rather than a few methods on MediaUpload: reading meaning out of a file is
 * a different job from accepting and storing bytes, and MediaUpload was already at the
 * 300-line limit before this existed.
 *
 * Order, from most to least trustworthy: what the photographer typed into the file's
 * metadata, then what they called the file. Nothing else is invented.
 */
final class MediaAlt
{
    /**
     * Strings cameras and phones write into title fields, meaning "a camera made this".
     * Announcing them aloud is worse than silence.
     *
     * Deliberately short. Every entry here is a string seen in the wild, not a guess at
     * one, because a too-eager list silently eats descriptions people really wrote.
     */
    private const GENERIC = [
        'olympus digital camera',
        'sony dsc',
        'konica minolta digital camera',
        'minolta digital camera',
        'nikon digital camera',
        'casio computer co.,ltd.',
        'exif_jpeg_picture',
        'dcim',
        'untitled',
        'default',
    ];

    /**
     * Names a device generated. Matched against the name with its extension removed.
     *
     * A screen reader reading "IMG 4032" aloud tells someone nothing except that a camera
     * was involved, which they can already assume. Empty is better.
     */
    private const DEVICE = [
        // IMG_1234, IMG-1234, IMG 1234, and the video/motion variants phones write.
        '~^(img|dsc|dscn|dscf|pxl|mvimg|vid|pano|burst)[ _-]?\d+~i',
        // PXL_20240101_123456789, 20240101_123456, 2024-01-01 12.34.56
        '~^\d{4}[-_]?\d{2}[-_]?\d{2}([ _-]?\d{2}[.:_-]?\d{2}([.:_-]?\d{2})?)?~',
        // Screenshot 2024-01-01 at 12.34.56, Screen Shot …, and the Czech and German ones
        // an owner of this CMS is as likely to have as the English.
        '~^(screenshot|screen shot|snímek obrazovky|snimek obrazovky|bildschirmfoto)~iu',
        // WhatsApp Image 2024-01-01 at 12.34.56, Signal-2024-01-01
        '~^(whatsapp|signal|viber|telegram)[ _-]~i',
        // Bare digits: 20240101, 4032, 1699999999 (a timestamp someone exported).
        '~^\d+$~',
        // Hash-like or UUID: 8+ hex characters, or the dashed UUID shape.
        '~^[0-9a-f]{8,}$~i',
        '~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i',
    ];

    /**
     * What this picture should say, or '' when nothing worth saying can be found.
     */
    public static function suggest(string $file, string $originalName): string
    {
        $fromMetadata = self::fromMetadata($file);

        return $fromMetadata !== '' ? $fromMetadata : self::fromName($originalName);
    }

    /**
     * A title the photographer typed, from IPTC first and then EXIF.
     *
     * IPTC is read through iptcparse, which is part of PHP itself — it works on a host
     * with no image extensions at all. EXIF needs the exif extension, so it is asked for
     * rather than assumed: plenty of shared hosts, and one leg of this project's CI, have
     * no exif at all, and a fatal there would refuse an upload that has nothing wrong with
     * it.
     */
    public static function fromMetadata(string $file): string
    {
        $info = [];
        @getimagesize($file, $info);

        if (isset($info['APP13']) && is_string($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);
            if (is_array($iptc)) {
                // 2#105 is the headline, 2#005 the object name. Headline first: it is the
                // one meant to be read as a sentence.
                foreach (['2#105', '2#005'] as $field) {
                    $value = $iptc[$field][0] ?? null;
                    $text = self::clean(is_string($value) ? $value : '');
                    if ($text !== '' && !self::isGeneric($text)) {
                        return $text;
                    }
                }
            }
        }

        if (!function_exists('exif_read_data')) {
            return '';
        }

        $exif = @exif_read_data($file);
        if (!is_array($exif)) {
            return '';
        }

        $description = self::clean(is_string($exif['ImageDescription'] ?? null) ? $exif['ImageDescription'] : '');
        if ($description !== '' && !self::isGeneric($description)) {
            return $description;
        }

        // XPTitle is Windows', and is UCS-2LE bytes rather than text.
        $title = self::clean(self::fromXp($exif['XPTitle'] ?? null));

        return $title !== '' && !self::isGeneric($title) ? $title : '';
    }

    /**
     * The file's own name, tidied — or '' when a device wrote it.
     *
     * `tim-u-uredu_2024.jpg` becomes "Tim u uredu 2024"; `IMG_4032.jpg` becomes nothing.
     */
    public static function fromName(string $originalName): string
    {
        // basename first: a name arriving as a path is a name, not a directory.
        $base = pathinfo(basename($originalName), PATHINFO_FILENAME);
        $base = self::clean($base);
        if ($base === '' || self::isDeviceName($base)) {
            return '';
        }

        $words = preg_replace('~[_.\-]+~u', ' ', $base);
        $words = self::clean(is_string($words) ? $words : '');
        if ($words === '' || self::isGeneric($words)) {
            return '';
        }

        // A name that already capitalises inside its first word chose to: iPhone, eBay,
        // macOS. Capitalising those produces "IPhone", which is not what anyone typed.
        // A name starting with a digit is left alone too — mb_strtoupper('2') is '2'.
        $firstWord = (string) (preg_split('~\s~u', $words)[0] ?? '');
        if (preg_match('~\p{Lu}~u', $firstWord) === 1) {
            return $words;
        }

        return mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);
    }

    /**
     * Writes the suggestion for the primary locale, unless the owner has already spoken.
     *
     * "Already spoken" means A ROW EXISTS THAT IS NOT MARKED AS A SUGGESTION — including a
     * row whose alt is empty, because an empty alt is itself a decision in this codebase:
     * it says the picture is decoration. Overwriting that with a guess would argue with
     * the owner about a choice they made deliberately.
     *
     * A row still marked as a suggestion may be refreshed, which is what lets replacing a
     * picture's bytes update a guess nobody has confirmed.
     */
    public static function fill(Db $db, int $mediaId, string $file, string $originalName): void
    {
        $locale = $db->one('SELECT code FROM locales WHERE is_primary = 1 ORDER BY sort, code');
        $code = is_array($locale) ? (string) ($locale['code'] ?? '') : '';
        if ($code === '') {
            return;
        }

        $existing = $db->one(
            'SELECT alt_suggested FROM media_meta WHERE media_id = ? AND locale = ?',
            [$mediaId, $code],
        );
        if ($existing !== null && (int) ($existing['alt_suggested'] ?? 0) !== 1) {
            return;
        }

        $alt = self::suggest($file, $originalName);
        if ($alt === '') {
            // Nothing worth saying. No row is written, so the picture simply has no alt
            // yet and the library does not claim Boxlet suggested one.
            return;
        }

        if ($existing === null) {
            $db->query(
                'INSERT INTO media_meta (media_id, locale, alt, caption, alt_suggested) VALUES (?, ?, ?, ?, 1)',
                [$mediaId, $code, $alt, ''],
            );

            return;
        }

        $db->query(
            'UPDATE media_meta SET alt = ?, alt_suggested = 1 WHERE media_id = ? AND locale = ?',
            [$alt, $mediaId, $code],
        );
    }

    /**
     * Which of these pictures still carry an unconfirmed suggestion.
     *
     * Not narrowed by locale, and it does not need to be: fill() writes the primary one
     * and nothing else ever sets the mark, so a row carrying it can only be that locale's.
     * Saying so here rather than adding a locale parameter that would carry no information.
     *
     * One query for the whole listing rather than one per card: the library shows two
     * hundred at a time, and a badge is not worth two hundred round trips.
     *
     * @param list<int> $mediaIds
     * @return array<int, true> id => true, so a caller can ask with isset()
     */
    public static function suggestedIds(Db $db, array $mediaIds): array
    {
        $ids = array_values(array_filter($mediaIds, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->all(
            "SELECT media_id FROM media_meta WHERE alt_suggested = 1 AND media_id IN ({$placeholders})",
            $ids,
        );

        $suggested = [];
        foreach ($rows as $row) {
            $suggested[(int) $row['media_id']] = true;
        }

        return $suggested;
    }

    /**
     * Control characters out, whitespace collapsed, and cut to what the column holds.
     *
     * mb_strcut rather than substr: cutting 255 BYTES through the middle of a character
     * would store a broken one, and Croatian alt text reaches the limit sooner than
     * English does.
     */
    private static function clean(string $text): string
    {
        $stripped = preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $text);
        $collapsed = preg_replace('~\s+~u', ' ', is_string($stripped) ? $stripped : '');

        return mb_strcut(trim(is_string($collapsed) ? $collapsed : ''), 0, 255);
    }

    private static function isGeneric(string $text): bool
    {
        return in_array(mb_strtolower($text), self::GENERIC, true);
    }

    private static function isDeviceName(string $name): bool
    {
        foreach (self::DEVICE as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A Windows XP* tag, which EXIF stores as UCS-2LE — either as bytes or, depending on
     * the build, as a comma-separated list of them.
     */
    private static function fromXp(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode('', array_map(static fn (mixed $byte): string => chr((int) $byte), $value));
        }
        if (!is_string($value) || $value === '') {
            return '';
        }

        // mb_convert_encoding returns a string for a valid encoding pair; checking for
        // false here was a guard against a signature this PHP no longer has.
        return @mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
    }
}
