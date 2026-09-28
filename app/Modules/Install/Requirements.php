<?php

namespace App\Modules\Install;

use App\Support\Bytes;
use Closure;

/**
 * What must be true before installing. Every required check blocks the installer with
 * no way around it; optional capabilities only warn.
 */
final class Requirements
{
    /**
     * @param Closure(): bool $rewriteWorks
     * @return list<array{id: string, label: string, ok: bool, required: bool, detail: string}>
     */
    public static function check(string $root, string $storage, string $envPath, Closure $rewriteWorks): array
    {
        $checks = [
            self::item('php', t('install.req.php', ['version' => PHP_VERSION]), version_compare(PHP_VERSION, '8.1.0', '>='), true),
        ];
        foreach (['pdo', 'mbstring', 'fileinfo', 'json', 'session', 'dom'] as $extension) {
            $checks[] = self::item('extension', t('install.req.extension', ['name' => $extension]), extension_loaded($extension), true);
        }
        $checks[] = self::item('driver', t('install.req.driver'), extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), true);
        foreach ([$storage, $root . '/public/cache'] as $directory) {
            $checks[] = self::item('writable', t('install.req.writable', ['path' => $directory]), is_dir($directory) && is_writable($directory), true);
        }
        // Checked now, not after four steps of typing.
        $envWritable = is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath));
        $checks[] = self::item('env', t('install.req.env', ['path' => $envPath]), $envWritable, true);
        $checks[] = self::item('rewrite', t('install.req.rewrite'), $rewriteWorks(), true);

        // The page editor sends a whole page as one form; PHP silently drops fields past
        // this limit. Reported, not blocking: the editor refuses truncated saves.
        $inputVars = (int) ini_get('max_input_vars');
        $checks[] = self::item('input_vars', t('install.opt.input_vars', ['limit' => $inputVars]), $inputVars >= 1000, false);
        // An upload arrives as one request, and PHP drops anything past these limits
        // without an error — the same class of silent failure as max_input_vars. Reported
        // rather than blocking: the uploader refuses what it cannot receive whole.
        $perFile = Bytes::parse((string) ini_get('upload_max_filesize'));
        $perRequest = Bytes::parse((string) ini_get('post_max_size'));
        $checks[] = self::item('upload_limits', t('install.opt.upload_limits', [
            'file' => (string) ini_get('upload_max_filesize'),
            'request' => (string) ini_get('post_max_size'),
        ]), $perFile >= 2 * 1024 * 1024 && $perRequest >= $perFile, false);

        $checks[] = self::item('intl', t('install.opt.intl'), extension_loaded('intl'), false);
        $checks[] = self::item('images', t('install.opt.images'), extension_loaded('gd') || extension_loaded('imagick'), false);
        $checks[] = self::item('avif', t('install.opt.avif'), self::avif(), false);
        // WebP is what nearly every variant is actually served as (SPEC §5.5). Without it
        // pictures still work — the original format is always written as a fallback — but
        // every page carries several times the bytes, which is worth knowing at install
        // time rather than discovering from a slow site.
        $checks[] = self::item('webp', t('install.opt.webp'), self::webp(), false);

        return $checks;
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, required: bool, detail: string}> $checks
     */
    public static function blocked(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{id: string, label: string, ok: bool, required: bool, detail: string}
     */
    private static function item(string $id, string $label, bool $ok, bool $required): array
    {
        return ['id' => $id, 'label' => $label, 'ok' => $ok, 'required' => $required, 'detail' => t("install.req.{$id}_detail")];
    }

    /**
     * Whether this server can write WebP, which is what nearly every variant is served
     * as. Probed the same way as AVIF: Imagick is optional, so it is referenced by name
     * rather than as a class.
     */
    private static function webp(): bool
    {
        if (function_exists('imagewebp')) {
            return true;
        }
        $imagick = 'Imagick';
        if (class_exists($imagick)) {
            return in_array('WEBP', $imagick::queryFormats('WEBP'), true);
        }

        return false;
    }

    private static function avif(): bool
    {
        if (function_exists('imageavif')) {
            return true;
        }
        // Imagick is optional, so it is referenced by name rather than as a class.
        $imagick = 'Imagick';
        if (class_exists($imagick)) {
            return in_array('AVIF', $imagick::queryFormats('AVIF'), true);
        }

        return false;
    }
}
