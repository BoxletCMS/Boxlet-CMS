<?php

namespace App\Modules\Media;

use App\Support\Embed;
use Closure;
use RuntimeException;

/**
 * A video's own picture, fetched ONCE by the server and kept in the library (PLAN.md D-147).
 *
 * WHY THE SERVER AND NOT THE VISITOR. An embed now waits for a press before anything of
 * YouTube's or Vimeo's is loaded, so the page stays free of their storage and their requests
 * until the visitor asks. The picture that stands in its place must not come from them
 * either: a thumbnail from i.ytimg.com is still a request to Google carrying the visitor's
 * address. So the admin asks for it once, this fetches it, and from then on it is an ordinary
 * picture of the site's own — resized, AVIF, served from /m/ — that the owner may replace.
 *
 * Only a still is fetched, and only from the provider's fixed addresses built from the id
 * Embed parsed: nothing the owner typed is ever requested as it was typed.
 */
final class EmbedPoster
{
    /** A thumbnail is tens of kilobytes; anything much larger is not one. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @param Closure(string): ?string|null $get a URL's body, or null when there is none; the
     *        network by default, a test's own answers otherwise
     */
    public function __construct(private readonly ?Closure $get = null)
    {
    }

    /**
     * The picture's bytes and a name to store them under, or a refusal saying why not.
     *
     * @return array{bytes: string, name: string}
     */
    public function fetch(string $url): array
    {
        $embed = Embed::parse($url);
        if ($embed === null || !in_array($embed['provider'], ['youtube', 'vimeo'], true)) {
            throw new RuntimeException(t('embed.poster_not_video'));
        }
        $get = $this->get ?? self::download(...);

        if ($embed['provider'] === 'youtube') {
            $meta = self::json($get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $embed['id'])));
            // The largest still first; not every video has one, and YouTube then answers a
            // 120-pixel grey placeholder rather than an error, which is why its size is checked.
            $candidates = [
                'https://i.ytimg.com/vi/' . $embed['id'] . '/maxresdefault.jpg',
                'https://i.ytimg.com/vi/' . $embed['id'] . '/hqdefault.jpg',
            ];
        } else {
            $meta = self::json($get('https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode('https://vimeo.com/' . $embed['id'])));
            $thumb = is_string($meta['thumbnail_url'] ?? null) ? $meta['thumbnail_url'] : '';
            // Only an address on Vimeo's own picture host is followed.
            $candidates = preg_match('~^https://i\.vimeocdn\.com/~', $thumb) === 1 ? [$thumb] : [];
        }

        foreach ($candidates as $candidate) {
            $bytes = $get($candidate);
            if ($bytes === null || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                continue;
            }
            $size = @getimagesizefromstring($bytes);
            $extension = $size === false ? null : (['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$size['mime']] ?? null);
            if ($size === false || $extension === null || $size[0] < 200) {
                continue;
            }
            // Named after the video, which is also what the library guesses its
            // description from (MediaAlt): "Spain — Chick Corea" says more than an id.
            // Slashes out: a title like "Spain/San Sebastian/Agosto 2018" would otherwise be
            // read as a path, and only its last part kept.
            $title = is_string($meta['title'] ?? null) ? trim(str_replace(['/', '\\'], ' ', $meta['title'])) : '';

            return ['bytes' => $bytes, 'name' => ($title !== '' ? $title : $embed['provider'] . '-' . $embed['id']) . '.' . $extension];
        }

        throw new RuntimeException(t('embed.poster_unreachable'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(?string $body): array
    {
        $decoded = $body === null ? null : json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function download(string $url): ?string
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(t('embed.poster_no_network'));
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "User-Agent: Boxlet\r\n",
            'timeout' => 10,
            'follow_location' => 1,
            'max_redirects' => 3,
            'ignore_errors' => false,
        ]]);
        $body = @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);

        return $body === false ? null : $body;
    }
}
