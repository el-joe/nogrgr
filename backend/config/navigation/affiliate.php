<?php

/*
| Affiliate (partner) panel sidebar — same shape as config/navigation/admin.php
| (Figma Affiliate & Owner sidebar 118:99699).
*/

return [
    [
        'key' => 'overview',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.affiliate.dashboard', 'icon' => 'dashboard', 'route' => 'affiliate.dashboard', 'permission' => 'dashboard.view', 'active' => 'affiliate.dashboard'],
            ['key' => 'links', 'label' => 'navigation.affiliate.links', 'icon' => 'link', 'route' => 'affiliate.links', 'permission' => 'links.view', 'active' => 'affiliate.links*'],
            ['key' => 'conversions', 'label' => 'navigation.affiliate.conversions', 'icon' => 'chart', 'route' => 'affiliate.conversions', 'permission' => 'conversions.view', 'active' => 'affiliate.conversions*'],
            ['key' => 'payouts', 'label' => 'navigation.affiliate.payouts', 'icon' => 'wallet', 'route' => 'affiliate.payouts.index', 'permission' => 'payouts.view', 'active' => 'affiliate.payouts.*'],
        ],
    ],
    [
        'key' => 'account',
        'label' => 'navigation.sections.account',
        'items' => [
            ['key' => 'notifications', 'label' => 'navigation.affiliate.notifications', 'icon' => 'bell', 'route' => 'affiliate.notifications.index', 'permission' => 'notifications.view', 'active' => 'affiliate.notifications.*'],
            ['key' => 'profile', 'label' => 'navigation.affiliate.profile', 'icon' => 'user', 'route' => 'affiliate.profile.edit', 'permission' => 'profile.manage', 'active' => 'affiliate.profile.*'],
        ],
    ],
];
