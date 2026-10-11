<?php

/**
 * The picture (PLAN.md D-059, D-111): the strip that says what is in the frame, and the
 * frame. Required by appearance.php inside #design-form, in its scope.
 *
 * @var string $host
 * @var string $pageName
 * @var list<array{id: int, title: string, depth: int}> $previewPages
 * @var string $previewUrl
 */
?>
                <div class="appearance-stage-column">
                    <?php /* The strip says WHAT is in the frame and at what size: a preview
                             with no address is a picture of something. */ ?>
                    <div class="stage-strip">
                        <span class="stage-where"><?= e($host) ?> <span>·</span>
                            <?php /* WHICH PAGE THE PICTURE IS OF (D-111). The home page by
                                     default; any published page of the previewed language
                                     on request, because a header laid over the first
                                     section looks different over a page without a hero,
                                     and a sticky header cannot be judged on a short one.
                                     Inside the form, so the script's query carries it like
                                     any control; hidden until the script runs, because
                                     without one the picture is the home page and a select
                                     that changed nothing would teach people not to trust
                                     selects (D-060). */ ?>
                            <span class="stage-page" data-page-name><?= e($pageName) ?></span>
                            <label class="stage-pick" data-preview-page hidden>
                                <span class="visually-hidden"><?= e(t('appearance.page_to_preview')) ?></span>
                                <select name="page">
<?php foreach ($previewPages as $option): ?>
                                    <option value="<?= e((string) $option['id']) ?>"><?= e(str_repeat('— ', $option['depth']) . $option['title']) ?></option>
<?php endforeach; ?>
                                </select>
                            </label>
                        </span>
                        <?php /* Said, not silently acted on: when the column cannot carry the
                                 chosen width the screen says so here and leaves the width
                                 alone. */ ?>
                        <?php /* With a script, pressing the picture opens settings (D-181). */ ?>
                        <span class="stage-hint" data-regions-hint hidden><?= icon('mouse-pointer-click') ?> <?= e(t('appearance.regions_hint')) ?></span>
                        <span class="stage-tight" data-stage-tight role="status" hidden><?= e(t('appearance.stage.tight')) ?></span>
                        <span class="stage-size" data-stage-size></span>
                    </div>
                    <?php /* THE ZOOM SCALES THE STAGE, NEVER THE FRAME'S WIDTH. A page judged
                             at 1280 has to lay itself out at 1280; shrinking the frame instead
                             would hand it a narrower window and it would answer with the phone
                             layout, which is a different question entirely. */ ?>
                    <div class="preview-stage" data-stage>
                        <iframe name="design-preview" src="<?= e($previewUrl) ?>" title="<?= e(t('design.preview')) ?>" data-design-preview></iframe>
                    </div>
                </div>
