<?php

use App\Support\Url;

/**
 * THE FORMS THAT ARE NOT THE DESIGN FORM, and are empty on purpose (PLAN.md D-152, D-157).
 * Their controls stand inside #design-form — the file input in Your designs, the answers in
 * the import question, Quick start's mirrors — and belong to these by their form attribute,
 * because a form inside a form is not one. Required by appearance.php before #design-form.
 *
 * @var string $csrf
 */
?>
        <form id="design-import" method="post" action="<?= e(Url::admin('appearance', 'import')) ?>" enctype="multipart/form-data" hidden>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        </form>
        <form id="design-import-add" method="post" action="<?= e(Url::admin('appearance', 'import', 'add')) ?>" hidden>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        </form>
        <?php /* Quick start's questions, repeated from their sections for a script to mirror:
                 owned by this form, which is never sent, so they are neither posted twice nor
                 one radio group with the fields they mirror (D-157). */ ?>
        <form id="appearance-quick" hidden></form>
