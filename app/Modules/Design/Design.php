<?php

namespace App\Modules\Design;

use App\Core\Db;
use App\Core\Settings;
use Throwable;

/**
 * The site's saved design: layer-1 decisions in design_tokens, compiled into the
 * stylesheet whose file name is recorded in settings.tokens_css.
 */
final class Design
{
    /** Where font files live as seen from the compiled stylesheet in public/cache. */
    private const FONTS_FROM_CACHE = '../assets/fonts';

    /**
     * The saved decisions. Missing or invalid values fall back to the default preset's,
     * so a damaged row can never break rendering.
     *
     * @return array<string, string>
     */
    public static function load(Db $db): array
    {
        $saved = [];
        foreach ($db->all('SELECT group_key, value_json FROM design_tokens') as $row) {
            $saved[(string) $row['group_key']] = json_decode((string) $row['value_json'], true);
        }

        return Tokens::validate($saved + Presets::get(Presets::DEFAULT))['decisions'];
    }

    /**
     * Stores validated decisions and publishes their stylesheet. This is where a design
     * is normally compiled; the one other path is the missing-file guard in
     * stylesheet(), which keeps a fresh deploy from rendering unstyled.
     *
     * @param array<string, string> $decisions
     * @return string the new stylesheet's file name
     */
    public static function save(Db $db, array $decisions, string $cacheDirectory): string
    {
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $db->query('DELETE FROM design_tokens');
            foreach ($decisions as $key => $value) {
                $db->query('INSERT INTO design_tokens (group_key, value_json) VALUES (?, ?)', [$key, json_encode($value, JSON_THROW_ON_ERROR)]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return self::publish($db, $decisions, $cacheDirectory);
    }

    /**
     * Compiles the decisions to cache/tokens.{hash}.css and records the file name.
     *
     * @param array<string, string> $decisions
     */
    public static function publish(Db $db, array $decisions, string $cacheDirectory): string
    {
        $file = (new TokenCompiler())->compile(
            Derived::from($decisions),
            $cacheDirectory,
            Typography::fontFaces($decisions['typography'], self::FONTS_FROM_CACHE),
        );
        Settings::set($db, 'tokens_css', $file);

        return $file;
    }

    /**
     * The stylesheet file every page links. Normally a settings read. It compiles only
     * when the recorded file is missing, after a fresh deploy or a cleared cache, so a
     * site never renders unstyled.
     */
    public static function stylesheet(Db $db, string $cacheDirectory): string
    {
        $file = Settings::get($db, 'tokens_css');
        if (is_string($file) && preg_match('~^tokens\.[0-9a-f]{12}\.css$~', $file) && is_file($cacheDirectory . '/' . $file)) {
            return $file;
        }

        return self::publish($db, self::load($db), $cacheDirectory);
    }
}
