<?php

use App\Support\Url;

/**
 * The demo's pictures, a few to a request (PLAN.md D-214, D-215): first its package coming in,
 * counted in megabytes, then its pictures put in place, counted one by one; and the way on.
 * With script the page goes on by itself (install.js), sending each request without leaving
 * and trying again when the server does not answer; without it, Continue does.
 *
 * Provided by View::render().
 *
 * @var int $value
 * @var int $max
 * @var string $text
 * @var string $csrf
 */
?>
        <h1><?= e(t('install.demo.title')) ?></h1>
        <p><?= e(t('install.demo.lead')) ?></p>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="stack" data-install-continue>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <progress class="install-progress" value="<?= (int) $value ?>" max="<?= max(1, (int) $max) ?>" aria-describedby="install-count"></progress>
            <p class="hint" id="install-count" role="status"><?= e($text) ?></p>
            <p class="hint" data-install-retry hidden><?= e(t('install.demo.retry')) ?></p>
            <button type="submit" class="button" data-busy-label="<?= e(t('install.demo.working')) ?>" data-stopped="<?= e(t('install.demo.stopped')) ?>"><?= e(t('install.demo.continue')) ?></button>
        </form>
