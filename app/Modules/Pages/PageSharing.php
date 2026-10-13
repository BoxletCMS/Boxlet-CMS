<?php

namespace App\Modules\Pages;

/**
 * WHAT A LINK TO A PAGE LOOKS LIKE WHERE IT IS SHARED (PLAN.md D-242, the owner): the Open
 * Graph and Twitter tags a chat, a social network or a messenger reads for its preview, and the
 * WebSite structured data that tells a search engine what the site is called.
 *
 * Made from what the page already says about itself, so there is nothing more to fill in: the
 * title and description it gives search engines, its canonical address, its sharing picture
 * (its own, else the site's), and the site's name. Only a real page has them; an error page and
 * the admin's preview, which have no canonical address, have none.
 */
final class PageSharing
{
    /**
     * The tags, in the order a reader of the source expects: what the page is, then its
     * picture, then the card a Twitter-style reader draws. A description the page does not
     * give is left out rather than repeated from the title (D-004).
     *
     * @return list<array{attribute: string, name: string, content: string}>
     */
    public static function tags(string $title, string $description, ?string $canonical, ?string $image, string $siteName): array
    {
        if ($canonical === null) {
            return [];
        }
        $og = [
            'og:type' => 'website',
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $canonical,
            'og:site_name' => $siteName,
            'og:image' => $image ?? '',
        ];
        $tags = [];
        foreach ($og as $name => $content) {
            if ($content !== '') {
                $tags[] = ['attribute' => 'property', 'name' => $name, 'content' => $content];
            }
        }
        // A large picture when there is one to show; otherwise the small card, title and words.
        $tags[] = ['attribute' => 'name', 'name' => 'twitter:card', 'content' => $image === null ? 'summary' : 'summary_large_image'];

        return $tags;
    }

    /**
     * WebSite JSON-LD for a language's home page: the name a search engine shows for the site
     * beside its results, and the address it belongs to. '' without a name, which would be a
     * claim with nothing in it. JSON_HEX_TAG, so it cannot close the script it stands in.
     */
    public static function website(string $name, string $url, string $locale): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
            'inLanguage' => $locale,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
