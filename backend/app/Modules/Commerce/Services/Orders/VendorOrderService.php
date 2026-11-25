<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Contracts\OrderRefundLedger;
use App\Modules\Commerce\DTOs\Orders\CancelOrderData;
use App\Modules\Commerce\DTOs\Orders\FulfilmentUpdateData;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Commerce\Support\TenantRunner;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor order pages (VEN-020 / 021, tenant context): KPIs, CSV export, the detail with
 * totals and the settlement figures (same numbers as the admin panel), staff cancel and
 * — own-products orders only — the shipping status.
 */
class VendorOrderService extends BaseService
{
    public function __construct(
        private readonly OrderRepositoryContract $orders,
        private readonly OrderPaymentRepositoryContract $payments,
        private readonly OrderSettlementRepositoryContract $settlements,
        private readonly OrderFinanceService $finance,
        private readonly OrderCancellationService $cancellation,
        private readonly OrderFulfilmentService $fulfilment,
        private readonly TenantRunner $tenants,
        private readonly OrderRefundLedger $refundLedger,
    ) {}

    public function tenantId(): int
    {
        return (int) $this->tenants->currentTenantId();
    }

    public function find(string $uuid): Order
    {
        return $this->orders->findDetailed($uuid) ?? throw CommerceException::rule('order_not_found', 404);
    }

    /**
     * @return array{orders: int, paid: int, processing: int, collected: Money}
     */
    public function stats(): array
    {
        $stats = $this->orders->stats();

        // Collected = paid, not void orders less what was refunded on them (QA-P5-010).
        $refunded = $this->refundLedger->refundedForOrders($this->tenantId(), $this->orders->collectedUuids());

        return ['orders' => $stats['orders'], 'paid' => $stats['paid'], 'processing' => $stats['processing'], 'collected' => Money::ofMinor($stats['collected'])->subtract($refunded)->max(Money::zero())];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Order $order): array
    {
        $tenantId = $this->tenantId();

        return [
            'figures' => $this->finance->figures($order, $tenantId),
            'remaining' => $this->finance->remaining($order, $tenantId),
            'cancelPreview' => $this->finance->cancellationPreview($order, $tenantId),
            'canCancel' => $this->cancellation->canCancel($order),
            'payment' => $this->payments->paidForGroup($order->checkout_group),
            'customerOrders' => $order->customer_id !== null ? $this->orders->placedCountForCustomer($order->customer_id) : 0,
            'settlements' => $this->settlements->forOrder($tenantId, $order->uuid),
        ];
    }

    public function cancel(Order $order, CancelOrderData $data): Order
    {
        return $this->cancellation->cancel($order, $data);
    }

    public function updateShipping(Order $order, FulfilmentUpdateData $data): Order
    {
        $this->fulfilment->assertStoreManageable($order);

        return $this->fulfilment->update($order, $data);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->orders->listing($filters)->orderByDesc('id');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['order', 'customer', 'status', 'payment', 'gateway', 'total_usd', 'your_share_usd', 'platform_share_usd', 'placed_at']);

            $query->chunk(500, static function ($orders) use ($out): void {
                /** @var Order $order */
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->number,
                        (string) $order->customer_name,
                        $order->status->value,
                        $order->payment_status->value,
                        (string) $order->payment_gateway,
                        $order->money('grand_total')->toDecimal(),
                        $order->money('vendor_profit')->toDecimal(),
                        $order->money('platform_profit')->toDecimal(),
                        (string) $order->placed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
