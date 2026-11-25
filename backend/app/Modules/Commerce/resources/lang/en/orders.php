<?php

return [
    'errors' => [
        'order_not_found' => 'Order not found.',
        'not_cancellable' => 'This order can no longer be cancelled.',
        'not_payable' => 'This order cannot be paid.',
        'payment_not_found' => 'Payment not found.',
        'checkout_in_progress' => 'Your order is already being placed. Please wait a moment.',
        'idempotency_mismatch' => 'This request key was already used for a different order.',
        'transition' => 'An order cannot move from ":from" to ":to".',
        'gateway_unknown' => 'Unknown payment gateway.',
        'webhook_rejected' => 'The notification could not be verified.',
    ],
    'messages' => [
        'cancelled' => 'Your order has been cancelled.',
    ],
    'notes' => [
        'payment_timeout' => 'Cancelled: payment was not completed in time.',
    ],
    'payment' => [
        'initiated' => 'Payment started.',
        'pending' => 'Payment is being processed.',
        'paid' => 'Payment completed successfully.',
        'failed' => 'Payment failed. Please try again.',
        'cancelled' => 'Payment was cancelled.',
    ],
    'payment_description' => 'Order :numbers',
];
