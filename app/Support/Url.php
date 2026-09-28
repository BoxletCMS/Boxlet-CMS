<?php

namespace App\Support;

/**
 * The only place URLs are built. Templates and controllers never concatenate paths.
 *
 * The primary locale has no prefix; every other locale does (SPEC §5.1).
 */
final class Url
{
    private static string $basePath = '';
    private static string $primaryLocale = '';
    private static string $origin = '';
    private static string $stylesheet = '';
    private static string $publicPath = '';

    /** @var (\Closure(string, string): string)|null slug to whole path (PLAN.md D-129) */
    private static ?\Closure $paths = null;

    /**
     * Where a slug lives under its parents, `/usluge/web-dizajn`: page() asks this for every
     * slug it is given, so no caller builds a nested address itself (D-129). null — before
     * the router is built, and between tests — leaves a slug as it is.
     *
     * @param (\Closure(string, string): string)|null $resolver
     */
    public static function usePaths(?\Closure $resolver): void
    {
        self::$paths = $resolver;
    }

    /** A slug's whole path under its parents, `usluge/web-dizajn`, with no prefix or slash. */
    public static function pathOf(string $locale, string $slug): string
    {
        return self::$paths === null ? $slug : (self::$paths)($locale, $slug);
    }

    /**
     * Where public/ lives on disk, so versioned() can hash the files it links.
     */
    public static function usePublicPath(string $directory): void
    {
        self::$publicPath = rtrim($directory, '/');
    }

    /**
     * Sets the design stylesheet every layout links: the compiled tokens.{hash}.css, or
     * the admin preview's stylesheet.
     */
    public static function useStylesheet(string $href): void
    {
        self::$stylesheet = $href;
    }

    public static function stylesheet(): string
    {
        return self::$stylesheet !== '' ? self::$stylesheet : self::asset('cache/tokens.css');
    }

    /**
     * $url with a query string appended.
     *
     * @param array<string, string> $query
     */
    public static function withQuery(string $url, array $query): string
    {
        return $query === [] ? $url : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    /**
     * @param string $origin scheme and host for absolute URLs, e.g. https://example.com
     */
    public static function configure(string $basePath, string $primaryLocale, string $origin = ''): void
    {
        self::$basePath = rtrim($basePath, '/');
        self::$primaryLocale = $primaryLocale;
        self::$origin = rtrim($origin, '/');
    }

    /**
     * Scheme, host and non-default port, from the server's own configuration
     * (SERVER_NAME), never the client's Host header.
     */
    public static function origin(bool $https, string $serverName, int $port): string
    {
        $default = $https ? 443 : 80;

        return ($https ? 'https://' : 'http://') . $serverName . ($port === 0 || $port === $default ? '' : ':' . $port);
    }

    /**
     * Absolute canonical URL of a page: the same address page() gives, without any query
     * string, on this site's origin.
     */
    /** The site's main language, as configure() was given it. */
    public static function primaryLocale(): string
    {
        return self::$primaryLocale;
    }

    /**
     * An address this site already made — a page's, the admin's — as a full URL, for
     * somewhere with no page to resolve it against, such as an email.
     */
    public static function withOrigin(string $path): string
    {
        return self::$origin . $path;
    }

    public static function canonical(string $locale, string $slug = ''): string
    {
        return self::$origin . self::page($locale, $slug);
    }

    /**
     * URL of a front-end page. An empty slug is the locale's home page:
     * / for the primary locale, /{locale}/ for any other.
     */
    public static function page(string $locale, string $slug = ''): string
    {
        $slug = self::pathOf($locale, $slug);
        $segments = array_map(
            'rawurlencode',
            array_filter(explode('/', $slug), static fn (string $segment): bool => $segment !== ''),
        );
        $path = '/' . implode('/', $segments);
        if ($locale !== self::$primaryLocale) {
            $path = '/' . rawurlencode($locale) . $path;
        }

        return self::$basePath . $path;
    }

    /**
     * URL inside the admin, which never carries a locale prefix, from path segments:
     * admin('login'), admin('pages', 12, 'status').
     */
    public static function admin(string|int ...$segments): string
    {
        $path = '';
        foreach ($segments as $segment) {
            $segment = trim((string) $segment, '/');
            if ($segment !== '') {
                $path .= '/' . rawurlencode($segment);
            }
        }

        return self::$basePath . '/admin' . $path;
    }

    /**
     * This site's absolute base URL as the server itself is configured (SERVER_NAME,
     * never the client's Host header), for RewriteCheck in the installer.
     */
    public static function serverBase(bool $https, string $serverName, int $port): string
    {
        return self::origin($https, $serverName, $port) . self::$basePath;
    }

    /**
     * URL of a static file under public/, e.g. asset('cache/tokens.css').
     */
    public static function asset(string $path): string
    {
        return self::$basePath . '/' . ltrim($path, '/');
    }

    /**
     * A static file under public/ on this site's origin, e.g. for a sharing image.
     *
     * asset() is relative, which is right for a page linking its own stylesheet and wrong
     * for anything a machine reads somewhere else: og:image is fetched by a crawler that
     * has no page to resolve it against. Same shape as canonical(), for the same reason.
     */
    public static function absolute(string $path): string
    {
        return self::$origin . self::asset($path);
    }

    /**
     * A stylesheet or script under public/ with a hash of its content in the query
     * string, so an edited file is a new URL that no browser or proxy has cached.
     *
     * tokens.css carries its hash in the file name because it is generated; these are
     * real files shipped with the release, and renaming them would need a build step the
     * install cannot run. The query string is enough: the web server still answers from
     * disk without PHP (SPEC §5.4).
     */
    public static function versioned(string $path): string
    {
        $url = self::asset($path);
        $file = self::$publicPath . '/' . ltrim($path, '/');
        if (self::$publicPath === '' || !is_file($file)) {
            return $url;
        }
        $hash = hash_file('sha256', $file);

        return $hash === false ? $url : self::withQuery($url, ['v' => substr($hash, 0, 12)]);
    }
}
