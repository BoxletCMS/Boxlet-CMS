<?php

namespace App\Modules\Install;

use App\Core\Db;
use App\Modules\Demo\DemoPictures;
use App\Modules\Demo\DemoSite;
use App\Modules\Media\MediaEncoder;
use App\Modules\Media\MediaUpload;
use App\Modules\Media\MediaVariants;
use App\Modules\Media\MediaWriter;
use Closure;

/**
 * THE DEMO'S PICTURES, A FEW TO A REQUEST (PLAN.md D-214). Fifty pictures with every size of
 * each took minutes in the installer's one request, and the owner's host answered a 524 after
 * Cloudflare's hundred seconds. So the installer takes them here, between making the database
 * and finishing: each request stores pictures and makes their sizes for at most SECONDS, says
 * how far it got, and the page sends the next.
 *
 * Nothing is lost when a request is cut short: a picture already stored is found again by its
 * content (MediaUpload answers with the one it has) and its sizes go on from the last one made
 * (MediaVariants), so the same picture is simply taken again.
 */
final class InstallDemo
{
    /** One request's time with the pictures: inside a host's thirty seconds and Cloudflare's hundred. */
    public const SECONDS = 12.0;

    /**
     * Less time than this left in a request, and no picture is started: a picture is stored
     * before its sizes are made, and with too little time left it was stored with none (the
     * sizes' own reserve is two seconds) and counted as tried. On the owner's host that left
     * most of the demo's pictures in the library with no size at all (D-214).
     */
    private const LEAST = 4.0;

    /** A picture whose sizes stop coming — this many requests in a row with none made — is left to Finish in Media. */
    private const STALLS = 3;

    /**
     * Every picture the demo's pages name in $lang, as files: what the requests work through.
     * $root is where they are (DemoPictures): the unpacked package, or demo_images/ when empty.
     *
     * @return list<string> paths under $root
     */
    public static function pictures(string $lang, string $root = ''): array
    {
        $catalogue = DemoPictures::catalogue($root);
        $files = [];
        foreach (DemoPictures::named(DemoSite::pages($lang))['pictures'] as $name) {
            if (isset($catalogue[$name])) {
                $files[] = $catalogue[$name]['file'];
            }
        }

        return $files;
    }

    /** Before the first request: the package to fetch, nothing placed. */
    public const START = ['stage' => 'fetch', 'done' => [], 'stalls' => [], 'sizes' => 0, 'bytes' => 0, 'failures' => 0, 'none' => false, 'error' => ''];

    /** Requests in a row the package may fail to come in before the demo goes without its pictures. */
    private const FAILURES = 5;

    /**
     * One request's worth of the demo (D-215): the package fetched, a piece at a time; then its
     * pictures placed, a few at a time. With $store given (the tests) nothing is fetched and the
     * pictures are the checkout's.
     *
     * @param array{stage: string, done: array<string, int>, stalls: array<string, int>, sizes: int, bytes: int, failures: int, none: bool, error: string} $state
     * @param (Closure(string, string, float): array{id: int, complete: bool, made: int})|null $store
     * @return array{stage: string, done: array<string, int>, stalls: array<string, int>, sizes: int, bytes: int, failures: int, none: bool, error: string}
     */
    public static function advance(string $root, string $storage, string $public, Db $db, string $lang, array $state, ?Closure $store): array
    {
        if ($store === null && $state['stage'] === 'fetch') {
            $fetched = InstallDemoPackage::fetch($root, $storage, self::SECONDS);
            $state['bytes'] = $fetched['bytes'];
            // What went wrong, shown on the page: the owner saw a bar standing at 0 MB and no word.
            $state['error'] = $fetched['error'];
            if ($fetched['ready']) {
                $state['stage'] = 'pictures';
            } elseif ($fetched['error'] !== '') {
                error_log('Demo package: ' . $fetched['error']);
                $state['failures'] += 1;
                // Without its pictures rather than not at all: a host that cannot reach GitHub.
                $state['none'] = $state['failures'] >= self::FAILURES;
            }

            return $state;
        }
        $folder = self::source($storage, $store);
        $placer = $store ?? InstallDemoPackage::placer($db, $storage, $public, $folder, self::storer($db, $storage, $public));
        $batch = self::batch(self::pictures($lang, $folder), ['done' => $state['done'], 'stalls' => $state['stalls'], 'sizes' => $state['sizes']], $placer);

        return ['stage' => 'pictures'] + $batch + $state;
    }

    /**
     * The progress the session kept, in its shape: a value missing or of the wrong kind is
     * START's, as on the first request.
     *
     * @return array{stage: string, done: array<string, int>, stalls: array<string, int>, sizes: int, bytes: int, failures: int, none: bool, error: string}
     */
    public static function state(mixed $saved): array
    {
        $saved = is_array($saved) ? $saved : [];
        $ints = static fn (mixed $list): array => is_array($list) ? array_map('intval', array_filter($list, 'is_numeric')) : [];

        return [
            'stage' => ($saved['stage'] ?? '') === 'pictures' ? 'pictures' : 'fetch',
            'done' => $ints($saved['done'] ?? null),
            'stalls' => $ints($saved['stalls'] ?? null),
            'sizes' => (int) ($saved['sizes'] ?? 0),
            'bytes' => (int) ($saved['bytes'] ?? 0),
            'failures' => (int) ($saved['failures'] ?? 0),
            'none' => (bool) ($saved['none'] ?? false),
            'error' => (string) ($saved['error'] ?? ''),
        ];
    }

    /**
     * Whether the demo can be put together: every picture in, or none coming.
     *
     * @param array{stage: string, done: array<string, int>, stalls: array<string, int>, sizes: int, bytes: int, failures: int, none: bool, error: string} $state
     */
    public static function finished(array $state, string $lang, string $storage, ?Closure $store): bool
    {
        return $state['none'] || ($state['stage'] === 'pictures' && count($state['done']) >= count(self::pictures($lang, self::source($storage, $store))));
    }

    /** Where the pictures are: the unpacked package, or the checkout's demo_images/ for the tests. */
    public static function source(string $storage, ?Closure $store): string
    {
        return $store === null ? InstallDemoPackage::folder($storage) : '';
    }

    /**
     * The demo page's bar and its words: megabytes while the package comes, pictures after.
     *
     * @param array{stage: string, done: array<string, int>, stalls: array<string, int>, sizes: int, bytes: int, failures: int, none: bool, error: string} $state
     * @return array{value: int, max: int, text: string}
     */
    public static function progress(array $state, string $lang, string $storage, ?Closure $store): array
    {
        if ($state['stage'] === 'fetch' && $store === null) {
            $total = max(1, (int) round(InstallDemoPackage::PACKAGE['bytes'] / 1048576));
            $done = (int) round($state['bytes'] / 1048576);

            $text = t('install.demo.fetching', ['done' => $done, 'total' => $total]);
            if ($state['error'] !== '') {
                $text .= ' ' . t('install.demo.fetch_failed', ['error' => $state['error'], 'try' => $state['failures'], 'tries' => self::FAILURES]);
            }

            return ['value' => $done, 'max' => $total, 'text' => $text];
        }
        $total = count(self::pictures($lang, self::source($storage, $store)));

        return ['value' => count($state['done']), 'max' => max(1, $total), 'text' => t('install.demo.count', ['done' => count($state['done']), 'total' => $total, 'sizes' => $state['sizes']])];
    }

    /**
     * What stores one picture and makes as many of its sizes as $seconds allow.
     *
     * @return Closure(string, string, float): array{id: int, complete: bool, made: int}
     */
    public static function storer(Db $db, string $storage, string $public): Closure
    {
        $encoder = new MediaEncoder();
        $upload = new MediaUpload($db, $storage, $encoder);
        $variants = new MediaVariants($db, $encoder, new MediaWriter($encoder), $storage, $public);

        return static function (string $file, string $name, float $seconds) use ($upload, $variants): array {
            // A copy, because an upload is moved into storage and the package's file must stay.
            $temporary = (string) tempnam(sys_get_temp_dir(), 'demo');
            copy($file, $temporary);
            $stored = $upload->store($temporary, $name);
            @unlink($temporary);
            $id = (int) $stored['id'];
            $made = $variants->generate($id, max(1.0, $seconds));

            return ['id' => $id, 'complete' => (bool) $made['complete'], 'made' => count($made['made'])];
        };
    }

    /**
     * One request's worth: pictures taken in order from where the last request stopped, until
     * the time is spent or none is left. A picture stays in the list while its sizes come, over
     * as many requests as that takes; only one that stops making any is let go.
     *
     * @param list<string> $files
     * @param array{done: array<string, int>, stalls: array<string, int>, sizes: int} $state file => media id, file => requests in a row with no size made, sizes made so far
     * @param Closure(string, string, float): array{id: int, complete: bool, made: int} $store
     * @return array{done: array<string, int>, stalls: array<string, int>, sizes: int}
     */
    public static function batch(array $files, array $state, Closure $store, float $seconds = self::SECONDS): array
    {
        $started = microtime(true);
        foreach ($files as $file) {
            if (isset($state['done'][$file])) {
                continue;
            }
            $left = $seconds - (microtime(true) - $started);
            if ($left < self::LEAST) {
                break;
            }
            try {
                $stored = $store($file, basename($file), $left);
            } catch (\Throwable $e) {
                // A server that cannot take a picture still gets the demo: that field stays empty.
                error_log('Demo picture ' . basename($file) . ': ' . $e->getMessage());
                $state['done'][$file] = 0;
                continue;
            }
            $state['sizes'] += $stored['made'];
            $state['stalls'][$file] = $stored['made'] > 0 ? 0 : ($state['stalls'][$file] ?? 0) + 1;
            if ($stored['complete'] || $state['stalls'][$file] >= self::STALLS) {
                $state['done'][$file] = $stored['id'];
            }
        }

        return $state;
    }

    /**
     * What the demo's seed stores its files with once the pictures are in: a picture taken
     * already is answered with its id, anything else (the documents) stored as it comes.
     *
     * @param array<string, int> $done file => media id, 0 for one the server refused
     * @param Closure(string, string): int $importer
     * @return Closure(string, string): int
     */
    public static function seedStore(array $done, Closure $importer): Closure
    {
        return static function (string $file, string $name) use ($done, $importer): int {
            if (array_key_exists($file, $done)) {
                if ($done[$file] === 0) {
                    throw new \RuntimeException('refused when it was taken');
                }

                return $done[$file];
            }

            return $importer($file, $name);
        };
    }
}
