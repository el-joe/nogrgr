<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\DTOs\ManualRefundData;
use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Events\RefundFailed;
use App\Modules\AfterSales\Events\ReturnStatusChanged;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Jobs\ProcessRefund;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Pricing\RefundBreakdown;
use App\Modules\Commerce\Pricing\RefundCalculator;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Commerce\Services\Stock\FulfilmentStockService;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Integrations\DTOs\RefundNotification;
use App\Modules\Integrations\DTOs\RefundRequestData;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Enums\RefundOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use InvalidArgumentException;

/**
 * Refunds of store orders (ADM-024 / VEN-023, decision #69). Amounts come only from
 * Commerce\Pricing\RefundCalculator (cancellation = everything left, return = line value
 * less its coupon share − fee, manual ≤ refundable). A refund is sent back through the
 * gateway that captured the order (store's own keys or the platform's, Stripe / PayPal
 * drivers, queued ProcessRefund) and every call is written to the refund's payment log;
 * cash / unsupported gateways are "manual" and completed by staff. Completion may move
 * the order to refunded / returned and the return to refunded; RefundCompleted notifies
 * and asks Affiliates to reverse a platform-collected conversion.
 */
class RefundService extends BaseService
{
    public function __construct(
        private readonly RefundRepositoryContract $refunds,
        private readonly ReturnRequestRepositoryContract $returns,
        private readonly OrderRepositoryContract $orders,
        private readonly OrderPaymentRepositoryContract $payments,
        private readonly OrderFinanceService $finance,
        private readonly OrderLifecycleService $lifecycle,
        private readonly FulfilmentStockService $stock,
        private readonly StorefrontGatewayService $gateways,
        private readonly TenantRunner $tenants,
    ) {}

    /**
     * Order of a store, read in its tenant database.
     */
    public function order(int $tenantId, string $orderUuid): Order
    {
        $order = $this->tenants->run($tenantId, fn (): ?Order => $this->orders->findDetailed($orderUuid));

        return $order ?? throw AfterSalesException::rule('order_not_found', 404);
    }

    public function remaining(int $tenantId, Order $order): Money
    {
        return $this->tenants->run($tenantId, fn (): Money => $this->finance->remaining($order, $tenantId));
    }

    public function createManual(int $tenantId, Order $order, ManualRefundData $data, Actor $actor): Refund
    {
        $remaining = $this->remaining($tenantId, $order);

        if (! $remaining->isPositive()) {
            throw AfterSalesException::rule('nothing_refundable');
        }

        try {
            $breakdown = RefundCalculator::manual($this->tenants->run($tenantId, fn () => $this->finance->refundable($order, $tenantId)), $data->amount);
        } catch (InvalidArgumentException) {
            throw AfterSalesException::rule('amount_exceeds', 422, ['max' => $remaining->format()]);
        }

        return $this->create($tenantId, $order, RefundSource::Manual, $breakdown, $data->reason, $actor, $data->restock);
    }

    /**
     * Full refund of a cancelled order that was paid online (nothing when already covered).
     */
    public function createForCancellation(int $tenantId, Order $order, Actor $actor): ?Refund
    {
        if (! $order->isPaidOnline()) {
            return null;
        }

        $breakdown = $this->tenants->run($tenantId, fn (): ?RefundBreakdown => $this->finance->cancellationPreview($order, $tenantId));

        if ($breakdown === null) {
            return null;
        }

        return $this->create($tenantId, $order, RefundSource::Cancellation, $breakdown, (string) ($order->cancel_reason ?: __('after-sales::refunds.reasons.cancellation')), $actor);
    }

    /**
     * Largest refund a return still allows: its fixed value less every refund already
     * raised for it that holds money (pending, processing, failed-retryable, completed),
     * capped by the order's refundable remainder. Never negative.
     */
    public function maxForReturn(ReturnRequest $return, Order $order): Money
    {
        $raised = Money::ofMinor($this->refunds->sumForReturn($return->id, RefundStatus::reservingValues()));

        return $return->money('refund_amount')->subtract($raised)->min($this->remaining($return->tenant_id, $order))->max(Money::zero());
    }

    public function createForReturn(ReturnRequest $return, Order $order, Money $amount, Actor $actor): Refund
    {
        $max = $this->maxForReturn($return, $order);

        if (! $amount->isPositive() || $amount->greaterThan($max)) {
            throw AfterSalesException::rule('amount_exceeds', 422, ['max' => $max->format()]);
        }

        $breakdown = new RefundBreakdown(
            itemsAmount: $return->money('items_amount')->min($amount),
            shippingAmount: $return->money('shipping_amount')->min($amount->subtract($return->money('items_amount')->min($amount))),
            returnFee: $return->money('return_fee'),
            amount: $amount,
            remaining: $max,
        );

        return $this->create($return->tenant_id, $order, RefundSource::Return, $breakdown, (string) __('after-sales::refunds.reasons.return', ['reference' => $return->reference]), $actor, false, $return);
    }

    /**
     * Sends a pending / failed refund to its gateway (ProcessRefund job).
     */
    public function process(Refund $refund, Actor $actor): Refund
    {
        $refund = $this->refunds->lock($refund);

        if (! in_array($refund->status, [RefundStatus::Pending, RefundStatus::Processing, RefundStatus::Failed], true) || $refund->method !== RefundMethod::OriginalPayment) {
            return $refund;
        }

        $attempt = $refund->attempts + 1;
        $this->refunds->update($refund, ['status' => RefundStatus::Processing, 'attempts' => $attempt, 'failure_reason' => null]);

        try {
            [$code, $config, $transaction] = $this->tenants->run($refund->tenant_id, function () use ($refund): array {
                $order = $this->orders->findByUuid($refund->order_uuid) ?? throw AfterSalesException::rule('order_not_found', 404);
                $payment = $this->payments->paidForGroup($order->checkout_group);
                $code = $payment !== null ? $payment->gateway : PaymentGatewayCode::tryFrom((string) $refund->gateway);

                if ($code === null || ! $this->gateways->supports($code)) {
                    throw AfterSalesException::rule('gateway_unsupported');
                }

                return [$code, $this->gateways->configFor($code), $payment !== null ? ($payment->transaction_id ?? $refund->original_transaction_id) : $refund->original_transaction_id];
            });

            if ($config === null || $transaction === null || $transaction === '') {
                throw AfterSalesException::rule('no_transaction');
            }

            $this->refunds->update($refund, ['original_transaction_id' => $transaction]);
            $result = $this->gateways->driver($code)->refund($config, new RefundRequestData($transaction, $refund->money(), $refund->reference.'-'.$attempt, $refund->reason));
        } catch (PaymentGatewayException|AfterSalesException $exception) {
            return $this->fail($refund, $exception->getMessage(), $actor);
        }

        $this->refunds->logAttempt($refund, [
            'gateway' => $code->value,
            'status' => match ($result->outcome) {
                RefundOutcome::Completed => RefundStatus::Completed,
                RefundOutcome::Pending => RefundStatus::Processing,
                RefundOutcome::Failed => RefundStatus::Failed,
            },
            'amount' => $refund->amount,
            'gateway_refund_id' => $result->refundId,
            'error' => $result->reason,
            'details' => $result->details,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
        ]);

        return match ($result->outcome) {
            RefundOutcome::Completed => $this->finish($refund, $actor, $result->refundId),
            RefundOutcome::Pending => $this->refunds->update($refund, ['gateway_refund_id' => $result->refundId]),
            RefundOutcome::Failed => $this->fail($refund, $result->reason ?? (string) __('after-sales::refunds.declined'), $actor, false),
        };
    }

    /**
     * A gateway webhook reports the outcome of a refund that was sent to it (decision
     * #72): completed → finish (return refunded, order status, RefundCompleted); failed →
     * failed (staff may retry). Only refunds still pending / processing move; a replay or
     * an update of a final refund changes nothing.
     */
    public function applyGatewayUpdate(Refund $refund, RefundNotification $update): Refund
    {
        $actor = Actor::system();

        /** @var array{0: Refund, 1: bool} $locked */
        $locked = $this->transaction(function () use ($refund, $update, $actor): array {
            $refund = $this->refunds->lock($refund);

            if (! in_array($refund->status, [RefundStatus::Pending, RefundStatus::Processing], true) || $update->outcome === RefundOutcome::Pending) {
                return [$refund, false];
            }

            $this->refunds->logAttempt($refund, [
                'gateway' => $refund->gateway,
                'status' => $update->outcome === RefundOutcome::Completed ? RefundStatus::Completed : RefundStatus::Failed,
                'amount' => $refund->amount,
                'gateway_refund_id' => $update->refundId,
                'error' => $update->reason,
                'details' => ['source' => 'webhook'],
                'actor_type' => $actor->type,
                'actor_id' => null,
            ]);

            return [$refund, true];
        }, $refund->getConnectionName());

        [$refund, $changed] = $locked;

        if (! $changed) {
            return $refund;
        }

        return $update->outcome === RefundOutcome::Completed
            ? $this->finish($refund, $actor, $update->refundId)
            : $this->fail($refund, $update->reason ?? (string) __('after-sales::refunds.declined'), $actor, false);
    }

    public function retry(Refund $refund, ?string $note, Actor $actor): Refund
    {
        if (! in_array($refund->status, [RefundStatus::Pending, RefundStatus::Failed], true) || $refund->method !== RefundMethod::OriginalPayment) {
            throw AfterSalesException::rule('refund_not_retryable');
        }

        $this->refunds->update($refund, ['notes' => $this->appendNote($refund, $note)]);
        ProcessRefund::dispatch($refund->id, $actor);

        return $refund->refresh();
    }

    /**
     * Staff confirm the money was paid back outside the gateway.
     */
    public function markCompleted(Refund $refund, ?string $note, Actor $actor): Refund
    {
        if (! $refund->status->isOpen()) {
            throw AfterSalesException::rule('refund_closed');
        }

        $this->refunds->update($refund, ['notes' => $this->appendNote($refund, $note)]);
        $this->refunds->logAttempt($refund, ['gateway' => null, 'status' => RefundStatus::Completed, 'amount' => $refund->amount, 'error' => $note, 'actor_type' => $actor->type, 'actor_id' => $actor->id]);

        return $this->finish($refund, $actor, null);
    }

    public function reject(Refund $refund, string $note, Actor $actor): Refund
    {
        if (! $refund->status->isOpen()) {
            throw AfterSalesException::rule('refund_closed');
        }

        $this->refunds->update($refund, [
            'status' => RefundStatus::Rejected,
            'notes' => $this->appendNote($refund, $note),
            'processed_by_type' => $actor->type,
            'processed_by_id' => $actor->id,
            'processed_by_name' => $actor->name,
            'processed_at' => now(),
        ]);
        $this->refunds->logAttempt($refund, ['gateway' => null, 'status' => RefundStatus::Rejected, 'amount' => $refund->amount, 'error' => $note, 'actor_type' => $actor->type, 'actor_id' => $actor->id]);

        return $refund;
    }

    private function create(int $tenantId, Order $order, RefundSource $source, RefundBreakdown $breakdown, string $reason, Actor $actor, bool $restock = false, ?ReturnRequest $return = null): Refund
    {
        $code = PaymentGatewayCode::tryFrom((string) $order->payment_gateway);
        $online = $order->isPaidOnline() && $code !== null && $this->gateways->supports($code);

        /** @var Refund $refund */
        $refund = $this->refunds->create([
            'reference' => $this->refunds->nextReference(),
            'tenant_id' => $tenantId,
            'order_uuid' => $order->uuid,
            'order_number' => $order->number,
            'return_request_id' => $return?->id,
            'source' => $source,
            'reason' => mb_substr($reason, 0, 500),
            'currency' => $breakdown->amount->currency,
            'items_amount' => $breakdown->itemsAmount->minor,
            'shipping_amount' => $breakdown->shippingAmount->minor,
            'return_fee' => $breakdown->returnFee->minor,
            'amount' => $breakdown->amount->minor,
            'method' => $online ? RefundMethod::OriginalPayment : RefundMethod::Manual,
            'gateway' => $order->payment_gateway,
            'payment_source' => $order->payment_source ?? PaymentSource::Tenant,
            'status' => RefundStatus::Pending,
            'restock' => $restock,
            'requested_by_type' => $actor->type,
            'requested_by_id' => $actor->id,
            'requested_at' => now(),
        ]);

        if ($online) {
            ProcessRefund::dispatch($refund->id, $actor)->afterCommit();
        }

        return $refund;
    }

    private function fail(Refund $refund, string $reason, Actor $actor, bool $log = true): Refund
    {
        $this->refunds->update($refund, ['status' => RefundStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 500)]);

        if ($log) {
            $this->refunds->logAttempt($refund, ['gateway' => $refund->gateway, 'status' => RefundStatus::Failed, 'amount' => $refund->amount, 'error' => mb_substr($reason, 0, 500), 'actor_type' => $actor->type, 'actor_id' => $actor->id]);
        }

        RefundFailed::dispatch($refund);

        return $refund;
    }

    private function finish(Refund $refund, Actor $actor, ?string $gatewayRefundId): Refund
    {
        $this->refunds->update($refund, [
            'status' => RefundStatus::Completed,
            'gateway_refund_id' => $gatewayRefundId ?? $refund->gateway_refund_id,
            'failure_reason' => null,
            'processed_by_type' => $actor->type,
            'processed_by_id' => $actor->id,
            'processed_by_name' => $actor->name,
            'processed_at' => now(),
        ]);

        $return = $refund->return_request_id !== null ? $this->returns->findForTenant($refund->return_request_id) : null;

        if ($return !== null && $return->status->canTransitionTo(ReturnStatus::Refunded)) {
            $from = $return->status;
            $this->returns->setStatus($return, ReturnStatus::Refunded, ['refunded_at' => now()]);
            $this->returns->addEvent($return, ['type' => ReturnEventType::Status, 'from_status' => $from, 'to_status' => ReturnStatus::Refunded, 'note' => $refund->reference, 'actor_type' => $actor->type, 'actor_id' => $actor->id, 'actor_name' => $actor->name]);
            ReturnStatusChanged::dispatch($return, $from, ReturnStatus::Refunded);
        }

        $this->tenants->run($refund->tenant_id, function () use ($refund, $actor): void {
            $order = $this->orders->findDetailed($refund->order_uuid);

            if ($order === null) {
                return;
            }

            if ($refund->restock && $order->fulfilment_branch_id !== null) {
                foreach ($order->items as $item) {
                    $this->stock->restockLine($order, $item, $item->quantity);
                }
            }

            $this->settleOrderStatus($order, $refund, $actor);
        });

        RefundCompleted::dispatch($refund);

        return $refund;
    }

    /**
     * Every unit came back → returned; everything refunded → refunded. RQA-VEN-009: once
     * the completed refunds cover the whole amount charged, the payment reads Refunded
     * (with refunded_at) whichever order status applies; a partial refund keeps Paid.
     */
    private function settleOrderStatus(Order $order, Refund $refund, Actor $actor): void
    {
        $refunded = $this->refunds->sumForOrder($refund->tenant_id, $order->uuid, [RefundStatus::Completed->value]);
        $fullyRefunded = $refunded > 0 && $refunded >= $order->grand_total;

        $returnedUnits = $this->returns->forOrder($refund->tenant_id, $order->uuid)
            ->filter(static fn (ReturnRequest $return): bool => in_array($return->status, [ReturnStatus::Refunded, ReturnStatus::Closed], true) && $return->refunded_at !== null)
            ->sum('quantity');

        if ($returnedUnits > 0 && $returnedUnits >= $order->items->sum('quantity') && $order->status->canTransitionTo(OrderStatus::Returned)) {
            $this->lifecycle->transition($order, OrderStatus::Returned, $actor->type, $actor->id, $refund->reference);
        } elseif ($fullyRefunded && $order->status->canTransitionTo(OrderStatus::Refunded)) {
            $this->lifecycle->transition($order, OrderStatus::Refunded, $actor->type, $actor->id, $refund->reference);
        }

        if ($fullyRefunded && ($order->payment_status !== PaymentStatus::Refunded || $order->refunded_at === null)) {
            $order->forceFill(['payment_status' => PaymentStatus::Refunded, 'refunded_at' => $order->refunded_at ?? now()])->save();
        }
    }

    private function appendNote(Refund $refund, ?string $note): ?string
    {
        $note = trim((string) $note);

        if ($note === '') {
            return $refund->notes;
        }

        return trim(($refund->notes !== null ? $refund->notes."\n" : '').$note);
    }
}
