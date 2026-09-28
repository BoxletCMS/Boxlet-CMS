<?php

// Updating an existing install, and maintenance mode (PLAN.md D-019, D-021).
//
// Split from en.php, which had grown past the 300-line rule. t() loads every file in
// lang/, so a new one needs no registration — and a key defined twice in two files is a
// test failure rather than whichever file happened to load first.

return [
    // Updating an existing install (PLAN.md D-019). Nothing runs on its own: the admin
    // sees one screen with one button, and the public site waits.
    'update.title' => 'Database update needed',
    'update.intro' => 'This copy of Boxlet has been updated, and its database has not caught up yet. Until you run the update, the site shows visitors a short "being updated" page.',
    'update.pending' => 'Waiting to be applied:',
    'update.run' => 'Run the update',
    'update.backup_sqlite' => 'Your database is a file, and a copy of it is saved into storage/backups/ before anything runs.',
    'update.backup_mysql' => 'Take a backup through your host before you press the button. Boxlet cannot make one for MySQL yet.',
    'update.done' => 'The database is up to date.',
    // The heading above it. Without a separate line the screen said "The database is up
    // to date." twice, as its own title and as its only sentence — noticed on the first
    // real use, on the live site.
    'update.up_to_date_title' => 'Database',
    'update.applied' => 'Applied: :files',
    'update.failed' => 'The update stopped at :file. Nothing after it has run, and everything before it is recorded, so fixing the problem and pressing the button again continues from there.',
    'update.locked' => 'An update is already running. Wait for it to finish, then reload this page.',
    'update.backup_failed' => 'The backup could not be written to storage/backups/. Nothing has been changed. Make that directory writable and try again.',
    'update.public.title' => 'Being updated',
    'update.public.body' => 'This site is being updated and will be back in a moment.',

    // Maintenance mode (PLAN.md D-021). The same page and the same gate as an update;
    // the difference is that the owner chose it, and can still see the real site.
    'maintenance.title' => 'Maintenance mode',
    'maintenance.off_now' => 'Your site is visible to everyone.',
    'maintenance.on_now' => 'Visitors see a short "back in a moment" page. You still see the site itself.',
    'maintenance.turn_on' => 'Turn on maintenance mode',
    'maintenance.turn_off' => 'Turn off maintenance mode',
    'maintenance.turned_on' => 'Maintenance mode is on. Visitors see a short "back in a moment" page.',
    'maintenance.turned_off' => 'Maintenance mode is off. Your site is visible again.',
    'maintenance.bar' => 'Maintenance mode is on — visitors cannot see this site.',
    'maintenance.bar_off' => 'Turn it off',
    'maintenance.failed' => 'Maintenance mode could not be switched: the storage directory is not writable. Your site is unchanged.',
    'maintenance.public.title' => 'Back in a moment',
    'maintenance.public.body' => 'This site is briefly unavailable. Please try again shortly.',
];
