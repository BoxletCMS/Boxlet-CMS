<?php

namespace App\Modules\Media;

/**
 * Whether a file may be uploaded at all, and what it may be stored as (SPEC §6).
 *
 * Three things decide, and all three must agree:
 *
 *   what it CLAIMS     the browser's filename extension — never trusted, only used to
 *                      reject early and to pick the stored extension once the sniff agrees
 *   what it IS         finfo reading the bytes, which is the only opinion that counts
 *   what we ALLOW      a closed list of formats, so a new one is a decision rather than
 *                      an accident
 *
 * HEIC is refused deliberately, even where the server could read it. Most shared hosts
 * cannot, and an upload that works for the developer and fails for the customer is worse
 * than one that always says "convert it first".
 *
 * Split from MediaUpload, which reached the 300-line rule when suggested alt text (D-025)
 * added two lines to it. The seam is a real one and not a line count: this answers what
 * may come in, while MediaUpload puts it somewhere and records it. Nothing here touches a
 * database or the filesystem beyond reading the bytes it is asked about.
 */
final class MediaFileType
{
    /** Extension => the MIME finfo must agree on. */
    private const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'avif' => 'image/avif',
    ];

    /**
     * FILES FOR VISITORS TO DOWNLOAD (PLAN.md O-17, D-126): extension => every MIME finfo may
     * report for it, the first being what the file is served as.
     *
     * Documents and archives, and nothing a browser would run or render as a page: no HTML,
     * no SVG, no script, whatever it is called (NEVER, below, still applies). The office
     * formats are ZIP archives inside, and an older libmagic reports them as exactly that,
     * so `application/zip` is accepted for them — the extension must still be on this list,
     * and a file is only ever served as an attachment, never shown.
     */
    private const DOCUMENTS = [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ];

    /**
     * Refused by name whatever the bytes say. finfo would catch a real script anyway, but
     * a file called .php that reached a servable directory through some future path is a
     * class of accident worth refusing twice.
     */
    private const NEVER = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
        'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'bash', 'htaccess', 'htm', 'html', 'svg'];

    /**
     * The extension a file may be stored under, or null when it is not allowed.
     *
     * $claimed is the client's filename; $sniffed is what finfo says about the bytes.
     */
    public static function extensionFor(string $claimed, string $sniffed): ?string
    {
        $extension = strtolower(pathinfo($claimed, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::NEVER, true)) {
            return null;
        }
        // A double extension is judged on its last part, which is what a web server would
        // do — and the sniff below has to agree anyway.
        if (!isset(self::ALLOWED[$extension])) {
            return null;
        }
        if (self::ALLOWED[$extension] !== $sniffed) {
            return null;
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * The extension a DOCUMENT may be stored under, or null when it is not one (D-126). The
     * same two tests a picture passes — the name on the list, the bytes agreeing — against
     * the documents' list.
     */
    public static function documentExtension(string $claimed, string $sniffed): ?string
    {
        $extension = strtolower(pathinfo($claimed, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::NEVER, true) || !isset(self::DOCUMENTS[$extension])) {
            return null;
        }

        return in_array($sniffed, self::DOCUMENTS[$extension], true) ? $extension : null;
    }

    /**
     * The admin's icon for a document of $extension (the owner, 2026-09-27): what kind of
     * thing it is — a text, a table, slides, an archive — from the icons the admin already
     * has (Lucide). Not the makers' logos, which are theirs; the extension printed beside the
     * icon says which program.
     */
    public static function icon(string $extension): string
    {
        return match ($extension) {
            'docx', 'odt' => 'file-type',
            'xlsx', 'ods', 'csv' => 'file-spreadsheet',
            'pptx', 'odp' => 'presentation',
            'zip' => 'file-archive',
            default => 'file-text',
        };
    }

    /** What a stored document is served as: its format's own type, not whatever finfo said. */
    public static function documentMime(string $extension): string
    {
        return self::DOCUMENTS[$extension][0] ?? 'application/octet-stream';
    }

    /**
     * Every extension the library accepts, pictures and documents, for the file chooser's
     * `accept` and for the words that say what may be uploaded.
     *
     * @return list<string>
     */
    public static function acceptedExtensions(): array
    {
        return array_merge(array_keys(self::ALLOWED), array_keys(self::DOCUMENTS));
    }

    /**
     * What finfo makes of a file's bytes.
     */
    public static function sniff(string $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file);

        return is_string($mime) ? $mime : '';
    }
}
