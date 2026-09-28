<?php

// Menus (PLAN.md D-028). A new concern, so a new file: t() merges every file in the
// locale's directory and a key defined twice is a test failure, not a silent winner.

return [
    'menus.title' => 'Menus',
    'menus.intro' => 'What visitors are offered, and what it is called there. A menu belongs to one language.',
    'menus.none' => 'No menus yet.',

    'menus.new' => 'New menu',
    'menus.name' => 'Name',
    'menus.name_hint' => 'Only you see this name, never visitors: call it Header, Footer or whatever tells you where it goes. The header and footer choose a menu by it.',
    'menus.name_required' => 'Give the menu a name.',
    'menus.name_taken' => 'There is already a menu called that in this language.',
    'menus.locale' => 'Language',
    'menus.create' => 'Create menu',
    'menus.rename' => 'Rename menu',
    'menus.name_edit_hint' => 'Only you see this name; visitors never do. The header and footer find their menu by it, and follow a rename.',
    'menus.created' => 'Menu created.',
    'menus.renamed' => 'Menu renamed.',
    'menus.deleted' => 'Menu deleted.',
    'menus.delete' => 'Delete',
    'menus.delete_confirm' => 'Delete the menu “:name” and everything in it?',
    'menus.not_found' => 'That menu is not here.',

    'menus.col.name' => 'Menu',
    'menus.col.locale' => 'Language',
    'menus.col.items' => 'Items',
    'menus.col.order' => 'Order',
    'menus.col.label' => 'Shown as',
    'menus.col.target' => 'Goes to',
    'menus.col.actions' => 'Actions',
    'menus.items_count' => ':count items',

    'menus.item.add' => 'Add item',
    'menus.item.added' => 'Item added.',
    'menus.item.saved' => 'Item saved.',
    'menus.item.deleted' => 'Item removed.',
    'menus.item.page' => 'Page',
    'menus.item.page_none' => 'No page — use an address',
    'menus.item.url' => 'Address',
    'menus.item.page_hint' => 'The page this item opens. Choosing one fills in its address and title below. If the page’s address changes later, the item follows it.',
    'menus.item.url_hint' => 'Filled in when you choose a page. For anything else — another site, an email address, a phone number — leave the page empty and type it here. An email opens the visitor’s mail app; a phone number can be tapped to call on a phone.',
    'menus.item.url_refused' => 'That address was not accepted, so the item points nowhere yet.',
    'menus.item.label' => 'Shown as',
    'menus.item.label_hint' => 'The words visitors see in the menu. A chosen page fills in its title; shorten it if the menu gets crowded. Left empty, the page’s own title is used.',
    'menus.item.edit' => 'Edit',
    'menus.item.save' => 'Save item',
    'menus.item.edit_title' => 'Edit “:label”',
    'menus.item.cancel' => 'Cancel',
    'menus.item.parent' => 'Under',
    'menus.item.parent_top' => 'Top level',
    'menus.item.parent_invalid' => 'That item cannot hold another one under it.',
    'menus.item.needs_target' => 'Choose a page or type an address.',

    // An item whose page was deleted, or whose address was refused. Shown here and left
    // out of the site, so the owner can repoint it rather than hunt for what disappeared.
    'menus.item.broken' => 'Goes nowhere',
    'menus.item.broken_hint' => 'Its page was deleted or its address was not accepted. Visitors do not see it.',
    'menus.item.draft' => 'Page is a draft',
    'menus.item.draft_hint' => 'Visitors do not see this item until the page is published.',

    'menus.order_hint' => 'Visitors see the items in this order. Drag a row by its handle, or use the arrows. An item placed under another opens as its submenu; a menu goes one level deep.',
    'menus.reordered' => 'Order saved.',
    'menus.reorder_failed' => 'That order could not be saved. Reload the page and try again.',
    'menus.move_up' => 'Move up',
    'menus.move_down' => 'Move down',
];
