<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\PaymentStatusChangeData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Events\PaymentRefunded;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;

/**
 * Gateway payment log: recording attempts and admin status changes.
 *   captured → the linked invoice is marked paid
 *   refunded → the linked invoice is marked refunded, PaymentRefunded +
 *              AffiliateConversionReversalRequested are fired (affiliate reversal)
 */
class PaymentLogService extends BaseService
{
    public function __construct(
        private readonly PaymentLogRepositoryContract $logs,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        ?int $tenantId,
        PaymentGateway $gateway,
        PaymentPurpose $purpose,
        Money $amount,
        ?Invoice $invoice = null,
        ?string $gatewayReference = null,
        ?array $payload = null,
    ): PaymentLog {
        /** @var PaymentLog $log */
        $log = $this->logs->create([
            'tenant_id' => $tenantId,
            'invoice_id' => $invoice?->id,
            'gateway' => $gateway,
            'purpose' => $purpose,
            'status' => PaymentLogStatus::Pending,
            'amount_minor' => $amount->minor,
            'currency' => $amount->currency,
            'gateway_reference' => $gatewayReference,
            'payload' => $payload,
        ]);

        return $log;
    }

    /**
     * A payment captured before its store existed (central registration): attach it to
     * the store and the subscription invoice issued at provisioning, and settle that
     * invoice with the gateway reference. Safe to call again.
     */
    public function settleWithInvoice(PaymentLog $log, int $tenantId, Invoice $invoice): PaymentLog
    {
        return $this->transaction(function () use ($log, $tenantId, $invoice): PaymentLog {
            $this->logs->update($log, ['tenant_id' => $tenantId, 'invoice_id' => $invoice->id]);

            if ($log->status === PaymentLogStatus::Captured && $invoice->status === InvoiceStatus::Unpaid) {
                $this->invoices->markPaid($invoice, $log->gateway->value, $log->gateway_reference ?? $log->reference());
            }

            return $log;
        }, (new PaymentLog)->getConnectionName());
    }

    public function changeStatus(PaymentLog $log, PaymentStatusChangeData $data): PaymentLog
    {
        if (! $log->status->canTransitionTo($data->status)) {
            throw BillingRuleException::invalidTransition($log->status->label(), $data->status->label());
        }

        return $this->transaction(function () use ($log, $data): PaymentLog {
            $attributes = ['status' => $data->status];

            if ($data->note !== null && $data->note !== '') {
                $attributes['note'] = trim(($log->note ? $log->note."\n" : '').$data->note);
            }

            match ($data->status) {
                PaymentLogStatus::Captured => $attributes['paid_at'] = now(),
                PaymentLogStatus::Refunded => $attributes['refunded_at'] = now(),
                default => null,
            };

            $this->logs->update($log, $attributes);
            $invoice = $log->invoice;

            if ($data->status === PaymentLogStatus::Captured) {
                if ($invoice !== null && $invoice->status === InvoiceStatus::Unpaid) {
                    $this->invoices->markPaid($invoice, $log->gateway->value, $log->gateway_reference ?? $log->reference());
                }

                $this->dispatchAfterCommit(new PaymentCaptured($log));
            }

            if ($data->status === PaymentLogStatus::Failed) {
                $this->dispatchAfterCommit(new PaymentFailed($log));
            }

            if ($data->status === PaymentLogStatus::Refunded) {
                if ($invoice !== null && $invoice->status === InvoiceStatus::Paid) {
                    $this->invoices->markRefunded($invoice);
                }

                $this->dispatchAfterCommit(new PaymentRefunded($log));
                $this->dispatchAfterCommit(new AffiliateConversionReversalRequested(
                    paymentLogId: $log->id,
                    tenantId: $log->tenant_id,
                    refundedAmountMinor: $log->amount_minor,
                    currency: $log->currency,
                    reason: $data->note,
                ));
            }

            return $log;
        }, (new PaymentLog)->getConnectionName());
    }
}
