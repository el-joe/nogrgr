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
use App\Modules\Commerce\Pricing\SettlementFigures;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use Carbon\CarbonInterface;

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

    /**
     * Idempotency key of an order event posting (`paid`, or the void status reached).
     * Shared by PostLedgerEntries and the ledger back-fill so neither posts twice.
     */
    public static function orderKey(int $tenantId, string $uuid, string $event = 'paid'): string
    {
        return 'order:'.$tenantId.':'.$uuid.':'.$event;
    }

    /**
     * What an order posting would change now: [payable delta, receivable delta] (minor
     * units; both 0 = the ledger already matches the formulas). Used by the back-fill dry run.
     *
     * @return array{payable: int, receivable: int}
     */
    public function orderDeltas(int $tenantId, Order $order): array
    {
        $figures = $this->finance->figures($order, $tenantId);

        return $this->deltas($tenantId, $order, $figures);
    }

    /**
     * Brings an order whose ledger drifted from the formulas (postings written by an earlier
     * release, e.g. a refund above the vendor share that only reversed the payable,
     * QA-P5-011) back onto the OrderSettlement figures. The key names the posted and the
     * target balances, so a re-run with nothing to correct posts nothing.
     */
    public function reconcile(int $tenantId, Order $order): ?LedgerTransaction
    {
        $state = $this->state($tenantId, $order, $this->finance->figures($order, $tenantId));

        if ($state['target_payable'] === $state['posted_payable'] && $state['target_receivable'] === $state['posted_receivable']) {
            return null;
        }

        $key = self::orderKey($tenantId, $order->uuid, 'adjust:'.implode(':', $state));

        return $this->order($tenantId, $order, $key, descriptionKey: 'order_adjustment');
    }

    public function order(int $tenantId, Order $order, string $key, LedgerSource $source = LedgerSource::Order, ?string $sourceReference = null, ?CarbonInterface $occurredAt = null, ?string $descriptionKey = null): ?LedgerTransaction
    {
        $figures = $this->finance->figures($order, $tenantId);
        ['payable' => $payableDelta, 'receivable' => $receivableDelta] = $this->deltas($tenantId, $order, $figures);

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
        $descriptionKey ??= match (true) {
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
            occurredAt: $occurredAt,
        ));
    }

    /**
     * @return array{payable: int, receivable: int}
     */
    private function deltas(int $tenantId, Order $order, SettlementFigures $figures): array
    {
        $state = $this->state($tenantId, $order, $figures);

        return ['payable' => $state['target_payable'] - $state['posted_payable'], 'receivable' => $state['target_receivable'] - $state['posted_receivable']];
    }

    /**
     * What the formulas say the order's payable / receivable is, and what order / refund
     * postings already accrued for it (minor units).
     *
     * @return array{target_payable: int, target_receivable: int, posted_payable: int, posted_receivable: int}
     */
    private function state(int $tenantId, Order $order, SettlementFigures $figures): array
    {
        $accrualSources = [LedgerSource::Order, LedgerSource::Refund];

        return [
            'target_payable' => $figures->collectedBy === CollectedBy::Platform ? $figures->owedToVendor->minor : 0,
            // A platform-collected order whose refunds exceed the vendor share leaves the store owing the shortfall.
            'target_receivable' => $figures->collectedBy === CollectedBy::Vendor ? $figures->platformShare->minor : max(0, -$figures->vendorShare->minor),
            'posted_payable' => -$this->entries->accountBalance($tenantId, LedgerAccount::VendorPayable, $order->uuid, $accrualSources),
            'posted_receivable' => $this->entries->accountBalance($tenantId, LedgerAccount::VendorReceivable, $order->uuid, $accrualSources),
        ];
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
