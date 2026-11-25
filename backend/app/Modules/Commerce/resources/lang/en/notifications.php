<?php

return [
    'store_new_order' => ['title' => 'New order :number', 'body' => ':customer placed order :number (:total).'],
    'store_order_cancelled' => ['title' => 'Order :number cancelled', 'body' => 'Order :number (:total) was cancelled.'],
    'admin_order_paid' => ['title' => 'Order :number paid', 'body' => ':store received a paid order :number (:total).'],
    'admin_order_cancelled' => ['title' => 'Order :number cancelled', 'body' => 'Order :number (:total) at :store was cancelled.'],
    'customer' => [
        'greeting' => 'Hello :customer_first_name,',
        'view_order' => 'View order',
        'confirmation' => ['subject' => 'Order :order_number confirmed', 'body' => 'Thank you for your order :order_number at :store_name. Total: :order_total.'],
        'shipped' => ['subject' => 'Order :order_number shipped', 'body' => 'Your order :order_number is on its way. Tracking number: :shipment_tracking_no.'],
        'delivered' => ['subject' => 'Order :order_number delivered', 'body' => 'Your order :order_number has been delivered. Enjoy!'],
        'cancelled' => ['subject' => 'Order :order_number cancelled', 'body' => 'Your order :order_number at :store_name has been cancelled.'],
    ],
];
