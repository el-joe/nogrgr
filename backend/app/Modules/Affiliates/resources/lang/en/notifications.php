<?php

return [
    'activated' => [
        'title' => 'Your affiliate account is active',
        'body' => 'Share your referral code :code and earn commission on every store that subscribes.',
    ],
    'conversion_approved' => [
        'title' => 'Commission approved: :amount',
        'body' => ':store paid its first subscription (:reference). :amount was added to your balance.',
    ],
    'conversion_reversed' => [
        'title' => 'Commission reversed: :amount',
        'body' => 'The commission for :store (:reference) was reversed and :amount was removed from your balance.',
    ],
    'payout_paid' => [
        'title' => 'Payout sent: :amount',
        'body' => 'We sent you :amount (reference :reference).',
    ],
    'payout_rejected' => [
        'title' => 'Payout request rejected: :amount',
        'body' => 'Your payout request :reference for :amount was rejected.',
    ],
    'mail' => [
        'greeting' => 'Hi :name,',
        'label_account' => 'Affiliate account',
        'label_conversion' => 'Conversion',
        'label_payout' => 'Payout',
        'code' => 'Referral code',
        'referral_link' => 'Referral link',
        'store' => 'Store',
        'plan' => 'Plan',
        'commission' => 'Commission',
        'balance' => 'Balance',
        'amount' => 'Amount',
        'method' => 'Method',
        'reference' => 'Reference',
        'paid' => 'Paid',
        'rejected' => 'Rejected',
        'cta_dashboard' => 'Open affiliate dashboard',
        'footer' => 'You received this email because you have an affiliate account with :platform.',
    ],
    'admin' => [
        'payout_requested' => ['title' => 'Payout request :reference', 'body' => ':name asked for a payout of :amount.'],
    ],
];
