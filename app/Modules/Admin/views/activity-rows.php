<?php

use App\Modules\Admin\Activity;

/**
 * Rows of the activity log, as a list of grid rows: when, what (a link while the thing still
 * exists), and what kind of thing. Required by activity.php and dashboard.php.
 *
 * @var list<array{id: int, at: string, kind: string, action: string, subjectId: int|null, subject: string}> $rows
 * @var string $zone the site's time zone
 */
?>
<?php if ($rows === []): ?>
                <p class="hint"><?= e(t('activity.none')) ?></p>
<?php else: ?>
                <ol class="activity-log" role="list">
<?php foreach ($rows as $row):
    $said = Activity::describe($row['kind'], $row['action'], $row['subject']);
    $link = Activity::link($row['kind'], $row['action'], $row['subjectId']);
    ?>
                    <li class="activity-row">
                        <time class="activity-when" datetime="<?= e(str_replace(' ', 'T', $row['at']) . 'Z') ?>"><?= e(Activity::when($row['at'], $zone)) ?></time>
                        <span class="activity-what"><?php if ($link !== null): ?><a href="<?= e($link) ?>"><?= e($said) ?></a><?php else: ?><?= e($said) ?><?php endif; ?></span>
                        <span class="activity-kind"><?= e(t('activity.kind.' . $row['kind'])) ?></span>
                    </li>
<?php endforeach; ?>
                </ol>
<?php endif; ?>
