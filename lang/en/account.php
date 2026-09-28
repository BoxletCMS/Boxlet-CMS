<?php

// Your login (PLAN.md D-132): the admin's own email and password. A new concern, so a new
// file: t() merges every file in the locale's directory.

return [
    'account.title' => 'Your login',
    'account.intro' => 'The address and password you log in with, and two-step login. Changing either asks for your current password.',
    'account.email' => 'Email',
    'account.email_label' => 'Login email',
    'account.email_save' => 'Change email',
    'account.email_invalid' => 'That is not an email address. Nothing was changed.',
    'account.email_changed' => 'You now log in as :email.',
    'account.password' => 'Password',
    'account.current' => 'Current password',
    'account.new' => 'New password',
    'account.new_hint' => 'At least :min characters. A long phrase is easier to remember than a short jumble.',
    'account.confirm' => 'New password again',
    'account.password_save' => 'Change password',
    'account.password_changed' => 'Your password was changed.',
    'account.current_wrong' => 'That is not your current password. Nothing was changed.',

    // A forgotten password (D-132).
    'account.forgot_link' => 'Forgot your password?',
    'account.forgot_title' => 'Forgot your password',
    'account.forgot_intro' => 'Type the address you log in with. If it is the admin’s, a link to set a new password is sent to it.',
    'account.forgot_submit' => 'Send the link',
    'account.back_to_login' => 'Back to log in',
    'account.forgot_sent' => 'If that is the admin’s address, a link is on its way. It works once, for :minutes minutes. Nothing arrived? Look in the spam folder, or use the way below.',
    'account.forgot_no_mail' => 'This site cannot send email yet, so no link can be sent. Use the way below instead.',
    'account.forgot_ftp' => 'Without email: put a file named :file on the server, by FTP or your host’s file manager, with the new password as its only line. The next visit to the login page sets it and deletes the file.',
    'account.mail_subject' => 'A new password for :site',
    'account.mail_body' => "Someone asked for a new password for the admin of :site. If it was you, open this link and choose one:\n\n:url\n\nIt works once, for :minutes minutes. If it was not you, do nothing: your password stays as it is.",
    'account.reset_title' => 'A new password',
    'account.reset_submit' => 'Set the new password',
    'account.reset_gone' => 'This link no longer works: it was used already, or it is more than an hour old.',
    'account.reset_again' => 'Ask for a new link',
    'account.reset_done' => 'Your new password is set. Log in with it.',
    'account.file_done' => 'The password in :file is set, and the file was deleted. Log in with it.',
    'account.file_left' => 'Boxlet could not delete :file, so it did not use it. Delete the file by FTP, and put it back only once the folder lets Boxlet delete it.',
    'account.file_refused' => 'The password in :file was not used: :problem The file was deleted.',
];
