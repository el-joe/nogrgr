<?php

/*
| Vendor (store) panel sidebar — same shape as config/navigation/admin.php. Items are
| added by each module as its vendor pages ship; missing routes never render.
*/

return [
    [
        'key' => 'overview',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.admin.dashboard', 'icon' => 'dashboard', 'route' => 'vendor.dashboard', 'permission' => 'dashboard.view', 'active' => 'vendor.dashboard'],
        ],
    ],
];
