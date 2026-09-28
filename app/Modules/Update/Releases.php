<?php

namespace App\Modules\Update;

use App\Support\Version;
use Closure;
use RuntimeException;

/**
 * The releases published in BoxletCMS/Boxlet-CMS, asked for only when the owner presses the
 * button (PLAN.md D-140). Nothing about the site is sent: the one request is GitHub's public
 * list of releases, and the second the chosen release's ZIP.
 *
 * A site on a pre-release is offered pre-releases too; a site on a release, releases only.
 * The ZIP is checked against the SHA-256 digest GitHub publishes for it.
 */
final class Releases
{
    public const LIST = 'https://api.github.com/repos/BoxletCMS/Boxlet-CMS/releases?per_page=30';

    /**
     * @param Closure(string): string|null $get fetches a URL's body; the network by default,
     *        a test's own list otherwise
     * @param Closure(string, string): void|null $save fetches a URL into a file
     */
    public function __construct(private readonly ?Closure $get = null, private readonly ?Closure $save = null)
    {
    }

    /**
     * The newest release above $current that this site may take, or null when it runs the
     * newest already.
     *
     * @return array{version: string, url: string, digest: string, published: string, page: string}|null
     */
    public function newest(string $current): ?array
    {
        $list = json_decode(($this->get ?? self::fetch(...))(self::LIST), true);
        if (!is_array($list)) {
            throw new RuntimeException(t('updates.github_unreadable'));
        }
        $wantPre = str_contains($current, '-');
        $best = null;
        foreach ($list as $release) {
            $tag = (string) ($release['tag_name'] ?? '');
            if (!is_array($release) || ($release['draft'] ?? false) || !Version::valid($tag)) {
                continue;
            }
            if (($release['prerelease'] ?? false) && !$wantPre) {
                continue;
            }
            if (Version::compare($tag, $current) <= 0 || ($best !== null && Version::compare($tag, $best['version']) <= 0)) {
                continue;
            }
            foreach ((array) ($release['assets'] ?? []) as $asset) {
                if (is_array($asset) && ($asset['name'] ?? '') === 'boxlet-' . $tag . '.zip') {
                    $best = [
                        'version' => $tag,
                        'url' => (string) ($asset['browser_download_url'] ?? ''),
                        'digest' => (string) ($asset['digest'] ?? ''),
                        'published' => (string) ($release['published_at'] ?? ''),
                        'page' => (string) ($release['html_url'] ?? ''),
                    ];
                }
            }
        }

        return $best !== null && str_starts_with($best['url'], 'https://github.com/BoxletCMS/Boxlet-CMS/') ? $best : null;
    }

    /**
     * The release's ZIP into $target, and its digest checked.
     *
     * @param array{version: string, url: string, digest: string, published: string, page: string} $release
     */
    public function download(array $release, string $target): void
    {
        ($this->save ?? self::fetchInto(...))($release['url'], $target);
        if (preg_match('~^sha256:([0-9a-f]{64})$~', $release['digest'], $digest) === 1
            && !hash_equals($digest[1], (string) hash_file('sha256', $target))) {
            unlink($target);
            throw new RuntimeException(t('updates.digest'));
        }
    }

    private static function fetch(string $url): string
    {
        $body = @file_get_contents($url, false, self::context());
        if ($body === false) {
            throw new RuntimeException(t('updates.github_unreachable'));
        }

        return $body;
    }

    private static function fetchInto(string $url, string $target): void
    {
        $in = @fopen($url, 'rb', false, self::context());
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException(t('updates.github_unreachable'));
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    /**
     * @return resource
     */
    private static function context()
    {
        return stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "User-Agent: Boxlet\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 20,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => false,
        ]]);
    }
}
