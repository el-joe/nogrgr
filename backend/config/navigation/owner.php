<?php

/*
| Store-owner portal sidebar ({central}/my-account) — same shape as config/navigation/admin.php.
| OWN-008 Notifications joins in P9.
*/

return [
    [
        'key' => 'portal',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.owner.dashboard', 'icon' => 'store', 'route' => 'owner.dashboard', 'permission' => 'owner.portal', 'active' => ['owner.dashboard', 'owner.select-store']],
            ['key' => 'subscriptions', 'label' => 'navigation.owner.subscriptions', 'icon' => 'layers', 'route' => 'owner.subscriptions.index', 'permission' => 'owner.portal', 'active' => 'owner.subscriptions.*'],
            ['key' => 'domains', 'label' => 'navigation.owner.domains', 'icon' => 'globe', 'route' => 'owner.domains.index', 'permission' => 'owner.portal', 'active' => 'owner.domains.*'],
            ['key' => 'invoices', 'label' => 'navigation.owner.invoices', 'icon' => 'file', 'route' => 'owner.invoices.index', 'permission' => 'owner.portal', 'active' => 'owner.invoices.*'],
        ],
    ],
    [
        'key' => 'account',
        'label' => 'navigation.sections.account',
        'items' => [
            ['key' => 'profile', 'label' => 'navigation.owner.profile', 'icon' => 'users', 'route' => 'owner.profile.edit', 'permission' => 'owner.portal', 'active' => 'owner.profile.*'],
            ['key' => 'add_store', 'label' => 'navigation.owner.add_store', 'icon' => 'plus', 'route' => 'owner.stores.create', 'permission' => 'owner.stores.create', 'active' => ['owner.stores.create', 'owner.stores.store']],
            ['key' => 'support', 'label' => 'navigation.owner.support', 'icon' => 'lifebuoy', 'route' => 'owner.support', 'permission' => 'owner.portal', 'active' => 'owner.support'],
        ],
    ],
];
