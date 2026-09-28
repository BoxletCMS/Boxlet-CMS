<?php

namespace App\Modules\Stats;

use App\Core\Db;

/**
 * Taking the counts out (PLAN.md O-20).
 *
 * Two shapes, for two jobs:
 *   - a CSV of one table as the screen is showing it, for a spreadsheet;
 *   - a JSON file of everything, row for row with the tables, for keeping a copy.
 *
 * One file rather than a ZIP of several, because one file is what a person can look at
 * before trusting it, and because writing a ZIP would be ours to write: the extension for
 * it is optional in PHP.
 *
 * OUT ONLY, at the owner's decision (2026-09-20). There was an import; it is gone. A file
 * of numbers that ADDS to what a site has counted is a way to make statistics say something
 * that never happened, and the counts are worth more when the only thing that can write
 * them is a visit.
 */
final class StatsExport
{
    /** The tables a whole export carries, in the order a reader meets them. */
    private const TABLES = ['stats_views', 'stats_page_visitors', 'stats_missing', 'stats_places'];

    /**
     * One table as the screen shows it, as CSV: the same rows, the same order, the same
     * narrowing — what is on the screen is what comes out.
     */
    /**
     * @param int|null $cityMin the owner's floor under the cities (D-109). The export obeys
     *        the same floor the screen does — a CSV that named what the table would not is
     *        the same disclosure by another door.
     */
    public static function csv(Db $db, string $dimension, StatsFilter $filter, bool $group, ?int $cityMin = null): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        // All four arguments: PHP 8.4 deprecates leaving the escape character to its default,
        // and an empty one is what writes plain CSV that every spreadsheet reads back.
        $line = static fn (array $values) => fputcsv($out, $values, ',', '"', '');

        if ($dimension === 'missing') {
            $line(['path', 'source', 'views']);
            foreach ((new StatsQuery($db))->missing($filter, null) ?? [] as $row) {
                $line([$row['path'], $row['source'], $row['views']]);
            }
        } else {
            $line([StatsView::filterKey($dimension), 'visitors', 'views']);
            foreach ((new StatsQuery($db, $cityMin))->top($dimension, $filter, null, $group) as $row) {
                $line([
                    $row['value'] === StatsQuery::OTHER ? StatsView::label($dimension, $row['value'], $cityMin) : $row['value'],
                    $row['visitors'] ?? '',
                    $row['views'],
                ]);
            }
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Every count there is, as JSON: the tables row for row, with the day it was made, so a
     * file can be looked at before it is trusted.
     */
    public static function json(Db $db): string
    {
        $data = ['boxlet' => 'statistics', 'version' => 1, 'exported' => gmdate('Y-m-d H:i:s'), 'tables' => []];
        foreach (self::TABLES as $table) {
            $data['tables'][$table] = $db->all("SELECT * FROM {$table} ORDER BY day");
        }

        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
