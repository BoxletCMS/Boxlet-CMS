<?php

namespace App\Modules\Admin;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Settings;
use App\Modules\Design\Composition;
use App\Modules\Mailer\MailSettings;
use App\Modules\Pages\TranslationStatus;
use App\Modules\Stats\StatsFilter;
use App\Modules\Stats\StatsQuery;
use App\Modules\Stats\Tracker;
use App\Support\Bytes;
use App\Support\Url;
use DateTimeImmutable;
use DateTimeZone;

/**
 * What the Overview shows (PLAN.md D-052): a strip of figures, each with the context that
 * makes it mean something; what needs the owner's attention, each linked to where it is
 * fixed; and the pages read most. Every figure is a count over a small table.
 */
final class Overview
{
    /**
     * The strip: label, value, and a note. Visitors only while statistics are counted.
     *
     * @return list<array{label: string, value: string, note: string, href: string, word: bool}>
     */
    public static function metrics(Db $db, string $zone): array
    {
        $pages = $db->one("SELECT COUNT(*) AS n, SUM(CASE WHEN status = 'published' THEN 0 ELSE 1 END) AS drafts FROM pages") ?? [];
        $media = $db->one('SELECT COUNT(*) AS n, SUM(size) AS bytes FROM media') ?? [];
        $messages = $db->one('SELECT COUNT(*) AS n, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread FROM form_submissions') ?? [];
        $locales = $db->all('SELECT code, is_primary FROM locales WHERE enabled = 1 ORDER BY is_primary DESC, sort, code');
        $drafts = (int) ($pages['drafts'] ?? 0);

        $metrics = [
            [
                'label' => t('overview.pages'),
                'value' => number_format((int) ($pages['n'] ?? 0)),
                'note' => $drafts === 0 ? t('overview.pages_all_published') : t('overview.pages_drafts', ['count' => (string) $drafts]),
                'href' => Url::admin('pages'),
                'word' => false,
            ],
            [
                'label' => t('overview.pictures'),
                'value' => number_format((int) ($media['n'] ?? 0)),
                'note' => Bytes::human((int) ($media['bytes'] ?? 0)),
                'href' => Url::admin('media'),
                'word' => false,
            ],
        ];

        if (Tracker::settings($db)['enabled']) {
            $today = new DateTimeImmutable('now', new DateTimeZone($zone));
            $week = StatsFilter::fromQuery(['period' => '7d'], $today);
            $before = $week->previous();
            $query = new StatsQuery($db);
            $now = $query->totals($week)['visitors'];
            $change = StatsQuery::change($now, $query->totals($week, $before['from'], $before['to'])['visitors']);
            $metrics[] = [
                'label' => t('overview.visitors'),
                'value' => number_format($now),
                'note' => $change === null ? t('overview.visitors_new') : t('overview.visitors_change', ['change' => sprintf('%+d%%', (int) round($change * 100))]),
                'href' => Url::admin('statistics') . '?period=7d',
                'word' => false,
            ];
        }

        $unread = (int) ($messages['unread'] ?? 0);
        $metrics[] = [
            'label' => t('overview.messages'),
            'value' => number_format((int) ($messages['n'] ?? 0)),
            'note' => $unread === 0 ? t('overview.messages_all_read') : t('overview.messages_unread', ['count' => (string) $unread]),
            'href' => Url::admin('forms'),
            'word' => false,
        ];

        $codes = [];
        foreach ($locales as $locale) {
            $codes[] = strtoupper((string) $locale['code']) . ((int) $locale['is_primary'] === 1 ? ' ' . t('overview.languages_main') : '');
        }
        $metrics[] = [
            'label' => t('overview.languages'),
            'value' => (string) count($locales),
            'note' => implode(', ', $codes),
            'href' => Url::admin('settings') . '#languages',
            'word' => false,
        ];

        $changed = $db->one("SELECT occurred_at FROM activity WHERE kind = 'design' ORDER BY occurred_at DESC LIMIT 1");
        $metrics[] = [
            'label' => t('overview.design'),
            'value' => t('design.preset.' . Composition::active($db)),
            'note' => $changed === null ? t('overview.design_note') : self::since((string) $changed['occurred_at'], $zone),
            'href' => Url::admin('design'),
            'word' => true,
        ];

        return $metrics;
    }

    /**
     * What is waiting on the owner, each with where it is and where it is fixed. An empty
     * list is the good news, and the screen says so rather than padding it.
     *
     * @return list<array{title: string, where: string, href: string}>
     */
    public static function attention(Db $db, Blocks $registry): array
    {
        $issues = [];
        $primary = Url::primaryLocale();

        $missing = $db->all(
            "SELECT m.id, m.filename FROM media m
             LEFT JOIN media_meta mm ON mm.media_id = m.id AND mm.locale = ?
             WHERE mm.id IS NULL OR mm.alt = '' ORDER BY m.id DESC",
            [$primary],
        );
        if ($missing !== []) {
            $issues[] = [
                'title' => t('overview.issue.no_description', ['count' => (string) count($missing)]),
                'where' => t('overview.where.media', ['names' => self::names($missing)]),
                'href' => count($missing) === 1 ? Url::admin('media', (int) $missing[0]['id']) : Url::admin('media'),
            ];
        }

        foreach (array_keys(TranslationStatus::counts($db, $registry)) as $pageId) {
            $page = $db->one('SELECT p.title, l.label FROM pages p JOIN locales l ON l.code = p.locale WHERE p.id = ?', [$pageId]);
            $issues[] = [
                'title' => t('overview.issue.stale', ['page' => (string) ($page['title'] ?? '')]),
                'where' => t('overview.where.pages', ['language' => (string) ($page['label'] ?? '')]),
                'href' => Url::admin('pages', $pageId),
            ];
        }

        if (Settings::mediaId($db, 'site_favicon') === null) {
            $issues[] = [
                'title' => t('overview.issue.no_favicon'),
                'where' => t('overview.where.branding'),
                'href' => Url::admin('settings') . '#branding',
            ];
        }

        if (!MailSettings::configured($db) && (int) ($db->one('SELECT COUNT(*) AS n FROM forms')['n'] ?? 0) > 0) {
            $issues[] = [
                'title' => t('overview.issue.no_mail'),
                'where' => t('overview.where.mail'),
                'href' => Url::admin('settings') . '#mail',
            ];
        }

        return $issues;
    }

    /**
     * The four pages read most in the last fortnight, and each one's share of the first.
     * Empty while statistics are off.
     *
     * @return list<array{path: string, views: int, share: float}>
     */
    public static function mostRead(Db $db, string $zone): array
    {
        if (!Tracker::settings($db)['enabled']) {
            return [];
        }
        $today = new DateTimeImmutable('now', new DateTimeZone($zone));
        $fortnight = StatsFilter::fromQuery([
            'period' => 'custom',
            'from' => $today->modify('-13 days')->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ], $today);
        $rows = (new StatsQuery($db))->top('pages', $fortnight, 4);
        $top = max(1, $rows[0]['views'] ?? 1);

        return array_map(static fn (array $row): array => [
            'path' => $row['value'],
            'views' => $row['views'],
            'share' => min(100, $row['views'] * 100 / $top),
        ], $rows);
    }

    /**
     * "unchanged for 12 days", from an activity row's time. One day has its own wording:
     * "unchanged for 1 days" was on the owner's Overview for as long as the metric existed.
     */
    private static function since(string $utc, string $zone): string
    {
        $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
        if ($at === false) {
            return t('overview.design_note');
        }
        $tz = new DateTimeZone($zone);
        $days = (int) $at->setTimezone($tz)->setTime(0, 0)->diff((new DateTimeImmutable('now', $tz))->setTime(0, 0))->format('%a');

        if ($days === 0) {
            return t('overview.design_today');
        }

        return t($days === 1 ? 'overview.design_unchanged_one' : 'overview.design_unchanged', ['days' => (string) $days]);
    }

    /**
     * The first two names, and how many more.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private static function names(array $rows): string
    {
        $names = array_map(static fn (array $row): string => (string) $row['filename'], array_slice($rows, 0, 2));
        $more = count($rows) - count($names);

        return implode(', ', $names) . ($more > 0 ? ' ' . t('overview.and_more', ['count' => (string) $more]) : '');
    }
}
