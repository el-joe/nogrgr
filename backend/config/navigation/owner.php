<?php

/*
| Store-owner portal sidebar ({central}/my-account) — same shape as config/navigation/admin.php.
| Only pages that exist are listed; the other OWN-* pages join when they are built.
*/

return [
    [
        'key' => 'account',
        'label' => 'navigation.sections.account',
        'items' => [
            ['key' => 'add_store', 'label' => 'navigation.owner.add_store', 'icon' => 'plus', 'route' => 'owner.stores.create', 'permission' => 'owner.stores.create', 'active' => 'owner.stores.*'],
        ],
    ],
];
