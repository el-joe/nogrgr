<?php

return [
    'title' => "Your store's performance",
    'subtitle' => 'The numbers that really matter to you, in a clear size and without clutter.',
    'period' => 'Period',
    'periods' => [
        '7d' => '7 Days',
        '30d' => '30 Days',
        '90d' => '90 Days',
        '12m' => '12 Months',
    ],
    'converted' => '≈ :amount (display only)',
    'kpi' => [
        'orders' => 'Orders',
        'orders_note' => 'Orders recorded in your store.',
        'average' => 'Average order',
        'average_note' => 'Average value of the orders in this period.',
        'outstanding' => 'Outstanding',
        'outstanding_note' => 'Open unpaid order value still pending.',
        'customers' => 'Customers',
        'customers_note' => 'Customers who ordered in this period.',
    ],
    'chart' => [
        'title' => 'Gross vs Collected',
        'subtitle' => 'Gross order value compared with paid collections.',
        'gross' => 'Gross sales',
        'collected' => 'Collected',
    ],
    'status' => [
        'title' => 'Status Mix',
        'subtitle' => 'Order pipeline distribution across statuses.',
        'total' => '{1} :count order|[0,*] :count orders',
    ],
    'latest' => [
        'title' => 'Latest orders',
        'subtitle' => 'The five most recent orders of your store.',
        'all' => 'All orders',
        'order' => 'Order',
        'customer' => 'Customer',
        'status' => 'Status',
        'total' => 'Total',
        'guest' => 'Guest',
        'empty_title' => 'No orders yet',
        'empty_description' => 'Your newest orders show up here as soon as customers place them.',
    ],
    'empty_title' => 'No orders in this period',
    'empty_description' => 'Pick a longer period, or share your store to get the first orders.',
];
