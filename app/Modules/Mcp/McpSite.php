<?php

namespace App\Modules\Mcp;

use App\Core\Container;
use App\Core\Db;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Appearance\DesignLibrary;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\DesignExport;
use App\Modules\Design\DesignSet;
use App\Modules\Design\DesignVocabulary;
use App\Modules\Media\MediaController;
use App\Modules\Media\MediaMeta;
use App\Modules\Media\MediaVariants;
use App\Support\PublicAddress;
use App\Support\Url;
use RuntimeException;

/**
 * The site, its pictures and its design over MCP (PLAN.md O-78).
 *
 * A picture is fetched by the server from a public https address (PublicAddress, every hop of a
 * redirect checked again) and stored as an upload is (MediaUpload), its sizes made as the clock
 * allows. A design set is read by DesignSet::parse, the reader an import and a file in
 * designs/custom/ meet, and KEPT — as a character when it has a composition, else as a saved
 * design — for the owner to try in Appearance; never applied (the owner, 2026-10-23).
 *
 * @phpstan-import-type Key from McpKeys
 */
final class McpSite
{
    /** Larger than any picture worth putting on a page, and smaller than a shared host's limit. */
    public const MAX_BYTES = 15 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param Key $key
     * @return array<string, mixed>
     */
    public function site(array $key): array
    {
        $db = $this->db();
        $languages = [];
        foreach ($this->container->get('locales') as $locale) {
            $languages[] = ['code' => (string) $locale['code'], 'label' => (string) $locale['label'], 'main' => (int) $locale['is_primary'] === 1];
        }
        usort($languages, static fn (array $a, array $b): int => $b['main'] <=> $a['main']);

        return [
            'name' => Settings::text($db, 'site_name'),
            'url' => Url::canonical(Url::primaryLocale()),
            'languages' => $languages,
            'character' => Composition::active($db),
            'pages' => (int) ($db->one('SELECT COUNT(*) AS n FROM pages')['n'] ?? 0),
            'pictures' => (int) ($db->one("SELECT COUNT(*) AS n FROM media WHERE kind = 'picture'")['n'] ?? 0),
            'this_key' => ['writes' => $key['can_write'], 'publishes' => $key['can_publish']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function media(string $search, int $limit): array
    {
        return array_values(array_map($this->item(...), $this->container->get('media_library')->all($search, $limit)));
    }

    /**
     * One picture or file as list_media gives it.
     *
     * @param array<string, mixed> $row a media row
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $card = MediaController::card($row);
        $alts = [];
        foreach (MediaMeta::forPicture($this->db(), $card['id']) as $locale => $meta) {
            $alts[$locale] = $meta['alt'];
        }
        $url = $card['kind'] === 'file' ? $card['download'] : MediaVariants::url($row, 'full');

        return [
            'id' => $card['id'],
            'kind' => $card['kind'],
            'file' => $card['filename'] . ($card['ext'] !== '' ? '.' . $card['ext'] : ''),
            'width' => $card['width'],
            'height' => $card['height'],
            'alt' => $alts,
            'url' => $url === null ? null : Url::withOrigin($url),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function design(): array
    {
        $characters = [];
        foreach (Characters::names() as $id) {
            $characters[] = ['id' => $id, 'name' => Characters::label($id), 'by_ai' => Characters::byAi($id)];
        }
        // The designs kept in the library, so a client can see what it kept and what the owner
        // has (D-290); their content is the owner's to bring back in Appearance.
        $saved = array_map(static fn (array $design): array => [
            'id' => $design['id'],
            'name' => $design['name'],
            'from_character' => $design['character'],
            'by_ai' => $design['by_ai'],
        ], DesignLibrary::all($this->db()));

        return [
            'character' => Composition::active($this->db()),
            'set' => json_decode(DesignExport::forSite($this->db(), 'site-design', Settings::text($this->db(), 'site_name')), true),
            'characters' => $characters,
            'saved_designs' => $saved,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function designSchema(): array
    {
        return DesignVocabulary::schema($this->container->get('blocks'));
    }

    /**
     * @return array<string, mixed>
     */
    public function addPicture(string $url, string $alt, string $locale): array
    {
        $locales = array_map(static fn (array $l): string => (string) $l['code'], $this->container->get('locales'));
        $locale = $locale === '' ? Url::primaryLocale() : $locale;
        if (!in_array($locale, $locales, true)) {
            throw new RuntimeException("The site has no language {$locale}; get_site lists them.");
        }
        $started = microtime(true);
        /** @var \Closure(string): array{bytes: string, type: string} $fetch */
        $fetch = $this->container->get('mcp_fetch');
        $found = $fetch($url);
        $extension = self::TYPES[$found['type']] ?? throw new RuntimeException('That address is not a picture this site takes (JPEG, PNG, WebP, GIF, AVIF).');
        $name = pathinfo(rawurldecode((string) parse_url($url, PHP_URL_PATH)), PATHINFO_FILENAME);
        $name = (preg_replace('~[^\pL\pN._-]+~u', '-', $name) ?? '') ?: 'picture';
        $temporary = tempnam((string) $this->container->get('config')->get('app.storage_path'), 'mcp');
        try {
            if ($temporary === false || file_put_contents($temporary, $found['bytes']) === false) {
                throw new RuntimeException('The site could not write the picture down.');
            }
            $result = $this->container->get('media_upload')->store($temporary, $name . '.' . $extension);
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }
        $id = (int) $result['id'];
        if (!$result['duplicate']) {
            $this->container->get('media_variants')->generate($id, MediaController::budget($started));
            Activity::record($this->db(), 'media', 'ai_added', $id, $name);
        }
        if ($alt !== '') {
            MediaMeta::save($this->db(), $id, $locale, mb_substr($alt, 0, 500), '');
        }

        $row = $this->container->get('media_library')->find($id);

        return ['id' => $id, 'already_there' => $result['duplicate'], 'picture' => $row === null ? null : $this->item($row)];
    }

    /**
     * @return array<string, mixed>
     */
    public function keepDesignSet(mixed $set): array
    {
        if (!is_array($set)) {
            throw new RuntimeException('set must be a boxlet-design-set object; get_design_set_schema describes it.');
        }
        $read = DesignSet::parse((string) json_encode($set, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->container->get('blocks'));
        $parsed = $read['set'];
        if ($parsed === null) {
            throw new RuntimeException('Not kept: ' . implode(' ', $read['errors']));
        }
        $db = $this->db();
        $name = $parsed['name']['en'] ?? (string) reset($parsed['name']);
        if ($parsed['composition'] !== null) {
            // As an imported character: the owner may delete it as any other they brought in.
            // Marked as an AI client's (D-290), on its tile at the top of Appearance.
            $id = Characters::addImported($db, $parsed, 'ai');
            $kept = ['as' => 'character', 'id' => $id, 'try_it' => Url::withOrigin(Url::admin('appearance'))];
        } else {
            $name = DesignLibrary::cleanName($name) !== '' ? DesignLibrary::cleanName($name) : 'AI design';
            // A saved design is at the foot of Appearance, under Your designs: the link lands on
            // that group, where the owner did not think to look (D-290).
            $kept = [
                'as' => 'saved design',
                'id' => DesignLibrary::save($db, $name, $parsed['decisions'], $parsed['look'], Composition::active($db), $parsed['dark'], true),
                'try_it' => Url::withOrigin(Url::admin('appearance') . '#group-designs'),
            ];
        }
        Activity::record($db, 'design', 'ai_added', null, $name);

        return $kept + ['name' => $name, 'warnings' => $read['warnings'], 'where' => $kept['as'] === 'character' ? 'Appearance, among the characters' : 'Appearance, at the foot, under Your designs'];
    }

    /**
     * A picture from a public https address: its bytes and its sniffed type. Every redirect is
     * checked as the first address was; three at most.
     *
     * @return array{bytes: string, type: string}
     */
    public static function fetch(string $url): array
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $address = PublicAddress::check($url);
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('curl could not start.');
            }
            $bytes = '';
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$address['pin']],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Boxlet',
                CURLOPT_HEADER => false,
                // Stops at the limit rather than reading a file that has no end.
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$bytes): int {
                    $bytes .= $chunk;

                    return strlen($bytes) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $next = curl_getinfo($handle, CURLINFO_REDIRECT_URL);
            $error = curl_error($handle);
            curl_close($handle);
            if (strlen($bytes) > self::MAX_BYTES) {
                throw new RuntimeException('That picture is larger than 15 MB.');
            }
            if ($ok === false) {
                throw new RuntimeException(t('address.unreachable', ['host' => $address['host'], 'error' => $error]));
            }
            if ($status >= 300 && $status < 400 && is_string($next) && $next !== '') {
                $url = $next;
                continue;
            }
            if ($status !== 200) {
                throw new RuntimeException("{$address['host']} answered {$status}.");
            }
            $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

            return ['bytes' => $bytes, 'type' => is_string($type) ? $type : ''];
        }

        throw new RuntimeException('Too many redirects.');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
