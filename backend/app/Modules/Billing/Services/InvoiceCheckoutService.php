<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Support\Collection;

/**
 * A vendor pays one of its unpaid invoices (request payment, language purchase…) through
 * a hosted checkout of the "vendor payments" gateway config. The payment log is linked to
 * the invoice, so the capture (return URL or webhook, replay-safe in GatewayPaymentService)
 * marks the invoice paid and InvoicePaid settles whatever it billed.
 */
class InvoiceCheckoutService extends BaseService
{
    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly PaymentDriverRegistry $drivers,
        private readonly PaymentLogRepositoryContract $logs,
    ) {}

    /**
     * Gateways a vendor can pay with: active, vendor-payments config enabled, with a driver.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function gateways(): Collection
    {
        return $this->drivers->checkoutGateways(GatewayPurpose::VendorPayments);
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @param  array<string, int|string|null>  $meta
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    public function start(Invoice $invoice, PaymentGatewayCode $code, string $description, Closure $returnUrls, ?string $email = null, ?string $locale = null, array $meta = []): array
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BillingRuleException(__('billing::messages.invoice_not_payable'));
        }

        return $this->payments->start(new StartPaymentData(
            gateway: $code,
            purpose: PaymentPurpose::Invoice,
            amount: $invoice->total(),
            description: $description,
            customerEmail: $email,
            locale: $locale,
            tenantId: $invoice->tenant_id,
            meta: [...$meta, 'invoice_id' => $invoice->id],
            invoice: $invoice,
        ), $returnUrls);
    }

    /**
     * An invoice-payment log of the store (and only that store's).
     */
    public function logFor(int $tenantId, int $logId): ?PaymentLog
    {
        /** @var PaymentLog|null $log */
        $log = $this->logs->query()->withoutGlobalScopes()->whereKey($logId)->first();

        return $log !== null && $log->tenant_id === $tenantId && $log->purpose === PaymentPurpose::Invoice ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirm(PaymentLog $log): PaymentLog
    {
        return $this->payments->confirm($log);
    }

    public function cancel(PaymentLog $log): PaymentLog
    {
        return $this->payments->cancel($log);
    }
}
