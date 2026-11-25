<?php

/*
| Platform billing (Billing module). Amounts are USD; rates in basis points (1500 = 15 %).
| Defaults only: Admin › Settings › Platform › Billing overrides them at runtime
| (decision #69, IntegrationConfigOverlay). The seller name falls back to the platform name.
*/

return [
    'tax_rate_bp' => 0,

    'invoice_due_days' => 7,

    // Days a past-due subscription keeps working before it expires.
    'grace_days' => 7,

    // Seller block printed on invoices.
    'seller' => [
        'name' => null,
        'address' => null,
        'tax_number' => null,
    ],
];
