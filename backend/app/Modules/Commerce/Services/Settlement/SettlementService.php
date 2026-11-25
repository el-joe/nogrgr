<?php

namespace App\Modules\Commerce\Services\Settlement;

use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Services\GatewayPaymentService;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Events\SettlementPaid;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSettlement as SettlementRow;
use App\Modules\Commerce\Pricing\OrderSettlement;
use App\Modules\Commerce\Pricing\SettlementFigures;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Support\Collection;

/**
 * VEN-021d "Pay platform" (ORDER_FORMULAS.md §3): on a vendor-collected order the vendor
 * owes vendor_own_platform = platform_profit − Σ paid settlements. The breakdown splits
 * it for display (shipping = min(shipping_charge, due), product = due − shipping); the
 * full due is paid through the platform's vendor-payments gateway (GatewayPaymentService,
 * purpose settlement, payment log) and the capture marks the settlement paid.
 */
class SettlementService extends BaseService
{
    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly PaymentDriverRegistry $drivers,
        private readonly PaymentLogRepositoryContract $logs,
        private readonly OrderSettlementRepositoryContract $settlements,
        private readonly OrderFinanceService $finance,
    ) {}

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function gateways(): Collection
    {
        return $this->drivers->checkoutGateways(GatewayPurpose::VendorPayments);
    }

    /**
     * @return array{figures: SettlementFigures, due: Money, shipping: Money, product: Money}
     */
    public function breakdown(Order $order, int $tenantId): array
    {
        $figures = $this->finance->figures($order, $tenantId);
        $split = OrderSettlement::breakdown($figures->owedToPlatform, $order->money('shipping_charge'));

        return ['figures' => $figures, 'due' => $figures->owedToPlatform, 'shipping' => $split['shipping'], 'product' => $split['product']];
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{settlement: SettlementRow, redirect: string}
     *
     * @throws PaymentGatewayException
     */
    public function start(Order $order, int $tenantId, PaymentGatewayCode $gateway, Closure $returnUrls, ?int $staffId = null, ?string $email = null, ?string $locale = null): array
    {
        $breakdown = $this->breakdown($order, $tenantId);

        if (! $breakdown['due']->isPositive()) {
            throw CommerceException::rule('nothing_to_settle');
        }

        /** @var SettlementRow $settlement */
        $settlement = $this->settlements->create([
            'reference' => $this->settlements->nextReference(),
            'tenant_id' => $tenantId,
            'order_uuid' => $order->uuid,
            'order_number' => $order->number,
            'amount' => $breakdown['due']->minor,
            'shipping_cost' => $breakdown['shipping']->minor,
            'product_cost' => $breakdown['product']->minor,
            'currency' => $breakdown['due']->currency,
            'gateway' => $gateway->value,
            'status' => SettlementStatus::Pending,
            'paid_by_id' => $staffId,
        ]);

        try {
            $started = $this->payments->start(new StartPaymentData(
                gateway: $gateway,
                purpose: PaymentPurpose::Settlement,
                amount: $breakdown['due'],
                description: (string) __('commerce::settlement.payment_description', ['number' => $order->number]),
                customerEmail: $email,
                locale: $locale,
                tenantId: $tenantId,
                meta: ['settlement_id' => $settlement->id, 'order_uuid' => $order->uuid, 'order_number' => $order->number],
            ), $returnUrls);
        } catch (PaymentGatewayException $exception) {
            $this->settlements->update($settlement, ['status' => SettlementStatus::Failed]);

            throw $exception;
        }

        $this->settlements->update($settlement, ['payment_log_id' => $started['log']->id]);

        return ['settlement' => $settlement, 'redirect' => $started['redirect']];
    }

    /**
     * A settlement payment log of the store (and only that store's).
     */
    public function logFor(int $tenantId, int $logId): ?PaymentLog
    {
        /** @var PaymentLog|null $log */
        $log = $this->logs->query()->withoutGlobalScopes()->whereKey($logId)->first();

        return $log !== null && $log->tenant_id === $tenantId && $log->purpose === PaymentPurpose::Settlement ? $log : null;
    }

    public function settlementOf(PaymentLog $log): ?SettlementRow
    {
        return $this->settlements->findByPaymentLog($log->id);
    }

    public function confirm(PaymentLog $log): PaymentLog
    {
        try {
            return $this->payments->confirm($log);
        } catch (PaymentGatewayException) {
            return $log;
        }
    }

    public function cancel(PaymentLog $log): PaymentLog
    {
        return $this->payments->cancel($log);
    }

    /**
     * PaymentCaptured / PaymentFailed of a settlement log (idempotent).
     */
    public function applyOutcome(PaymentLog $log, PaymentOutcome $outcome): void
    {
        $settlement = $this->settlements->findByPaymentLog($log->id);

        if ($settlement === null || $settlement->status === SettlementStatus::Paid) {
            return;
        }

        $this->settlements->update($settlement, $outcome === PaymentOutcome::Paid
            ? ['status' => SettlementStatus::Paid, 'paid_at' => now()]
            : ['status' => SettlementStatus::Failed]);

        if ($outcome === PaymentOutcome::Paid) {
            SettlementPaid::dispatch($settlement);
        }
    }
}
