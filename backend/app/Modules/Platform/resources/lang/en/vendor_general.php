<?php

return [
    'title' => 'General',
    'store' => 'Store',
    'save' => 'Save',
    'saved' => 'Store settings saved.',
    'requested' => 'Your change request was sent to the Nogrgr team.',
    'request_change' => 'Request change',
    'pending_locked' => 'A request is already waiting for review.',
    'list_separator' => ', ',
    'fields' => [
        'store_name' => 'Store name',
        'support_email' => 'Support email',
        'support_email_help' => 'Shown to shoppers who need help with an order.',
        'profit_percent' => 'Default profit %',
        'profit_percent_help' => 'Applied to central products without their own profit rule.',
        'timezone' => 'Timezone',
    ],
    'countries' => [
        'title' => 'Countries',
        'empty' => 'Your store does not sell in any country yet. Request the countries you want to sell in.',
    ],
    'categories' => [
        'title' => 'Categories',
        'empty' => 'No categories selected yet. Request the categories your store sells.',
    ],
    'pending' => [
        'line' => 'Pending request: :changes (sent :ago).',
        'add' => 'add :items',
        'remove' => 'remove :items',
    ],
    'modal' => [
        'countries' => [
            'title' => 'Request country change',
            'add' => 'Add countries',
            'add_placeholder' => 'Choose countries',
            'remove' => 'Remove countries',
            'remove_placeholder' => 'Choose countries',
        ],
        'categories' => [
            'title' => 'Request category change',
            'add' => 'Add categories',
            'add_placeholder' => 'Choose categories',
            'remove' => 'Remove categories',
            'remove_placeholder' => 'Choose categories',
        ],
        'reason' => 'Reason',
        'cancel' => 'Cancel',
        'send' => 'Send request',
    ],
    'errors' => [
        'nothing_selected' => 'Choose something to add or remove.',
        'last_country' => 'Your store must keep at least one selling country.',
    ],
];
