<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\AddonPurchasePaid;
use App\Modules\Billing\Events\LanguagePurchasePaid;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;

/**
 * Buying a paid add-on (a language, AI translation): creates the purchase as
 * pending_payment plus its unpaid invoice. Paying the invoice (gateway, P5, or admin)
 * settles it through markPaid() and fires AddonPurchasePaid for the owning module.
 */
class AddonPurchaseService extends BaseService
{
    public function __construct(
        private readonly AddonPurchaseRepositoryContract $purchases,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Idempotent per item: an unpaid purchase of the same item is returned as is.
     *
     * @param  array<string, mixed>  $meta
     */
    public function purchase(int $tenantId, AddonType $type, string $itemCode, Money $amount, string $description, ?string $billToName = null, array $meta = []): AddonPurchase
    {
        if ($amount->currency !== Money::BASE_CURRENCY || $amount->isNegative()) {
            throw new BillingRuleException(__('billing::messages.invalid_amount'));
        }

        $open = $this->purchases->openFor($tenantId, $type, $itemCode);

        if ($open !== null) {
            return $open;
        }

        return $this->transaction(function () use ($tenantId, $type, $itemCode, $amount, $description, $billToName, $meta): AddonPurchase {
            /** @var AddonPurchase $purchase */
            $purchase = $this->purchases->create([
                'tenant_id' => $tenantId,
                'type' => $type,
                'item_code' => $itemCode,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'status' => AddonPurchaseStatus::PendingPayment,
                'meta' => $meta === [] ? null : $meta,
            ]);

            $this->invoices->issue(new InvoiceDraftData(
                tenantId: $tenantId,
                type: InvoiceType::forAddon($type),
                lines: [new InvoiceLineData($description, $amount->minor)],
                billable: $purchase,
                billToName: $billToName,
            ));

            return $purchase;
        }, (new AddonPurchase)->getConnectionName());
    }

    /**
     * Extra language (anything but ar/en) at its single admin-set USD price (decision #45).
     * Paying the invoice fires LanguagePurchasePaid (tenant id + language id).
     */
    public function purchaseLanguage(int $tenantId, int $languageId, string $languageCode, string $languageName, Money $price, ?string $billToName = null): AddonPurchase
    {
        if (in_array(strtolower($languageCode), ['ar', 'en'], true)) {
            throw new BillingRuleException(__('billing::messages.language_free'));
        }

        $purchase = $this->purchase(
            $tenantId,
            AddonType::Language,
            strtolower($languageCode),
            $price,
            __('billing::invoices.line.language', ['language' => $languageName]),
            $billToName,
            ['language_id' => $languageId],
        );

        if (($purchase->meta['language_id'] ?? null) !== $languageId) {
            $this->purchases->update($purchase, ['meta' => array_merge($purchase->meta ?? [], ['language_id' => $languageId])]);
        }

        return $purchase;
    }

    public function invoiceFor(AddonPurchase $purchase): ?Invoice
    {
        /** @var Invoice|null $invoice */
        $invoice = $purchase->invoices()->latest('id')->first();

        return $invoice;
    }

    public function markPaid(AddonPurchase $purchase, ?int $paymentIntentId = null): AddonPurchase
    {
        if ($purchase->status === AddonPurchaseStatus::Paid) {
            return $purchase;
        }

        if (! $purchase->status->isOpen()) {
            throw BillingRuleException::invalidTransition($purchase->status->label(), AddonPurchaseStatus::Paid->label());
        }

        $this->purchases->update($purchase, [
            'status' => AddonPurchaseStatus::Paid,
            'paid_at' => now(),
            'payment_intent_id' => $paymentIntentId ?? $purchase->payment_intent_id,
        ]);
        event(new AddonPurchasePaid($purchase));

        $languageId = $purchase->meta['language_id'] ?? null;

        if ($purchase->type === AddonType::Language && is_numeric($languageId)) {
            event(new LanguagePurchasePaid($purchase->tenant_id, (int) $languageId, $purchase->item_code, $purchase->id));
        }

        return $purchase;
    }

    public function cancel(AddonPurchase $purchase): AddonPurchase
    {
        if (! $purchase->status->isOpen()) {
            throw BillingRuleException::invalidTransition($purchase->status->label(), AddonPurchaseStatus::Cancelled->label());
        }

        $this->transaction(function () use ($purchase): void {
            $this->purchases->update($purchase, ['status' => AddonPurchaseStatus::Cancelled]);
            $invoice = $this->invoiceFor($purchase);

            if ($invoice !== null && $invoice->status->canTransitionTo(InvoiceStatus::Void)) {
                $this->invoices->void($invoice);
            }
        }, (new AddonPurchase)->getConnectionName());

        return $purchase;
    }
}
