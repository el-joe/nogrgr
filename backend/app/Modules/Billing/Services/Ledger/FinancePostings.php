<?php

namespace App\Modules\Billing\Services\Ledger;

use App\Modules\Billing\DTOs\Ledger\LedgerLine;
use App\Modules\Billing\DTOs\Ledger\LedgerPostingData;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Commerce\Enums\CollectedBy;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;

/**
 * Turns finance events into ledger postings (decision #72).
 *
 * Orders post the DIFFERENCE between what the formulas say now and what earlier order /
 * refund postings of that order already accrued, so the ledger always equals
 * Commerce\Pricing\OrderSettlement (ORDER_FORMULAS.md §3):
 *   collected by the platform → vendor_payable = vendor share (vendor_profit − refunded,
 *                              ≥ 0; 0 when void), counter platform_cash; a negative vendor
 *                              share (refunds above it) is vendor_receivable instead;
 *   collected by the store    → vendor_receivable = platform share (platform_profit;
 *                              0 when void), counter platform_revenue.
 * Settlements credit vendor_receivable (cash in, or a payout offset that debits
 * vendor_payable); payouts debit vendor_payable; a paid invoice is a platform-only
 * cash / revenue pair (net 0 for the store, listed in transactions).
 */
class FinancePostings
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerRepositoryContract $entries,
        private readonly OrderFinanceService $finance,
    ) {}

    public function order(int $tenantId, Order $order, string $key, LedgerSource $source = LedgerSource::Order, ?string $sourceReference = null): ?LedgerTransaction
    {
        $figures = $this->finance->figures($order, $tenantId);
        $accrualSources = [LedgerSource::Order, LedgerSource::Refund];

        $targetPayable = $figures->collectedBy === CollectedBy::Platform ? $figures->owedToVendor->minor : 0;
        // A platform-collected order whose refunds exceed the vendor share leaves the store owing the shortfall.
        $targetReceivable = $figures->collectedBy === CollectedBy::Vendor ? $figures->platformShare->minor : max(0, -$figures->vendorShare->minor);
        $postedPayable = -$this->entries->accountBalance($tenantId, LedgerAccount::VendorPayable, $order->uuid, $accrualSources);
        $postedReceivable = $this->entries->accountBalance($tenantId, LedgerAccount::VendorReceivable, $order->uuid, $accrualSources);

        $payableDelta = $targetPayable - $postedPayable;
        $receivableDelta = $targetReceivable - $postedReceivable;

        if ($payableDelta === 0 && $receivableDelta === 0) {
            return null;
        }

        $lines = [
            LedgerLine::change(LedgerAccount::VendorPayable, $payableDelta, $order->uuid, $order->number),
            LedgerLine::change(LedgerAccount::PlatformCash, $payableDelta, $order->uuid, $order->number),
            LedgerLine::change(LedgerAccount::VendorReceivable, $receivableDelta, $order->uuid, $order->number),
            LedgerLine::change(LedgerAccount::PlatformRevenue, $receivableDelta, $order->uuid, $order->number),
        ];
        $reversal = $payableDelta < 0 || $receivableDelta < 0;
        $descriptionKey = match (true) {
            $source === LedgerSource::Refund => 'refund',
            $reversal => 'order_void',
            $figures->collectedBy === CollectedBy::Platform => 'order_vendor_share',
            default => 'order_platform_share',
        };

        return $this->ledger->post(new LedgerPostingData(
            tenantId: $tenantId,
            source: $source,
            idempotencyKey: $key,
            description: $this->describe($descriptionKey, ['number' => $order->number, 'reference' => (string) $sourceReference, 'percent' => $figures->vendorPercent, 'total' => $order->money('grand_total')->format('en')]),
            lines: $lines,
            sourceReference: $sourceReference ?? '#'.$order->number,
            orderUuid: $order->uuid,
            direction: $reversal ? LedgerDirection::Reversal : null,
            meta: ['key' => $descriptionKey, 'params' => ['number' => $order->number, 'reference' => (string) $sourceReference, 'percent' => $figures->vendorPercent, 'total' => $order->grand_total], 'collected_by' => $figures->collectedBy->value],
        ));
    }

    public function settlement(OrderSettlement $settlement): ?LedgerTransaction
    {
        $offset = $settlement->method === SettlementMethod::PayoutOffset->value;
        $amount = $settlement->amount;
        $lines = [
            LedgerLine::credit(LedgerAccount::VendorReceivable, $amount, $settlement->order_uuid, $settlement->order_number),
            $offset
                ? LedgerLine::debit(LedgerAccount::VendorPayable, $amount)
                : LedgerLine::debit(LedgerAccount::PlatformCash, $amount, $settlement->order_uuid, $settlement->order_number),
        ];
        $params = ['number' => $settlement->order_number, 'reference' => (string) $settlement->reference];

        return $this->ledger->post(new LedgerPostingData(
            tenantId: $settlement->tenant_id,
            source: LedgerSource::Settlement,
            idempotencyKey: 'settlement:'.$settlement->id,
            description: $this->describe($offset ? 'settlement_offset' : 'settlement', $params),
            lines: $lines,
            sourceReference: $settlement->reference,
            orderUuid: $settlement->order_uuid,
            amount: $amount,
            meta: ['key' => $offset ? 'settlement_offset' : 'settlement', 'params' => $params, 'method' => $settlement->method ?? $settlement->gateway],
            occurredAt: $settlement->paid_at,
            createdById: $settlement->paid_by_id,
        ));
    }

    public function payoutPaid(TenantPayout $payout): ?LedgerTransaction
    {
        $params = ['reference' => (string) $payout->reference, 'method' => $payout->method->value];

        return $this->ledger->post(new LedgerPostingData(
            tenantId: $payout->tenant_id,
            source: LedgerSource::Payout,
            idempotencyKey: 'payout:'.$payout->id.':paid',
            description: $this->describe('payout', $params),
            lines: [
                LedgerLine::debit(LedgerAccount::VendorPayable, $payout->amount),
                LedgerLine::credit(LedgerAccount::PlatformCash, $payout->amount),
            ],
            sourceReference: $payout->reference,
            meta: ['key' => 'payout', 'params' => $params, 'payout_id' => $payout->id],
            occurredAt: $payout->paid_at,
            createdById: $payout->created_by_id,
        ));
    }

    /**
     * A paid payout that is cancelled is reversed (nothing to undo when it was never paid).
     */
    public function payoutCancelled(TenantPayout $payout): ?LedgerTransaction
    {
        if ($this->entries->findByKey('payout:'.$payout->id.':paid') === null) {
            return null;
        }

        $params = ['reference' => (string) $payout->reference];

        return $this->ledger->post(new LedgerPostingData(
            tenantId: $payout->tenant_id,
            source: LedgerSource::Payout,
            idempotencyKey: 'payout:'.$payout->id.':cancelled',
            description: $this->describe('payout_cancelled', $params),
            lines: [
                LedgerLine::credit(LedgerAccount::VendorPayable, $payout->amount),
                LedgerLine::debit(LedgerAccount::PlatformCash, $payout->amount),
            ],
            sourceReference: $payout->reference,
            direction: LedgerDirection::Reversal,
            meta: ['key' => 'payout_cancelled', 'params' => $params, 'payout_id' => $payout->id],
        ));
    }

    public function invoicePaid(Invoice $invoice): ?LedgerTransaction
    {
        if ($invoice->total_minor <= 0) {
            return null;
        }

        $subscription = in_array($invoice->type, [InvoiceType::Subscription, InvoiceType::Renewal], true);
        $params = ['number' => $invoice->number];

        return $this->ledger->post(new LedgerPostingData(
            tenantId: $invoice->tenant_id,
            source: $subscription ? LedgerSource::Subscription : LedgerSource::Addon,
            idempotencyKey: 'invoice:'.$invoice->id,
            description: $this->describe($subscription ? 'subscription' : 'addon', $params),
            lines: [
                LedgerLine::debit(LedgerAccount::PlatformCash, $invoice->total_minor),
                LedgerLine::credit(LedgerAccount::PlatformRevenue, $invoice->total_minor),
            ],
            sourceReference: $invoice->number,
            direction: LedgerDirection::Debit,
            amount: $invoice->total_minor,
            meta: ['key' => $subscription ? 'subscription' : 'addon', 'params' => $params, 'invoice_id' => $invoice->id],
            occurredAt: $invoice->paid_at,
        ));
    }

    /**
     * Stored English text (search / export); pages translate from meta.key.
     *
     * @param  array<string, string|int>  $params
     */
    private function describe(string $key, array $params): string
    {
        return (string) __('billing::ledger.descriptions.'.$key, $params, 'en');
    }
}
