<?php

/*
| Affiliate (partner) panel sidebar — same shape as config/navigation/admin.php.
*/

return [
    [
        'key' => 'overview',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.admin.dashboard', 'icon' => 'dashboard', 'route' => 'affiliate.dashboard', 'permission' => 'dashboard.view', 'active' => 'affiliate.dashboard'],
        ],
    ],
];
