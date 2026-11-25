<?php

namespace App\Modules\Catalog\Listeners\Requests;

use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Catalog\Services\Requests\RequestPaymentService;

/**
 * A paid Billing invoice settles the request payment it was issued for.
 */
final class SettleRequestPayment
{
    public function __construct(private readonly RequestPaymentService $payments) {}

    public function handle(InvoicePaid $event): void
    {
        if ($event->invoice->billable_type === 'request_payment') {
            $this->payments->markPaidByInvoice($event->invoice);
        }
    }
}
