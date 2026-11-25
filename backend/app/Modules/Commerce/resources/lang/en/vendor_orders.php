<?php

return [
    'title' => 'Orders',
    'subtitle' => 'Track your store\'s orders, payment collection and fulfilment progress.',
    'report' => 'Download report',
    'search' => 'Search order, customer, product',
    'empty_title' => 'No orders yet',
    'empty_description' => 'Orders placed in your storefront appear here.',
    'kpi' => [
        'orders' => 'Orders', 'orders_hint' => 'Orders placed in your store',
        'paid' => 'Paid', 'paid_hint' => 'Orders with a captured payment',
        'processing' => 'Processing', 'processing_hint' => 'Orders moving through fulfilment',
        'collected' => 'Collected', 'collected_hint' => 'Paid order value',
    ],
    'columns' => ['order' => 'Order', 'customer' => 'Customer', 'value' => 'Value', 'commission' => 'Your share', 'status' => 'Status', 'gateway' => 'Gateway', 'placed' => 'Placed at', 'actions' => 'Actions'],
    'filters' => ['status' => 'Status', 'payment' => 'Payment', 'gateway' => 'Gateway', 'kind' => 'Type', 'date' => 'Date'],
    'view' => 'View order',
    'print_invoice' => 'Print invoice',
    'refund' => 'Refund',
    'cancel' => 'Cancel',
    'shipping' => 'Shipping',
    'fulfilled_by' => 'Fulfilled by',
    'platform_fulfilled' => 'Nogrgr',
    'central_note' => 'Platform-product order: the platform updates the shipping status. You can update the status of store-product orders only.',
];
