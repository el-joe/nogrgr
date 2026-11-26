<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Models\Order;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * Temporary signed link to the printable invoice of an order on the store's own domain
 * (`storefront.orders.invoice.signed`): the app opens it in a browser / web view to print
 * or save as PDF without a session. Valid for 30 minutes, for that order only.
 */
class OrderInvoiceLinks
{
    public const TTL_MINUTES = 30;

    /**
     * @return array{url: string, expires_at: string}
     */
    public function link(Order $order): array
    {
        $expires = CarbonImmutable::now()->addMinutes(self::TTL_MINUTES);
        $path = URL::temporarySignedRoute('storefront.orders.invoice.signed', $expires, ['order' => $order->uuid], absolute: false);
        $tenant = tenancy()->initialized ? tenant() : null;
        $base = $tenant instanceof Tenant ? StoreSettingsService::storeUrl($tenant) : rtrim(url('/'), '/');

        return ['url' => $base.$path, 'expires_at' => $expires->toIso8601String()];
    }
}
