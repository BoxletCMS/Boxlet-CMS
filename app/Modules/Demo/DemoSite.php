<?php

namespace App\Modules\Demo;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\Composition;
use App\Modules\Design\SectionStyle;
use App\Modules\Forms\Form;
use App\Modules\Menus\Menu;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageLinks;
use App\Modules\Settings\SiteChrome;
use App\Support\RichText;
use RuntimeException;

/**
 * The demo ("golden") site: published pages that between them use every block type,
 * every layout and every section style value. A new install can start from it, and
 * `php migrations/seed.php` adds it to an empty site, so any change to blocks or design
 * can be checked visually in seconds.
 */
final class DemoSite
{
    /**
     * Creates the demo pages in $locale. Refuses a site that already has pages rather
     * than mixing demo content into real content.
     *
     * @return int the number of pages created
     */
    public static function seed(Db $db, Blocks $registry, string $locale): int
    {
        if ((int) ($db->one('SELECT COUNT(*) AS n FROM pages')['n'] ?? 0) > 0) {
            throw new RuntimeException('The site already has pages. The demo is only added to a site without any.');
        }

        // What a person using the editor would get. A seed that stored only the keys it
        // names left every other key at the CLOSED-SET DEFAULT — normal, left, none — which
        // is not what the active character composes, so every demo section counted as
        // hand-tuned and announced itself: measured at 21 of 21 panels open, with rhythm
        // differing in 16 and align in 13 purely from defaults nobody chose.
        $character = Composition::active($db);

        $pages = self::pages();
        // Every page first, so a link can refer to one seeded after it (PLAN.md D-034). A
        // new page's group is its own id.
        $ids = [];
        foreach ($pages as $page) {
            $ids[$page['slug']] = Page::create($db, $registry, $locale, $page['title'], $page['slug'], null, []);
        }
        $reference = static fn (array $match): string => isset($ids[$match[1]]) ? PageLinks::to($ids[$match[1]]) : $match[0];
        // One contact form, in the demo's language, for every Form block the seed holds.
        $form = Form::create($db, $locale, 'Contact');

        foreach ($pages as $page) {
            $id = $ids[$page['slug']];
            $blocks = [];
            foreach ($page['blocks'] as [$type, $content, $style, $layout]) {
                $content = self::link($registry->get($type)['fields'], $content, $reference);
                if (($content['form'] ?? null) === 'demo:form') {
                    $content['form'] = $form;
                }
                $blocks[] = [
                    // Never rendered in an editor: the key only has to exist and differ.
                    'key' => \App\Modules\Pages\BlockForm::key(null, count($blocks)),
                    'id' => null,
                    'type' => $type,
                    'content' => $registry->normalize($type, $content),
                    // The keys the seed names win; every other key comes from the character's
                    // composition for this block type. `+` keeps the left-hand value, which
                    // is exactly that rule.
                    'style' => SectionStyle::normalize($style + Composition::style($character, $type)),
                    'layout' => $registry->layout($type, $layout),
                ];
            }
            Page::update($db, $registry, $id, [
                'title' => $page['title'],
                'slug' => $page['slug'],
                'parent_id' => null,
                'status' => 'draft',
                // The demo pages give no meta of their own: each falls back to its title.
                'seo_json' => '{}',
            ], $blocks);
            Page::setStatus($db, $id, true);
        }

        self::menu($db, $locale, $ids);

        return count($pages);
    }

    /**
     * A navigation menu over the demo pages, and the chrome pointed at it.
     *
     * WITHOUT THIS THE DEMO HAS NO HEADER AT ALL. An empty header is deliberately not drawn
     * (PLAN.md D-032), and a fresh install had no menu, no logo and no button — so the one
     * thing a new owner sees first was a site with no navigation, and the Appearance screen
     * had no header to show them. The demo exists to be a site worth looking at; a site
     * without navigation is not one.
     *
     * Every demo page but the home page, whose name is the logo's job, in the order they are
     * seeded.
     *
     * @param array<string, int> $ids slug => page id
     */
    private static function menu(Db $db, string $locale, array $ids): void
    {
        $menu = Menu::create($db, $locale, 'Main');
        foreach ($ids as $slug => $id) {
            if ($slug !== '') {
                Menu::addItem($db, $menu, null, $id, null, null);
            }
        }
        SiteChrome::saveShared($db, 'Main');
    }

    /**
     * The seed's `demo:{slug}` links turned into page references, in link fields and rich
     * text alike, and inside a repeater's items the same way as at the top: the Columns
     * block's links would otherwise have been stored as `demo:about`, which no link rule
     * accepts, and drawn as nothing.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $content
     * @param callable(array<int|string, string>): string $reference
     * @return array<string, mixed>
     */
    private static function link(array $fields, array $content, callable $reference): array
    {
        foreach ($fields as $name => $field) {
            if ($field['type'] === 'link' && is_array($content[$name] ?? null) && is_string($content[$name]['url'] ?? null)) {
                $content[$name]['url'] = (string) preg_replace_callback('~^demo:([a-z0-9-]*)$~', $reference, $content[$name]['url']);
            }
            if ($field['type'] === 'richtext' && is_string($content[$name] ?? null)) {
                $linked = (string) preg_replace_callback('~(?<=href=")demo:([a-z0-9-]*)(?=")~', $reference, $content[$name]);
                $content[$name] = RichText::sanitize($linked);
            }
            if ($field['type'] === 'repeater' && is_array($content[$name] ?? null)) {
                foreach ($content[$name] as $i => $item) {
                    $content[$name][$i] = is_array($item) ? self::link($field['fields'], $item, $reference) : $item;
                }
            }
        }

        return $content;
    }

    /**
     * @return list<array{slug: string, title: string, blocks: list<array{string, array<string, mixed>, array<string, string>, string}>}>
     */
    public static function pages(): array
    {
        return require __DIR__ . '/pages.php';
    }
}
