<?php

return [
    'title' => 'Shoppers',
    'subtitle' => 'Read-only view across stores from central order facts. Personal data is masked.',
    'export' => 'Export',
    'search' => 'Search shopper or store',
    'guest' => 'Guest shopper',
    'empty_title' => 'No shoppers yet',
    'empty_description' => 'Shoppers appear here once they place an order in any store.',
    'kpi' => [
        'shoppers' => 'Shoppers',
        'new' => 'New (30d)',
        'returning' => 'Returning',
        'avg_orders' => 'Avg orders',
    ],
    'kpi_hint' => [
        'shoppers' => 'Buyers with at least one order',
        'new' => 'First order in the last 30 days',
        'returning' => 'Buyers with two orders or more',
        'avg_orders' => 'Orders per shopper',
    ],
    'filters' => [
        'store' => 'Store',
        'country' => 'Country',
        'status' => 'Status',
        'new_only' => 'New only',
    ],
    'columns' => [
        'shopper' => 'Shopper',
        'email' => 'E-mail',
        'store' => 'Store',
        'country' => 'Country',
        'orders' => 'Orders',
        'spent' => 'Spent',
        'status' => 'Status',
        'first_order' => 'First order',
        'last_order' => 'Last order',
    ],
];
