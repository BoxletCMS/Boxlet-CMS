<?php

// Redirects (PLAN.md D-129): addresses that lead elsewhere. A new concern, so a new file.

return [
    'redirects.title' => 'Redirects',
    'redirects.intro' => 'Addresses that no longer have a page of their own, and where they lead instead. A visitor or a search engine following one is sent on permanently (301).',

    'redirects.rules' => 'Your rules',
    'redirects.rules_none' => 'No rules yet. Add one for each address of an old site that people or search engines still use.',
    'redirects.col.from' => 'Address',
    'redirects.col.to' => 'Leads to',
    'redirects.col.used' => 'Used',
    'redirects.col.actions' => 'Actions',
    'redirects.col.old' => 'Old address',
    'redirects.col.page' => 'Page',
    'redirects.nowhere' => 'Its page was deleted',
    'redirects.unpublished' => 'Page not published',
    'redirects.never_used' => 'Not yet',
    'redirects.used_one' => 'Once, last :date',
    'redirects.used_many' => ':count times, last :date',
    'redirects.delete' => 'Delete',
    'redirects.delete_confirm' => 'Delete the redirect for :from? The address will answer “not found”.',

    'redirects.new' => 'New rule',
    'redirects.from' => 'Old address',
    'redirects.from_hint' => 'As it was on the old site: /usluge.html, /index.php?id=12, or the whole address. Capitals do not matter. Some servers answer addresses ending in .php themselves, other than /index.php, and those never reach Boxlet.',
    'redirects.to_page' => 'Leads to a page',
    'redirects.to_page_none' => '— Choose a page —',
    'redirects.draft' => 'draft',
    'redirects.or' => 'or',
    'redirects.to_url' => 'Leads to an address',
    'redirects.to_url_hint' => 'Another site, or a path on this one. A page is better where there is one: it keeps working when the page is renamed.',
    'redirects.create' => 'Add rule',
    'redirects.created' => 'The rule for :from was added.',
    'redirects.deleted' => 'The redirect for :from was deleted.',

    'redirects.from_required' => 'Type the old address. The home page cannot be redirected.',
    'redirects.from_system' => 'This address belongs to Boxlet itself and cannot be redirected.',
    'redirects.from_taken' => 'There is already a rule for this address.',
    'redirects.from_live' => 'This is the address of the page “:page”, which answers it first, so the rule would never be used.',
    'redirects.to_required' => 'Choose a page, or type an address.',
    'redirects.to_both' => 'Choose a page or type an address, not both.',
    'redirects.url_invalid' => 'Type an address starting with /, http:// or https://.',

    'redirects.kept' => 'Old addresses of renamed pages',
    'redirects.kept_intro' => 'Kept by Boxlet when a published page gets a new address, so links to the old one keep working. Delete one only if nobody should reach the page that way any more.',
    'redirects.kept_none' => 'None yet. When you change the address of a published page, the old one appears here.',
];
