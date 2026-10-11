<?php

// Media: uploading, refusing, the library screen, and what one picture means. The
// library is "Media" because it will later hold documents to download (PLAN.md O-17);
// wording about a single image still says "picture", because that is what it is.
// (SPEC §5.5).
//
// Split from en.php, which had grown past the 300-line rule. t() loads every file in
// lang/, so a new one needs no registration — and a key defined twice in two files is a
// test failure rather than whichever file happened to load first.

return [
    // What a refusal says has to name the thing that was wrong: "invalid file" sends
    // someone back to try the same file again.
    'media.refused' => 'That file is not one Boxlet accepts (it looks like :type). Pictures: JPEG, PNG, WebP, GIF or AVIF. Files to download: PDF, ZIP, Word, Excel, PowerPoint, OpenDocument, TXT or CSV.',
    'media.refused_avif' => 'This server cannot read AVIF pictures. Save it as a JPEG or PNG and upload that.',
    // Refused rather than accepted-and-broken. Without GD or Imagick nothing can be
    // generated, and the picture would sit in the library as a card with no thumbnail and
    // no way to fix it — a silent failure the owner would have to diagnose from the shape
    // of the damage. The installer reports this too (install.opt.images).
    'media.refused_no_encoder' => 'This server cannot process pictures at all: neither GD nor Imagick is installed, so no sizes can be made. Nothing was uploaded. Ask your host to enable the GD extension.',
    'media.refused_heic' => 'HEIC pictures are not accepted, because most servers cannot read them. Export it as a JPEG first — on an iPhone, Settings → Camera → Formats → Most Compatible.',
    'media.storage_unwritable' => 'The picture could not be saved: storage/uploads is not writable. Nothing was changed.',
    'media.too_large' => 'That picture is larger than this server accepts (:limit per file). Nothing was uploaded.',
    'media.incomplete_upload' => 'The upload did not finish — the connection dropped part-way. Nothing was saved; try again.',
    'media.no_file' => 'No file was chosen.',

    // Said in the browser, before anything is sent. A body over the server's limit never
    // reaches a page of ours, so this is the only place it can be explained.
    'media.too_large_named' => ':name is :size, which is more than this server accepts (:limit per picture). It was not uploaded.',
    'media.too_large_total' => 'Those pictures come to :size together, and this server accepts :limit per upload. Send them in smaller batches.',
    'media.limits' => 'Pictures (JPEG, PNG, WebP, GIF, AVIF) and files to download (PDF, ZIP, Word, Excel, PowerPoint, OpenDocument, TXT, CSV) · up to :file each, :request at once',
    // Files for visitors to download (PLAN.md D-126).
    'media.kind' => 'Pictures or files',
    'media.kind.all' => 'Everything',
    'media.kind.pictures' => 'Pictures',
    'media.kind.files' => 'Files',
    'media.downloads_one' => ':count download',
    'media.downloads_many' => ':count downloads',
    'media.file_details' => 'A file for visitors to download. It is never shown as a page: whoever opens its address is asked to save it.',
    'media.file_address' => 'Address to download it from',
    'media.file_downloads' => 'Downloaded',
    'media.file_try' => 'Download it',
    'media.file_used_by_none' => 'No page offers this file yet.',

    // The library screen.
    'media.title' => 'Media',
    // The library as a table (D-052).
    'media.lede' => 'Every picture is kept in several sizes, made on upload. Descriptions are read to visitors who cannot see the picture. Files for visitors to download — PDFs, documents, archives — live here too.',
    'media.drop_anywhere' => 'Drop files anywhere on this screen, or',
    'media.show' => 'Show',
    'media.show.all' => 'All',
    'media.show.unused' => 'Unused',
    'media.show.undescribed' => 'No description',
    'media.show.none' => 'No picture is left by this filter.',
    'media.show.every' => 'Show every picture',
    'media.count' => ':count files · :size',
    'media.col.picture' => 'Picture',
    'media.col.file' => 'File',
    'media.col.dimensions' => 'Dimensions',
    'media.col.size' => 'Size',
    'media.col.used' => 'Used on',
    'media.col.described' => 'Description',
    'media.col.actions' => 'Actions',
    'media.used_one' => '1 page',
    'media.used_many' => ':count pages',
    'media.used_site' => 'the site',
    'media.unused_hint' => 'No page shows this picture, and it is not the site\'s logo, favicon or sharing picture.',
    'media.described.missing' => 'Missing',
    'media.described.set' => 'Set',
    'media.unfinished' => 'Unfinished',
    'media.more' => 'More for :name',
    'media.open' => 'Open',
    'media.empty' => 'Nothing here yet. Upload a picture to start.',
    'media.upload' => 'Upload files',
    'media.upload_hint' => 'Add pictures by dragging them here, several at once, or click to choose them. Each is resized for phones and screens as it arrives, and its description for visitors who cannot see it is filled in from the picture itself or its file name.',
    'media.drop' => 'Drag pictures here or',
    'media.browse' => 'browse',
    'media.uploading' => 'Uploading…',
    'media.search_placeholder' => 'Search by name or description',
    'media.pager' => 'Pages of the library',
    'media.newer' => 'Newer',
    'media.older' => 'Older',
    'media.page_of' => 'Page :page of :pages · :total in all',
    'media.upload_submit' => 'Upload',
    'media.uploaded' => 'Uploaded :count.',
    'media.duplicate' => ':name was already in the library, so it was not stored twice.',
    'media.search' => 'Search by name or description',
    'media.search_submit' => 'Search',
    'media.search_none' => 'No picture matches “:term”.',
    'media.no_thumb' => 'Still being processed',
    'media.incomplete' => 'Not all sizes are made yet.',
    'media.finish' => 'Finish processing',
    'media.finished' => 'Finished :name.',
    'media.dimensions' => ':width × :height',
    'media.not_found' => 'That picture no longer exists.',
    // The picker says why rather than showing an empty panel, which would read as
    // "there are no pictures" when the truth is that the list could not be fetched.
    'media.pick_failed' => 'The pictures could not be loaded. Close this and try again.',
    // The picker's resting state has to say what pressing it does. "office" alone read as
    // a text field somebody had typed into.
    'media.pick_choose' => 'Choose picture',
    'media.pick_change' => 'Change',
    'media.pick_close' => 'Close',

    // One picture.
    'media.back' => 'All media',
    'media.details' => 'Details',
    'media.original_name' => 'Uploaded as',
    'media.dimensions_label' => 'Dimensions',
    'media.size' => 'File size',
    'media.format' => 'Format',
    'media.added' => 'Added',
    'media.used_by' => 'Used on',
    'media.used_by_none' => 'No page uses this picture yet.',
    'media.meaning' => 'What this picture shows',
    'media.save' => 'Save',
    'media.delete' => 'Delete',
    'media.delete_confirm' => 'Delete :name? This cannot be undone.',
    'media.deleted' => 'The picture was deleted.',
    // Several deleted together (D-218).
    'media.bulk.all' => 'Select everything on this page of the list',
    'media.bulk.pick' => 'Select :name',
    'media.bulk.none_ticked' => 'Tick pictures and files to delete several at once.',
    'media.bulk.ticked' => ':count selected',
    'media.bulk.delete' => 'Delete selected',
    'media.bulk.confirm_any' => 'Delete the selected pictures and files? This cannot be undone.',
    'media.bulk.confirm' => 'Delete :count pictures and files? Any a page still shows are kept. This cannot be undone.',
    'media.bulk.none' => 'Nothing was selected, so nothing was deleted.',
    'media.bulk.deleted_one' => 'One was deleted.',
    'media.bulk.deleted_many' => ':count were deleted.',
    'media.bulk.kept_heading_one' => 'One was kept, because a page still shows it:',
    'media.bulk.kept_heading_many' => ':count were kept, because pages still show them:',
    'media.bulk.kept_one' => ':name (on :pages)',
    'media.deleted_file' => 'The file was deleted.',
    // A refusal that does not say WHICH pages sends someone hunting through the site.
    'media.in_use' => 'It is still used on :pages. Remove it there first, then delete it.',
    'media.replace_drop' => 'Drop the new picture here or',
    'media.replace_submit' => 'Replace the picture',
    'media.replace' => 'Replace',
    'media.replace_hint' => 'Put a different picture in its place. Every page using it shows the new one, and the sizes are made again.',
    'media.replaced' => 'The picture was replaced.',
    // :file is filled in by the browser with the dropped file's name.
    'media.replace_confirm' => 'Replace ":name" with :file? It changes the picture on every page that shows it (pages: :count), and the old picture cannot be brought back.',
    'media.replace_confirm_unused' => 'Replace ":name" with :file? The old picture cannot be brought back.',
    'media.replace_duplicate' => 'Those exact bytes are already in the library, as :name. Nothing was replaced.',
    // Cropping (D-026). The two buttons are deliberately not symmetrical: one is safe and
    // one cannot be undone, and the wording has to carry that difference on its own,
    // because a confirm dialog is the last thing read and the first thing dismissed.
    'media.crop' => 'Crop',
    'media.crop_hint' => 'Choose the part of the picture to keep. The sizes are made again from what you keep.',
    'media.crop_open' => 'Crop',
    // The focal point (PLAN.md D-121).
    'media.focal' => 'Focal point',
    'media.focal_hint' => 'Click the part of the picture that must stay in view. Wherever the picture is cut to a shape — behind a hero’s words, as a section’s background, in a square — this point is kept in frame.',
    'media.focal_x' => 'Across (%)',
    'media.focal_y' => 'Down (%)',
    'media.focal_save' => 'Save focal point',
    'media.focal_saved' => 'The focal point was moved, and the cut sizes were made again.',
    'media.focal_saved_later' => 'The focal point was moved. The cut sizes are being made again — the Media screen finishes them.',
    'media.focal_wide' => 'In a wide space, as on a computer',
    'media.focal_tall' => 'In a tall space, as on a phone',
    'media.crop_cancel' => 'Cancel',
    'media.crop_ratio' => 'Shape',
    'media.crop_ratio_free' => 'Free',
    'media.crop_ratio_hero' => 'Wide banner (16:9)',
    'media.crop_ratio_card' => 'Card (3:2)',
    'media.crop_ratio_wide' => 'Sharing (1.91:1)',
    'media.crop_ratio_thumb' => 'Square (1:1)',
    'media.crop_new' => 'Save as new picture',
    'media.crop_new_hint' => 'Keeps this picture as it is and adds the cropped part as another one.',
    'media.crop_replace' => 'Replace this picture',
    'media.crop_replace_hint' => 'Every page using this picture shows the cropped version. The original is gone and this cannot be undone — use "Save as new picture" to keep it.',
    'media.crop_replace_confirm' => 'Replace :name with the cropped version? The original cannot be brought back.',
    'media.cropped_new' => 'The cropped picture was added.',
    'media.cropped_replaced' => 'The picture was replaced with the cropped version.',
    'media.crop_too_small' => 'That crop is smaller than :min pixels on its short side. Choose a larger area.',
    'media.crop_outside' => 'That crop falls outside the picture. Nothing was changed.',
    'media.crop_ratio_wrong' => 'That crop does not match the shape you chose. Nothing was changed.',

    'media.alt' => 'Alt text',
    'media.alt_hint' => 'What the picture shows, for someone who cannot see it. Leave it empty if the picture is decoration.',
    // A guess is marked as one until the owner looks at it. Saying "check it" rather than
    'media.caption' => 'Caption',
    'media.meta_saved' => 'Saved.',
    'media.remake_title' => 'Make every size again',
    'media.remake_intro' => 'Each picture is kept in several sizes, made when it was uploaded. Making them again gives older pictures what this site makes now — smaller files since the latest update — and is needed if the sizes themselves change. Pictures stay on your pages the whole time; the new files replace the old ones one by one.',
    'media.remake_start' => 'Make every size again',
    'media.remake_confirm' => 'Make every picture\'s sizes again? It can take a few minutes with many pictures. Your pages keep showing them throughout.',
    'media.remake_started' => 'Pictures to make again: :count. It runs by itself while this page is open.',
    'media.remake_left' => 'Pictures still to make again: :left.',
    'media.remake_continue' => 'Continue',
    'media.remake_auto' => 'Continuing by itself. You can leave this page; coming back carries on.',
    'media.remake_progress' => 'Still to make again: :left.',
    'media.remake_done' => 'Every picture\'s sizes were made again.',
];
