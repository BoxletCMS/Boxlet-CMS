<?php

namespace App\Support;

/**
 * THE VISITOR'S STYLESHEETS AS ONE FILE (PLAN.md D-204). Every page of a site links the same
 * nineteen sheets; a browser on a first visit fetched each before it drew anything, and on a
 * cPanel host Lighthouse put that at 1.2 s. One file, cache/site.<hash>.css, made from them in
 * this order the first time a page asks for it, so the cascade is theirs unchanged, and named
 * by a hash of what it holds, so a browser may keep it a year (D-203) and a change is a new
 * address.
 *
 * Read from the code's own public/assets, which ships with it; written beside tokens.css,
 * without the sheets' own comments (D-205). A relative url() in a sheet is relative to
 * assets/, and is given the way there from cache/.
 * A bundle this replaces is removed only once no kept page can still name it (PageCache's
 * day); a folder that cannot be written gives '' and the layout links the nineteen as before.
 */
final class SiteStyles
{
    /** In the order the cascade needs, which is the order they were linked in. */
    public const FILES = [
        'site.css', 'blocks-hero.css', 'blocks-hero-split.css', 'blocks.css', 'blocks-cards.css',
        'blocks-words.css', 'blocks-accordion.css', 'blocks-stats.css', 'blocks-media.css',
        'blocks-logos.css', 'blocks-embed.css', 'blocks-downloads.css', 'chrome.css',
        'chrome-footer.css', 'chrome-header.css', 'sections.css', 'sections-edges.css',
        'sections-steps.css', 'sections-columns.css',
    ];

    /** The bundle's name in $cacheDirectory, written if it is not there; '' when it cannot be. */
    public static function file(string $cacheDirectory): string
    {
        static $made = [];
        if (isset($made[$cacheDirectory])) {
            return $made[$cacheDirectory];
        }
        $css = self::css();
        $name = 'site.' . substr(hash('sha256', $css), 0, 12) . '.css';
        $path = $cacheDirectory . '/' . $name;
        if (!is_file($path)) {
            $temporary = $path . '.' . getmypid();
            if ((!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0775, true)) || @file_put_contents($temporary, $css) === false || !@rename($temporary, $path)) {
                @unlink($temporary);

                return $made[$cacheDirectory] = '';
            }
            foreach (glob($cacheDirectory . '/site.*.css') ?: [] as $old) {
                if ($old !== $path && (int) @filemtime($old) < time() - PageCache::MAX_AGE - 3600) {
                    @unlink($old);
                }
            }
        }

        return $made[$cacheDirectory] = $name;
    }

    /**
     * The nineteen, one after another, each named in a comment and without its own: the
     * sheets explain themselves at length, and a visitor fetched those words with every first
     * visit, 25 of 36 KB once compressed (D-205). The indentation goes too; nothing inside a
     * declaration is touched, and no sheet holds a comment mark inside a string.
     */
    public static function css(): string
    {
        $assets = dirname(__DIR__, 2) . '/public/assets';
        $css = '';
        foreach (self::FILES as $file) {
            $css .= "/* {$file} */\n" . self::rebase(self::bare((string) file_get_contents($assets . '/' . $file)));
        }

        return $css;
    }

    /** A sheet without its comments, its indentation and its empty lines. */
    private static function bare(string $css): string
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = (string) preg_replace('~^[ \t]+~m', '', $css);

        return (string) preg_replace('~\n{2,}~', "\n", trim($css)) . "\n";
    }

    /** A relative url() in a sheet under assets/, as seen from cache/. */
    private static function rebase(string $css): string
    {
        return (string) preg_replace_callback(
            '~url\(\s*(["\']?)(?![a-z][a-z0-9+.-]*:|/|#|\.\./)([^"\')]+)\1\s*\)~i',
            static fn (array $m): string => 'url(' . $m[1] . '../assets/' . $m[2] . $m[1] . ')',
            $css,
        );
    }
}
