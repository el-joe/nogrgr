<?php

/*
| Platform billing (Billing module). Amounts are USD; rates in basis points (1500 = 15 %).
*/

return [
    'tax_rate_bp' => (int) env('BILLING_TAX_RATE_BP', 0),

    'invoice_due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),

    // Days a past-due subscription keeps working before it expires.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Seller block printed on invoices.
    'seller' => [
        'name' => env('BILLING_SELLER_NAME', env('APP_NAME', 'Nogrgr')),
        'address' => env('BILLING_SELLER_ADDRESS'),
        'tax_number' => env('BILLING_SELLER_TAX_NUMBER'),
    ],
];
