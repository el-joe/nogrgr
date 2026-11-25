<?php

/*
| Ledger transaction texts (decision #72). `descriptions` are stored in English with the
| transaction (search / export); `titles` / `subtitles` are rendered from its meta.
*/
return [
    'descriptions' => [
        'order_vendor_share' => 'Order #:number vendor share',
        'order_platform_share' => 'Order #:number platform share',
        'order_void' => 'Order #:number cancelled / refunded',
        'refund' => 'Refund :reference of order #:number',
        'settlement' => 'Settlement :reference for order #:number',
        'settlement_offset' => 'Settlement :reference for order #:number (payout offset)',
        'payout' => 'Payout :reference',
        'payout_cancelled' => 'Payout :reference cancelled',
        'subscription' => 'Subscription invoice :number',
        'addon' => 'Add-on invoice :number',
    ],
    'titles' => [
        'order_vendor_share' => 'Order #:number vendor share',
        'order_platform_share' => 'Order #:number platform share',
        'order_void' => 'Order #:number cancelled / refunded',
        'refund' => 'Refund :reference · order #:number',
        'settlement' => 'Settlement :reference · order #:number',
        'settlement_offset' => 'Settlement :reference · order #:number',
        'payout' => 'Payout :reference',
        'payout_cancelled' => 'Payout :reference cancelled',
        'subscription' => 'Subscription invoice :number',
        'addon' => 'Add-on invoice :number',
    ],
    'subtitles' => [
        'order_vendor_share' => ':percent% of :total',
        'order_platform_share' => 'collected by the store · :total',
        'order_void' => 'reverses the order share',
        'refund' => 'borne by the store',
        'settlement' => ':method',
        'settlement_offset' => 'deducted from payouts',
        'payout' => ':method',
        'payout_cancelled' => 'reversal',
        'subscription' => 'paid to the platform',
        'addon' => 'paid to the platform',
    ],
];
