<?php

namespace App\Modules\Admin;

use App\Core\Db;
use App\Support\Url;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The activity log (PLAN.md D-052, migration 0021): one row for each thing that changed on
 * the site, written by the controller that changed it, and read by the Overview and the
 * full log.
 *
 * Writing never fails the change it records: the change has already happened by then, and
 * a site whose page cannot be published because a log line could not be written would have
 * its priorities backwards.
 */
final class Activity
{
    /** Kept for a year: older rows go at the next write. */
    private const KEEP_DAYS = 365;

    /**
     * Records that $action happened to a $kind called $subject. $subjectId is its id while
     * it exists, for a link; null where there is none to link to.
     */
    public static function record(Db $db, string $kind, string $action, ?int $subjectId, string $subject): void
    {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $db->query(
                'INSERT INTO activity (occurred_at, kind, action, subject_id, subject) VALUES (?, ?, ?, ?, ?)',
                [$now->format('Y-m-d H:i:s'), $kind, $action, $subjectId, mb_substr($subject, 0, 190)],
            );
            $db->query('DELETE FROM activity WHERE occurred_at < ?', [$now->modify('-' . self::KEEP_DAYS . ' days')->format('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            error_log('Activity log: ' . $e->getMessage());
        }
    }

    /**
     * The latest rows, newest first.
     *
     * @return list<array{id: int, at: string, kind: string, action: string, subjectId: int|null, subject: string}>
     */
    public static function recent(Db $db, int $limit, int $offset = 0): array
    {
        $rows = $db->all(
            'SELECT id, occurred_at, kind, action, subject_id, subject FROM activity
             ORDER BY occurred_at DESC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        );

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'at' => (string) $row['occurred_at'],
            'kind' => (string) $row['kind'],
            'action' => (string) $row['action'],
            'subjectId' => $row['subject_id'] === null ? null : (int) $row['subject_id'],
            'subject' => (string) $row['subject'],
        ], $rows));
    }

    public static function count(Db $db): int
    {
        return (int) ($db->one('SELECT COUNT(*) AS n FROM activity')['n'] ?? 0);
    }

    /** What happened, in words: "Published “About”". */
    public static function describe(string $kind, string $action, string $subject): string
    {
        return t("activity.{$kind}.{$action}", ['name' => $subject]);
    }

    /**
     * Where the thing is, for a link; null when it is gone or has no screen of its own. A
     * deleted thing is never linked, and neither is one whose id was not recorded.
     */
    public static function link(string $kind, string $action, ?int $subjectId): ?string
    {
        if ($subjectId === null || $action === 'deleted') {
            return null;
        }

        return match ($kind) {
            'page' => Url::admin('pages', $subjectId),
            'media' => Url::admin('media', $subjectId),
            'menu' => Url::admin('menus', $subjectId),
            'form' => Url::admin('forms', $subjectId),
            'message' => Url::admin('forms', $subjectId, 'messages'),
            'redirect' => Url::admin('redirects'),
            default => null,
        };
    }

    /**
     * When, as the log shows it, in the site's zone: the time today, "Yesterday", "3 days"
     * within a week, and a date before that.
     */
    public static function when(string $utc, string $zone, ?DateTimeImmutable $now = null): string
    {
        $tz = new DateTimeZone($zone);
        $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
        if ($at === false) {
            return $utc;
        }
        $at = $at->setTimezone($tz);
        $today = ($now ?? new DateTimeImmutable('now'))->setTimezone($tz)->setTime(0, 0);
        $days = (int) $today->diff($at->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days >= 0 => $at->format('H:i'),
            $days === -1 => t('activity.yesterday'),
            $days > -7 => t('activity.days_ago', ['days' => (string) -$days]),
            default => $at->format('j M'),
        };
    }
}
