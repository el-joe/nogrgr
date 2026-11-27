<?php

namespace App\Modules\Inventory\Services\Alerts;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Inventory\Models\StockAlertSubscription;
use App\Modules\Inventory\Notifications\BackInStockNotification;
use App\Modules\Inventory\Repositories\Contracts\StockAlertSubscriptionRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Back-in-stock subscriptions (decision #124): the "Notify me" form of an out-of-stock
 * product page (all theme families) and POST /store-api/v1/products/{id}/stock-alerts.
 * A signed-in customer subscribes with the account e-mail (inbox + push + e-mail per the
 * "back in stock" preference); a guest gives an e-mail (e-mail only). When the stock
 * returns every pending row is claimed (notified_at) and gets exactly one notice.
 */
class BackInStockService extends BaseService
{
    public function __construct(
        private readonly StockAlertSubscriptionRepositoryContract $subscriptions,
        private readonly StoreCatalogReader $reader,
        private readonly CustomerRepositoryContract $customers,
    ) {}

    /**
     * @throws ValidationException when the product is unknown, inactive or in stock
     */
    public function subscribe(int $storeProductId, ?Customer $customer, ?string $email, string $locale): StockAlertSubscription
    {
        /** @var StoreProduct|null $product */
        $product = StoreProduct::query()->whereKey($storeProductId)->where('is_active', true)->first();

        if ($product === null) {
            throw ValidationException::withMessages(['product' => __st('product.stock_alert.unavailable')]);
        }

        $view = $this->reader->view($this->reader->load($product));

        if ($view->stock > 0) {
            throw ValidationException::withMessages(['product' => __st('product.stock_alert.in_stock')]);
        }

        $address = mb_strtolower(trim((string) ($customer?->email ?: $email)));

        if ($address === '') {
            throw ValidationException::withMessages(['email' => __st('product.stock_alert.email_required')]);
        }

        return $this->subscriptions->subscribe($product->id, $address, $customer?->id, $locale);
    }

    public function isSubscribed(int $storeProductId, ?Customer $customer): bool
    {
        return $customer !== null && filled($customer->email) && $this->subscriptions->isPending($storeProductId, mb_strtolower((string) $customer->email));
    }

    /**
     * Sends the single back-in-stock notice to every pending subscriber of the product.
     *
     * @return int notices sent
     */
    public function notifySubscribers(StoreProductView $view): int
    {
        $sent = 0;
        $variables = $this->variables($view);

        foreach ($this->subscriptions->pending($view->product->id) as $subscription) {
            if ($this->subscriptions->claim([$subscription->id]) !== 1) {
                continue;
            }

            $customer = $subscription->customer_id !== null ? $this->customers->find($subscription->customer_id) : null;
            $notification = new BackInStockNotification($view->product->id, $variables, $subscription->locale);

            if ($customer instanceof Customer) {
                $customer->notify($notification);
            } else {
                Notification::route('mail', $subscription->email)->notify($notification);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * @return array<string, string>
     */
    private function variables(StoreProductView $view): array
    {
        $tenant = tenant();
        $base = $tenant instanceof Tenant ? StoreSettingsService::storeUrl($tenant) : rtrim(url('/'), '/');

        return [
            'store.name' => $tenant instanceof Tenant ? (string) $tenant->name : '',
            'store.url' => $base,
            'product.name' => $view->name,
            'product.url' => $base.route('storefront.product', ['product' => $view->product->id], false),
        ];
    }
}
