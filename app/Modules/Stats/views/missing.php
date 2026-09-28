<?php

use App\Support\Url;

/**
 * Addresses visitors asked for and the site does not have (PLAN.md O-20), with where they
 * came from — which is what says whose link is broken. Required by statistics.php and
 * all.php; null means the screen is narrowed to something this table cannot answer.
 *
 * @var list<array{path: string, source: string, views: int}>|null $missing
 */
?>
<?php if ($missing === null): ?>
                <p class="hint"><?= e(t('stats.missing_not_split')) ?></p>
<?php elseif ($missing === []): ?>
                <p class="hint"><?= e(t('stats.missing_none')) ?></p>
<?php else: ?>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('stats.column.missing')) ?></th>
                            <th scope="col"><?= e(t('stats.column.sources')) ?></th>
                            <th scope="col" class="stats-num"><?= e(t('stats.tries')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($missing as $row): ?>
                        <tr>
                            <th scope="row" class="stats-name"><span><?= e($row['path']) ?></span></th>
                            <td><?= e($row['source'] === '' ? t('stats.direct') : $row['source']) ?></td>
                            <td class="stats-num"><?= e(number_format($row['views'])) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
<?php endif; ?>
