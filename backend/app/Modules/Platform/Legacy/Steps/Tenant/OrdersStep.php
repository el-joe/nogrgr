<?php

namespace App\Modules\Platform\Legacy\Steps\Tenant;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Models\Order;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Reporting\Listeners\RecordOrderFacts;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Store orders, items, payment attempt and status history, money as USD minor units.
 *
 * The old stored snapshot is the truth (ORDER_FORMULAS.md): every line keeps its own
 * cost / profit_amount / price / sub_total / fixed_shipping / discount / tax /
 * grand_total, converted with Money::of (old OrderPricing::money rounding) — nothing is
 * re-priced. Order sums are the stored line sums (items_grand_total = Σ line
 * grand_total, order grand_total = items + shipping − coupon, section 1), the old
 * platform / vendor split is copied and checked (platform + vendor = grand total).
 * Old order numbers are kept: number = old order id, uuid = old uuid. The central
 * `order_facts` row is written through the Reporting listener, as for a live order.
 */
class OrdersStep implements LegacyStepHandler
{
    /** @var array<int, object> store_products rows of the current chunk (prefetched, no per-line query) */
    private array $storeRows = [];

    public function __construct(private readonly RecordOrderFacts $facts) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Orders;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->storeSource();

        if (! $context->source->hasTable($source, 'orders')) {
            return;
        }

        $hasActivities = $context->source->hasTable($source, 'order_activities');

        $source->table('orders')->orderBy('id')->chunkById(200, function (Collection $rows) use ($context, $source, $hasActivities): void {
            $ids = $rows->pluck('id')->all();
            $items = $source->table('order_items')->whereIn('order_id', $ids)->orderBy('id')->get()->groupBy('order_id');
            $activities = $hasActivities ? $source->table('order_activities')->whereIn('order_id', $ids)->orderBy('id')->get()->groupBy('order_id') : collect();
            $names = $this->names($context, $items->flatten());
            $written = [];

            $context->storeWriter()->transaction(function () use ($rows, $context, $items, $activities, $names, &$written): void {
                foreach ($rows as $row) {
                    $id = $this->order($context, $row, $items->get($row->id, collect()), $activities->get($row->id, collect()), $names);

                    if ($id !== null) {
                        $written[] = $id;
                    }
                }
            });

            $this->recordFacts($context, $written);
        });
    }

    /**
     * @param  array<string, string>  $names
     */
    private function order(LegacyContext $context, object $row, Collection $items, Collection $activities, array $names): ?int
    {
        $writer = $context->storeWriter();
        $scope = $context->scope();
        $context->report->read($scope, 'orders');

        if ($items->isEmpty()) {
            $context->report->issue(LegacyIssueLevel::Warning, $scope, 'orders', (int) $row->id, 'Order without lines (skipped)');
            $context->report->skipped($scope, 'orders');

            return null;
        }

        $lines = [];
        $own = true;

        foreach ($items as $item) {
            $line = $this->line($context, $item, $names);
            $own = $own && $line['product_source'] === StoreProductSource::Own->value;
            $lines[] = $line;
        }

        $sum = static fn (string $column): int => array_sum(array_column($lines, $column));
        $itemsGrandTotal = $sum('grand_total');
        $shipping = LegacyValues::minor($row->shipping_charge ?? 0);
        $coupon = LegacyValues::minor($row->coupon_discount ?? 0);
        $grandTotal = $itemsGrandTotal + $shipping - $coupon;
        $platform = LegacyValues::minor($row->platform_profit ?? 0);
        $vendor = LegacyValues::minor($row->vendor_profit ?? 0);
        [$status, $payment] = $this->status($row);

        if ($grandTotal < 0) {
            $context->report->issue(LegacyIssueLevel::Error, $scope, 'orders', (int) $row->id, 'Negative order total');
        }

        if (! in_array($status, [OrderStatus::Cancelled, OrderStatus::Refunded], true) && abs(($platform + $vendor) - $grandTotal) > 1) {
            $context->report->issue(LegacyIssueLevel::Error, $scope, 'orders', (int) $row->id, 'Platform + vendor profit differ from the order total by more than 1 cent (bad money)');
        }

        $address = LegacyValues::json($row->shipping_address ?? null) ?? [];
        $countryId = isset($address['country_id']) ? $context->central->id('country', (int) $address['country_id']) : null;
        $customerId = $row->customer_id !== null ? $writer->id('customer', (int) $row->customer_id) : null;

        if ($row->customer_id !== null && $customerId === null) {
            $context->report->issue(LegacyIssueLevel::Warning, $scope, 'orders', (int) $row->id, 'Order customer missing (kept as guest order)');
        }

        $uuid = Str::isUuid((string) ($row->uuid ?? '')) ? (string) $row->uuid : (string) Str::uuid();
        $source = ($row->collected_by ?? 'vendor') === 'platform' ? PaymentSource::Central : PaymentSource::Tenant;
        $placed = $row->created_at;
        $couponSource = in_array($row->coupon_source ?? null, ['central', 'vendor'], true) ? $row->coupon_source : null;
        $totals = [
            'items_grand_total' => $itemsGrandTotal,
            'tax' => $sum('tax'),
            'shipping_charge' => $shipping,
            'coupon_discount' => $coupon,
            'coupon_source' => $couponSource,
            'grand_total' => $grandTotal,
            'platform_profit' => $platform,
            'vendor_profit' => $vendor,
            'lines' => [],
            'legacy' => true,
        ];

        $orderId = $writer->upsert('order', (int) $row->id, 'orders', [
            'uuid' => $uuid,
            'number' => (string) $row->id,
            'checkout_group' => Str::isUuid((string) ($row->order_group_uuid ?? '')) ? $row->order_group_uuid : $uuid,
            'kind' => ($own ? OrderKind::Own : OrderKind::Catalog)->value,
            'status' => $status->value,
            'payment_status' => $payment->value,
            'customer_id' => $customerId,
            'customer_name' => isset($address['full_name']) ? mb_substr((string) $address['full_name'], 0, 120) : null,
            'customer_email' => isset($address['email']) ? mb_substr((string) $address['email'], 0, 120) : null,
            'customer_phone' => isset($address['phone']) ? mb_substr((string) $address['phone'], 0, 32) : null,
            'country_id' => $countryId,
            'shipping_address' => $address === [] ? null : $address,
            'currency' => 'USD',
            'items_grand_total' => $itemsGrandTotal,
            'display_subtotal' => $sum('sub_total') + $sum('fixed_shipping'),
            'flash_discount' => $sum('discount'),
            'tax' => $sum('tax'),
            'shipping_charge' => $shipping,
            'coupon_discount' => $coupon,
            'coupon_source' => $couponSource,
            'coupon_code' => isset($row->coupon_code) ? mb_substr((string) $row->coupon_code, 0, 40) : null,
            'grand_total' => $grandTotal,
            'platform_profit' => $platform,
            'vendor_profit' => $vendor,
            'totals' => $totals,
            'payment_gateway' => $row->payment_method !== null ? mb_substr((string) $row->payment_method, 0, 32) : null,
            'payment_source' => $source->value,
            'tracking_number' => isset($row->tracking_number) ? mb_substr((string) $row->tracking_number, 0, 64) : null,
            'cancel_reason' => isset($row->cancellation_reason) ? mb_substr((string) $row->cancellation_reason, 0, 500) : null,
            'placed_at' => $placed,
            'paid_at' => in_array($payment, [PaymentStatus::Paid, PaymentStatus::Refunded], true) ? $placed : null,
            'delivered_at' => in_array($status, [OrderStatus::Delivered, OrderStatus::Completed], true) ? $row->updated_at : null,
            'cancelled_at' => $row->cancelled_at ?? ($status === OrderStatus::Cancelled ? $row->updated_at : null),
            'refunded_at' => $row->refunded_at ?? null,
            'fulfilment_status' => (in_array($status, [OrderStatus::Delivered, OrderStatus::Completed], true) ? FulfilmentStatus::Delivered : FulfilmentStatus::Pending)->value,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at ?? now(),
        ]);

        $writer->syncPivot('order_items', 'order_id', $orderId, array_map(static fn (array $line): array => ['order_id' => $orderId, 'created_at' => $row->created_at, 'updated_at' => $row->created_at] + $line, $lines));
        $this->history($context, $orderId, $row, $status, $activities);
        $this->payment($context, (int) $row->id, $uuid, $row, $status, $payment, $source, $grandTotal);

        return $orderId;
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function line(LegacyContext $context, object $item, array $names): array
    {
        $writer = $context->storeWriter();
        $scope = $context->scope();
        $tenantProduct = $item->product_id !== null ? (int) $item->product_id : null;
        $storeProductId = $tenantProduct !== null ? $writer->id('tenant_product', $tenantProduct) : null;
        $centralOld = $item->catalog_product_id ?? null;

        if ($storeProductId === null && $centralOld !== null) {
            $storeProductId = $writer->id('store_product', 'c'.(int) $centralOld);
        }

        $row = $storeProductId !== null ? ($this->storeRows[$storeProductId] ?? null) : null;
        $sourceKind = $row !== null ? (string) $row->source : ($centralOld !== null ? StoreProductSource::Central->value : StoreProductSource::Own->value);
        $productId = $sourceKind === StoreProductSource::Central->value ? (int) ($row->central_product_id ?? $context->central->id('product', (int) $centralOld) ?? 0) : (int) ($storeProductId ?? 0);
        $variantOld = $item->product_variant_id ?? null;
        $variantKey = $variantOld !== null ? (int) ($writer->id('tenant_variant', (int) $variantOld) ?? 0) : 0;

        if ($storeProductId === null) {
            $context->report->issue(LegacyIssueLevel::Warning, $scope, 'order_items', (int) $item->id, 'Order line product no longer exists (line kept with its money snapshot)');
        }

        foreach (['cost', 'price', 'discount', 'grand_total'] as $column) {
            if (LegacyValues::losesPrecision($item->{$column} ?? 0)) {
                $context->report->issue(LegacyIssueLevel::Info, $scope, 'order_items', (int) $item->id, "Sub-cent {$column} rounded to cents");
            }
        }

        return [
            'line_key' => 'legacy-'.$item->id,
            'store_product_id' => max(0, (int) $storeProductId),
            'product_source' => $sourceKind,
            'product_id' => max(0, $productId),
            'variant_key' => max(0, $variantKey),
            'name' => $names[$sourceKind.':'.$productId] ?? 'Item #'.$item->id,
            'quantity' => (int) ($item->qty ?? 1),
            'cost' => LegacyValues::minor($item->cost ?? 0),
            'profit_amount' => LegacyValues::minor($item->profit_amount ?? 0),
            'price' => LegacyValues::minor($item->price ?? 0),
            'sub_total' => LegacyValues::minor($item->sub_total ?? 0),
            'fixed_shipping' => LegacyValues::minor($item->fixed_shipping ?? 0),
            'discount' => LegacyValues::minor($item->discount ?? 0),
            'discount_source' => in_array($item->discount_source ?? null, ['central', 'vendor'], true) ? $item->discount_source : null,
            'tax' => LegacyValues::minor($item->tax ?? 0),
            'shipping_fee' => LegacyValues::minor($item->shipping_fee ?? 0),
            'grand_total' => LegacyValues::minor($item->grand_total ?? 0),
            'weight_grams' => (int) round((float) ($item->weight ?? 0) * 1000),
        ];
    }

    /**
     * @return array{0: OrderStatus, 1: PaymentStatus}
     */
    private function status(object $row): array
    {
        $paid = (bool) ($row->paid ?? false);
        $cod = in_array(strtolower((string) ($row->payment_method ?? '')), ['cod', 'cash', 'cash_on_delivery'], true);
        $status = match ((string) $row->status) {
            'processing' => OrderStatus::Processing,
            'shipped' => OrderStatus::Shipped,
            'delivered' => OrderStatus::Delivered,
            'completed' => OrderStatus::Completed,
            'cancelled', 'rejected' => OrderStatus::Cancelled,
            'refunded' => OrderStatus::Refunded,
            default => $paid ? OrderStatus::Paid : ($cod ? OrderStatus::Processing : OrderStatus::PendingPayment),
        };
        $payment = match (true) {
            $status === OrderStatus::Refunded => PaymentStatus::Refunded,
            $paid => PaymentStatus::Paid,
            $cod => PaymentStatus::CashOnDelivery,
            default => PaymentStatus::Unpaid,
        };

        return [$status, $payment];
    }

    private function history(LegacyContext $context, int $orderId, object $row, OrderStatus $status, Collection $activities): void
    {
        $rows = [];

        foreach ($activities as $activity) {
            $to = OrderStatus::tryFrom((string) ($activity->status ?? '')) ?? $status;
            $rows[] = ['order_id' => $orderId, 'from_status' => null, 'to_status' => $to->value, 'actor_type' => ActorType::System->value, 'actor_id' => null, 'note' => mb_substr(trim($activity->title.' '.($activity->description ?? '')), 0, 500), 'created_at' => $activity->created_at];
        }

        if ($rows === []) {
            $rows[] = ['order_id' => $orderId, 'from_status' => null, 'to_status' => $status->value, 'actor_type' => ActorType::System->value, 'actor_id' => null, 'note' => 'Imported from the previous platform', 'created_at' => $row->created_at];
        }

        $context->storeWriter()->syncPivot('order_status_history', 'order_id', $orderId, $rows);
    }

    private function payment(LegacyContext $context, int $legacyId, string $uuid, object $row, OrderStatus $status, PaymentStatus $payment, PaymentSource $source, int $amount): void
    {
        if ($row->payment_method === null || $payment === PaymentStatus::CashOnDelivery) {
            return;
        }

        $details = LegacyValues::json($row->payment_details ?? null) ?? [];
        $attempt = match ($payment) {
            PaymentStatus::Paid, PaymentStatus::Refunded => PaymentAttemptStatus::Paid,
            default => $status === OrderStatus::Cancelled ? PaymentAttemptStatus::Cancelled : PaymentAttemptStatus::Pending,
        };

        $context->storeWriter()->upsert('order_payment', $legacyId, 'order_payments', [
            'reference' => 'legacy_'.$legacyId.'_'.substr(md5($uuid), 0, 12),
            'checkout_group' => Str::isUuid((string) ($row->order_group_uuid ?? '')) ? $row->order_group_uuid : $uuid,
            'gateway' => mb_substr((string) $row->payment_method, 0, 32),
            'source' => $source->value,
            'mode' => 'live',
            'status' => $attempt->value,
            'amount' => $amount,
            'currency' => 'USD',
            'transaction_id' => isset($details['transaction_id']) && is_scalar($details['transaction_id']) ? mb_substr((string) $details['transaction_id'], 0, 255) : null,
            'details' => ['legacy' => true],
            'completed_at' => $attempt === PaymentAttemptStatus::Paid ? $row->created_at : null,
            'created_at' => $row->created_at,
        ]);
    }

    /**
     * Line name snapshots (the old lines had none): store / central product names, one query each per chunk.
     *
     * @return array<string, string> "<source>:<product id>" => name
     */
    private function names(LegacyContext $context, Collection $items): array
    {
        $writer = $context->storeWriter();
        $storeIds = $items->pluck('product_id')->filter()->map(fn (mixed $id): ?int => $writer->id('tenant_product', (int) $id))
            ->merge($items->pluck('catalog_product_id')->filter()->map(fn (mixed $id): ?int => $writer->id('store_product', 'c'.(int) $id)))
            ->filter(static fn (?int $id): bool => $id !== null && $id > 0)->unique()->values()->all();
        $this->storeRows = [];

        if ($storeIds === []) {
            return [];
        }

        $out = [];
        $stores = $writer->connection()->table('store_products')->whereIn('id', $storeIds)->get(['id', 'source', 'central_product_id', 'name']);
        $this->storeRows = $stores->keyBy('id')->all();
        $central = $context->central->connection()->table('products')->whereIn('id', $stores->pluck('central_product_id')->filter()->all())->pluck('name', 'id')->all();

        foreach ($stores as $store) {
            if ($store->source === StoreProductSource::Central->value) {
                $out['central:'.$store->central_product_id] = $this->english($central[$store->central_product_id] ?? null);
            } else {
                $out['own:'.$store->id] = $this->english($store->name);
            }
        }

        return array_filter($out);
    }

    private function english(mixed $json): string
    {
        $names = LegacyValues::json($json) ?? [];

        return mb_substr((string) ($names['en'] ?? reset($names) ?: ''), 0, 255);
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function recordFacts(LegacyContext $context, array $orderIds): void
    {
        if ($context->dryRun() || $context->tenant === null) {
            return;
        }

        foreach (Order::query()->whereIn('id', $orderIds)->get() as $order) {
            $this->facts->handle(new OrderStatusChanged($order, (int) $context->tenant->id, null, $order->status));
        }
    }
}
