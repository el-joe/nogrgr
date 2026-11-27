<?php

namespace App\Modules\Integrations\Listeners;

use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Services\Limits\GatewayLimitService;

/**
 * A storefront order paid through the PLATFORM account of a gateway adds its total to
 * the store's monthly gateway volume (ADM-100c limits). Own-key and COD orders do not count.
 */
class RecordGatewayVolume
{
    public function __construct(private readonly GatewayLimitService $limits) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $code = is_string($order->payment_gateway) ? PaymentGatewayCode::tryFrom($order->payment_gateway) : null;

        if ($order->payment_source !== PaymentSource::Central || $code === null) {
            return;
        }

        $this->limits->record($event->tenantId, $code, $order->money('grand_total')->minor);
    }
}
