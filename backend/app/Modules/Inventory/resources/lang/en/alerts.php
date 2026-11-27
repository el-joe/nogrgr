<?php

return [
    'low_stock' => ['title' => 'Low stock: :product', 'body' => 'Only :stock left of :product (alert at :threshold).'],
    'out_of_stock' => ['title' => 'Out of stock: :product', 'body' => ':product is out of stock and cannot be bought until it is restocked.'],
    'back_in_stock' => ['title' => ':product is back in stock', 'body' => 'Good news: :product is available again at :store.'],
    'threshold' => [
        'title' => 'Low-stock alert threshold',
        'help' => 'Store-wide number of units at which the team is alerted, for products without their own minimum stock. 0 turns low-stock alerts off (out-of-stock alerts stay on).',
        'label' => 'Alert when stock reaches',
        'units' => 'units',
        'save' => 'Save threshold',
        'saved' => 'Low-stock threshold saved.',
    ],
];
