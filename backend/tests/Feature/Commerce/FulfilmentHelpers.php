<?php

use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Reporting\Models\OrderFact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require_once __DIR__.'/StoreApiHelpers.php';
require_once __DIR__.'/CommerceHelpers.php';

/*
| Shared setup of the P5 batch 3 tests (fulfilment, returns, refunds, settlement):
| a paid storefront order (placed through the Store API, captured by Stripe with
| transaction pi_paid_1) and its order_facts row.
*/

if (! function_exists('paidShopOrder')) {
    /**
     * @param  array<int, int>  $lines  store_product_id => quantity
     */
    function paidShopOrder(object $shop, Customer $customer, array $lines, string $key = 'order-key-0001', ?string $source = null): Order
    {
        $data = shopPlaceOrder($shop, $customer, $lines, $key);
        $uuid = $data['orders'][0]['uuid'];
        tenancy()->end();

        return $shop->tenant->run(function () use ($uuid, $source): Order {
            $order = Order::query()->where('uuid', $uuid)->sole();

            if ($source !== null) {
                Order::query()->where('checkout_group', $order->checkout_group)->update(['payment_source' => $source]);
            }

            OrderPayment::query()->create([
                'reference' => 'PAY-'.Str::upper(Str::random(8)),
                'checkout_group' => $order->checkout_group,
                'gateway' => 'stripe',
                'source' => $source ?? 'central',
                'mode' => 'test',
                'status' => PaymentAttemptStatus::Paid,
                'amount' => Order::query()->where('checkout_group', $order->checkout_group)->sum('grand_total'),
                'currency' => 'USD',
                'transaction_id' => 'pi_paid_1',
                'completed_at' => now(),
            ]);

            app(OrderLifecycleService::class)->markGroupPaid($order->checkout_group, 'stripe');

            return $order->fresh(['items']) ?? $order;
        });
    }

    /**
     * Headers of the remote-modal loader (fetch with X-Requested-With).
     *
     * @return array<string, string>
     */
    function xhrHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    function factId(Order $order): int
    {
        return (int) OrderFact::withoutTenantScope()->where('order_uuid', $order->uuid)->value('id');
    }

    function orderIn(object $shop, Order $order): Order
    {
        return $shop->tenant->run(fn (): Order => Order::query()->with(['items', 'trackingEvents'])->where('uuid', $order->uuid)->sole());
    }

    /**
     * Stripe refund API fake: succeeded / failed / pending.
     */
    function fakeStripeRefund(string $status = 'succeeded'): void
    {
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_test_1', 'status' => $status, 'amount' => 100, 'currency' => 'usd', 'failure_reason' => $status === 'failed' ? 'expired_or_canceled_card' : null])]);
    }
}
