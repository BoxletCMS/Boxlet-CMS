<?php

namespace App\Modules\Media;

use App\Core\Db;
use App\Modules\Pages\Slug;
use App\Support\Bytes;
use RuntimeException;

/**
 * Storing an uploaded picture, and putting different bytes behind an existing one (SPEC §6).
 *
 * WHETHER a file may be uploaded at all is MediaFileType: the agreement between what it
 * claims, what finfo says it is, and the closed list of formats. This half takes a file
 * that has passed and gives it somewhere to live.
 *
 * The stored filename is generated here, never taken from the client: a name arriving as
 * "photo.php.jpg", "../../evil" or 300 bytes of Unicode is a name this never has to
 * reason about, because it is discarded.
 */
final class MediaUpload
{
    public function __construct(
        private readonly Db $db,
        private readonly string $storagePath,
        private readonly MediaEncoder $encoder,
    ) {
    }

    /**
     * Why an upload failed, in words, or null when it did not.
     *
     * PHP's own UPLOAD_ERR_* codes are reported here rather than in the controller,
     * because the two size errors have to name the same limits as everything else. The
     * limits themselves live in Bytes: the router needs them before any of this runs, to
     * tell a discarded post from an expired form.
     */
    public static function problem(int $errorCode): ?string
    {
        $limits = Bytes::limits();

        return match ($errorCode) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE => t('media.too_large', ['limit' => $limits['fileLabel']]),
            UPLOAD_ERR_FORM_SIZE => t('media.too_large', ['limit' => $limits['fileLabel']]),
            UPLOAD_ERR_PARTIAL => t('media.incomplete_upload'),
            UPLOAD_ERR_NO_FILE => t('media.no_file'),
            default => t('media.storage_unwritable'),
        };
    }

    /**
     * The name a picture is stored under: derived from what the person called it, so the
     * library is searchable, but generated rather than accepted.
     */
    public static function filenameFor(string $originalName): string
    {
        $base = Slug::fromTitle(pathinfo($originalName, PATHINFO_FILENAME));
        if ($base === '') {
            $base = 'image';
        }

        return substr($base, 0, 80);
    }

    /**
     * The row for a file already stored with these bytes, or null.
     *
     * @return array<string, mixed>|null
     */
    public function existing(string $hash): ?array
    {
        return $this->db->one('SELECT * FROM media WHERE hash = ?', [$hash]);
    }

    /**
     * Stores the original and records it, or returns the existing row for identical bytes.
     *
     * The original is never modified and never public: it goes to storage/uploads/, which
     * is outside the web root, and only generated variants are served (D-020).
     *
     * @return array{id: int, duplicate: bool}
     */
    public function store(string $temporaryFile, string $originalName): array
    {
        $sniffed = MediaFileType::sniff($temporaryFile);
        // A DOCUMENT goes its own way (D-126): nothing to measure, nothing to make from it.
        $document = MediaFileType::documentExtension($originalName, $sniffed);
        if ($document !== null) {
            return $this->storeDocument($temporaryFile, $originalName, $document);
        }
        $extension = MediaFileType::extensionFor($originalName, $sniffed);
        if ($extension === null) {
            throw new RuntimeException(t('media.refused', ['type' => $sniffed === '' ? '?' : $sniffed]));
        }
        // A host with neither encoder is real shared hosting. inspect() would still succeed
        // — getimagesize is core, not GD — so without this the upload APPEARS to work: the
        // original is stored, a row is written, and generation then makes nothing at all.
        // The library shows a card with no thumbnail and nothing says why.
        if ($this->encoder->driver() === null) {
            throw new RuntimeException(t('media.refused_no_encoder'));
        }
        if ($extension === 'avif' && !$this->encoder->supports('avif')) {
            throw new RuntimeException(t('media.refused_avif'));
        }

        $hash = (string) sha1_file($temporaryFile);
        $existing = $this->existing($hash);
        if ($existing !== null) {
            return ['id' => (int) $existing['id'], 'duplicate' => true];
        }

        $info = $this->encoder->inspect($temporaryFile);
        $filename = self::filenameFor($originalName);
        $relative = 'uploads/' . $hash . '.' . $extension;
        $target = $this->storagePath . '/' . $relative;

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }
        if (!self::place($temporaryFile, $target)) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }

        $this->db->query(
            'INSERT INTO media (filename, original_name, path, mime, size, width, height, hash, created_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $filename,
                substr($originalName, 0, 255),
                $relative,
                $info['mime'],
                (int) filesize($target),
                $info['width'],
                $info['height'],
                $hash,
                gmdate('Y-m-d H:i:s'),
                'incomplete',
            ],
        );

        $id = (int) $this->db->lastInsertId();
        // A library full of pictures with nothing to say is worse than a guess the owner
        // can correct, and every guess is marked as one (D-025).
        MediaAlt::fill($this->db, $id, $target, $originalName);

        return ['id' => $id, 'duplicate' => false];
    }

    /**
     * A file for visitors to download (PLAN.md O-17, D-126): stored like a picture's original
     * — under its hash, outside the web root, never modified — and recorded as a `file`.
     *
     * No encoder is asked and no size is measured: there is nothing to make from it. So it is
     * complete the moment it is stored, and nothing that finishes or remakes pictures will
     * ever pick it up. It is served whole, as an attachment, by DownloadController.
     *
     * @return array{id: int, duplicate: bool}
     */
    private function storeDocument(string $temporaryFile, string $originalName, string $extension): array
    {
        $hash = (string) sha1_file($temporaryFile);
        $existing = $this->existing($hash);
        if ($existing !== null) {
            return ['id' => (int) $existing['id'], 'duplicate' => true];
        }

        $relative = 'uploads/' . $hash . '.' . $extension;
        $target = $this->storagePath . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }
        if (!self::place($temporaryFile, $target)) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }

        $filename = Slug::fromTitle(pathinfo($originalName, PATHINFO_FILENAME));
        $this->db->query(
            'INSERT INTO media (filename, original_name, path, mime, size, width, height, hash, created_at, status, kind)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                substr($filename === '' ? 'file' : $filename, 0, 80),
                substr($originalName, 0, 255),
                $relative,
                MediaFileType::documentMime($extension),
                (int) filesize($target),
                0,
                0,
                $hash,
                gmdate('Y-m-d H:i:s'),
                'complete',
                'file',
            ],
        );

        return ['id' => (int) $this->db->lastInsertId(), 'duplicate' => false];
    }

    /**
     * Puts different bytes behind an existing picture, keeping its id.
     *
     * The id is the whole point: every page showing this picture shows the new one, with
     * no page edited and nothing to go and find.
     *
     * THE NAME FOLLOWS THE NEW FILE (the owner, 2026-09-27). It used to be kept as "what the
     * owner called this picture" — but nothing lets the owner name a picture; the name was
     * generated from the first file, so after a replacement the library titled a new
     * picture with an old file's name. It is generated from the new file exactly as an
     * upload's is. The caller's forgetVariants() removes the old files by the old name.
     *
     * EVERYTHING THAT CAN REFUSE DOES SO BEFORE ANYTHING IS WRITTEN OR REMOVED, so a
     * rejected replacement leaves the picture exactly as it was.
     *
     * @return array<string, mixed> the row as it stood, so the caller can remove what was
     *                              generated from the old bytes
     */
    public function replace(int $mediaId, string $temporaryFile, string $originalName): array
    {
        $existing = $this->db->one('SELECT * FROM media WHERE id = ?', [$mediaId]);
        if ($existing === null) {
            throw new RuntimeException(t('media.not_found'));
        }

        $sniffed = MediaFileType::sniff($temporaryFile);
        $extension = MediaFileType::extensionFor($originalName, $sniffed);
        if ($extension === null) {
            throw new RuntimeException(t('media.refused', ['type' => $sniffed === '' ? '?' : $sniffed]));
        }
        // Here too, and for a worse reason: replace() clears variants_json and deletes the
        // old original, so accepting bytes this server cannot process would destroy a
        // working picture and put an unusable one in its place. Refused before anything is
        // written or removed, like every other refusal in this method.
        if ($this->encoder->driver() === null) {
            throw new RuntimeException(t('media.refused_no_encoder'));
        }
        if ($extension === 'avif' && !$this->encoder->supports('avif')) {
            throw new RuntimeException(t('media.refused_avif'));
        }

        $hash = (string) sha1_file($temporaryFile);
        $duplicate = $this->existing($hash);
        if ($duplicate !== null && (int) $duplicate['id'] !== $mediaId) {
            // hash is unique, so this would fail at the database anyway. Naming the
            // picture that already holds those bytes is more use than a constraint error.
            throw new RuntimeException(t('media.replace_duplicate', ['name' => (string) $duplicate['filename']]));
        }

        $info = $this->encoder->inspect($temporaryFile);
        $relative = 'uploads/' . $hash . '.' . $extension;
        $target = $this->storagePath . '/' . $relative;
        if (!self::place($temporaryFile, $target)) {
            throw new RuntimeException(t('media.storage_unwritable'));
        }

        $this->db->query(
            'UPDATE media SET filename = ?, original_name = ?, path = ?, mime = ?, size = ?, width = ?, height = ?,
                    hash = ?, variants_json = NULL, status = ? WHERE id = ?',
            [
                self::filenameFor($originalName),
                substr($originalName, 0, 255),
                $relative,
                $info['mime'],
                (int) filesize($target),
                $info['width'],
                $info['height'],
                $hash,
                'incomplete',
                $mediaId,
            ],
        );

        // New bytes may refresh a suggestion nobody has confirmed, and never touch an alt
        // the owner wrote or confirmed (D-025).
        MediaAlt::fill($this->db, $mediaId, $target, $originalName);

        // The old original is nothing's source now. Removed after the row points at the
        // new one, so a failure halfway leaves one file too many rather than a row whose
        // original has gone.
        $old = $this->storagePath . '/' . (string) $existing['path'];
        if ($old !== $target && is_file($old)) {
            @unlink($old);
        }

        return $existing;
    }

    /**
     * move_uploaded_file for a real upload, rename for anything else — so tests and the
     * development photograph script use the same path as a browser does.
     */
    private static function place(string $from, string $to): bool
    {
        if (is_uploaded_file($from)) {
            return move_uploaded_file($from, $to);
        }

        return @copy($from, $to);
    }
}
