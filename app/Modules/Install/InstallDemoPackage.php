<?php

namespace App\Modules\Install;

use App\Core\Db;
use App\Modules\Media\MediaEncoder;
use App\Modules\Media\MediaUpload;
use App\Support\RangeDownload;
use Closure;
use PharData;
use Throwable;
use ZipArchive;

/**
 * THE DEMO'S PACKAGE (PLAN.md D-215, the owner): its pictures with every size already made,
 * its documents and their words, one archive in demo_images/ of boxlet-cms-dev on GitHub. The
 * installer fetches it a piece to a request, checks it against the SHA-256 written here,
 * unpacks it into storage/demo/, and then copies each picture's sizes into place instead of
 * making them: making them took four and a half minutes here and more than a request has on
 * the owner's host (D-214).
 *
 * The package is built by tools/demo-images/pack.php, which prints the size and the hash for
 * PACKAGE below, and committed beside the pictures: a new demo is a new file, boxlet-demo-2.zip,
 * and the old one stays, so every release ever made still finds the package it names. A
 * checkout (the development site, the browser copy) has it in demo_images/ and copies it
 * rather than fetching it.
 */
final class InstallDemoPackage
{
    /** The package the installer fetches, from main, and what it must be. A new demo is a new version. */
    public const PACKAGE = [
        'version' => '1',
        'url' => 'https://raw.githubusercontent.com/BoxletCMS/boxlet-cms-dev/main/demo_images/boxlet-demo-1.zip',
        'bytes' => 56992716,
        'sha256' => '251809e82458ecb2dac70a4f3694d5a3ed462ab613103f892232630cc0233a0c',
    ];

    /** One piece of the download; four of them in a request at most. */
    private const PIECE = 8 * 1024 * 1024;

    /** Where the package is unpacked, under storage/. */
    public static function folder(string $storage): string
    {
        return $storage . '/demo';
    }

    /**
     * One request's worth of getting the package: copied from the checkout where it was built,
     * else fetched a piece at a time from where the last request stopped; once all of it is in,
     * checked and unpacked.
     *
     * $package is PACKAGE but in the tests, which fetch a small one of their own.
     *
     * @param (callable(string, int, int, string, string): array{bytes: int, total: int, etag: string, whole: bool, error: string})|null $piece
     * @param array{version: string, url: string, bytes: int, sha256: string} $package
     * @return array{ready: bool, bytes: int, error: string}
     */
    public static function fetch(string $root, string $storage, float $seconds, ?callable $piece = null, array $package = self::PACKAGE): array
    {
        $folder = self::folder($storage);
        if (is_file($folder . '/prepared/manifest.json')) {
            return ['ready' => true, 'bytes' => $package['bytes'], 'error' => ''];
        }
        $zip = $storage . '/demo-package.zip';
        $local = $root . '/demo_images/boxlet-demo-' . $package['version'] . '.zip';
        if (!is_file($zip) && is_file($local)) {
            copy($local, $zip);
        }
        $piece ??= RangeDownload::piece(...);
        $started = microtime(true);
        $have = is_file($zip) ? (int) filesize($zip) : 0;
        while ($have < $package['bytes'] && microtime(true) - $started < $seconds) {
            $part = $storage . '/demo-package.piece';
            $to = min($have + self::PIECE, $package['bytes']) - 1;
            $answer = $piece($package['url'], $have, $to, '', $part);
            if ($answer['error'] !== '' || $answer['whole'] && $have > 0) {
                @unlink($part);

                return ['ready' => false, 'bytes' => $have, 'error' => $answer['error'] !== '' ? $answer['error'] : 'the whole file came back'];
            }
            file_put_contents($zip, (string) file_get_contents($part), FILE_APPEND);
            @unlink($part);
            clearstatcache(true, $zip);
            $have = (int) filesize($zip);
        }
        if ($have < $package['bytes']) {
            return ['ready' => false, 'bytes' => $have, 'error' => ''];
        }
        if ($have !== $package['bytes'] || !hash_equals($package['sha256'], (string) hash_file('sha256', $zip))) {
            // Not what was released: fetched again from the start.
            @unlink($zip);

            return ['ready' => false, 'bytes' => 0, 'error' => 'the package is not the one released'];
        }
        try {
            self::unpack($zip, $folder);
        } catch (Throwable $e) {
            return ['ready' => false, 'bytes' => $have, 'error' => $e->getMessage()];
        }

        return ['ready' => true, 'bytes' => $have, 'error' => ''];
    }

    /**
     * What stores one picture from the package: the original as an upload is stored, and its
     * sizes copied from the package under the new picture's id, the row then saying what the
     * pipeline that made them said. A picture the package has no sizes for is made as before.
     *
     * @param Closure(string, string, float): array{id: int, complete: bool, made: int} $make
     * @return Closure(string, string, float): array{id: int, complete: bool, made: int}
     */
    public static function placer(Db $db, string $storage, string $public, string $folder, Closure $make): Closure
    {
        $upload = new MediaUpload($db, $storage, new MediaEncoder());
        $manifest = json_decode((string) @file_get_contents($folder . '/prepared/manifest.json'), true);
        $pictures = is_array($manifest['pictures'] ?? null) ? $manifest['pictures'] : [];

        return static function (string $file, string $name, float $seconds) use ($db, $upload, $public, $folder, $pictures, $make): array {
            $prepared = $pictures[substr($file, strlen($folder) + 1)] ?? null;
            $sizes = is_array($prepared) ? glob($folder . '/prepared/m/*/' . (int) $prepared['id'] . '-' . $prepared['filename'] . '.*') : [];
            if (!is_array($prepared) || $sizes === [] || $sizes === false) {
                return $make($file, $name, $seconds);
            }
            $temporary = (string) tempnam(sys_get_temp_dir(), 'demo');
            copy($file, $temporary);
            $stored = $upload->store($temporary, $name);
            @unlink($temporary);
            $id = (int) $stored['id'];
            $filename = (string) ($db->one('SELECT filename FROM media WHERE id = ?', [$id])['filename'] ?? $prepared['filename']);
            foreach ($sizes as $size) {
                $preset = basename(dirname($size));
                @mkdir($public . '/m/' . $preset, 0775, true);
                copy($size, $public . '/m/' . $preset . '/' . $id . '-' . $filename . '.' . pathinfo($size, PATHINFO_EXTENSION));
            }
            $db->query("UPDATE media SET variants_json = ?, status = 'complete' WHERE id = ?", [(string) $prepared['variants_json'], $id]);

            return ['id' => $id, 'complete' => true, 'made' => count($sizes)];
        };
    }

    /** The package and what it unpacked to, gone once the demo is in. */
    public static function clean(string $storage): void
    {
        if (is_file($storage . '/demo-package.zip')) {
            unlink($storage . '/demo-package.zip');
        }
        $folder = self::folder($storage);
        if (!is_dir($folder)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($folder);
    }

    /** ZipArchive where PHP has it; else PharData, which reads a zip whose entries are stored. */
    private static function unpack(string $zip, string $folder): void
    {
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }
        if (class_exists(ZipArchive::class)) {
            $archive = new ZipArchive();
            if ($archive->open($zip) !== true || !$archive->extractTo($folder)) {
                throw new \RuntimeException('the package could not be opened');
            }
            $archive->close();

            return;
        }
        (new PharData($zip))->extractTo($folder, null, true);
    }
}
