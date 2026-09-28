<?php

namespace App\Support;

/**
 * PHP's shorthand byte sizes, and what this server will actually accept.
 *
 * Three places need these figures and all three must agree. The installer reports what a
 * server allows, the uploader refuses what it cannot receive whole, and the router tells
 * a post PHP threw away from a form whose token expired. A picture the requirements
 * screen called acceptable must not then be refused, and every refusal must name the same
 * figure the owner was told to raise.
 */
final class Bytes
{
    /**
     * A size in bytes. 0 means no limit, which is what PHP's "-1" and an empty value mean.
     */
    public static function parse(string $size): int
    {
        $size = trim($size);
        if ($size === '' || $size === '-1') {
            return 0;
        }
        $number = (int) $size;

        return match (strtolower(substr($size, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * What this server accepts, in bytes and as the server states it.
     *
     * The labels are the strings ini_get returned rather than a re-rendering of the
     * parsed number: "8M" is what the owner will look for in php.ini.
     *
     * @return array{file: int, request: int, fileLabel: string, requestLabel: string}
     */
    public static function limits(): array
    {
        return [
            'file' => self::parse((string) ini_get('upload_max_filesize')),
            'request' => self::parse((string) ini_get('post_max_size')),
            'fileLabel' => (string) ini_get('upload_max_filesize'),
            'requestLabel' => (string) ini_get('post_max_size'),
        ];
    }

    /**
     * Whether PHP threw the whole request away for being too large.
     *
     * An upload past post_max_size does not arrive truncated: PHP discards it entirely,
     * so $_POST and $_FILES are both empty and the only evidence left is that the browser
     * said it was sending more than the limit.
     *
     * This lives here, and not with the uploader, because the ROUTER is what meets it
     * first. A discarded post carries no CSRF token either, so the token check fails for
     * a reason that has nothing to do with the token — and "this form has expired" would
     * send someone to reload the page and try the very same file again.
     */
    public static function postWasDiscarded(int $contentLength, bool $hasFiles, bool $hasPost): bool
    {
        $limit = self::limits()['request'];

        return $limit > 0 && $contentLength > $limit && !$hasFiles && !$hasPost;
    }

    /**
     * A size for a person to read: "4.2 MB". Whole units below a megabyte, because a
     * decimal place on a thumbnail is noise. media.js repeats this arithmetic in the
     * browser, where the file has not been sent yet and PHP cannot be asked.
     */
    public static function human(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }
}
