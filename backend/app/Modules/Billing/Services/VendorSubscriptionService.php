<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\VendorSubscriptionCheckoutData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Enums\VendorSubscriptionAction;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vendor › Finance › Subscription & balance (VEN-060…060d) for the current store:
 * plan + usage vs limits, balance figures (central ledger), renew / change plan / pay an
 * invoice through the existing hosted checkouts (SubscriptionRenewalService,
 * InvoiceCheckoutService) and the payment result. Prices come from the plan and the
 * invoice calculators only; every lookup is pinned to the store's tenant id.
 */
class VendorSubscriptionService extends BaseService
{
    public const RECENT_TRANSACTIONS = 5;

    public function __construct(
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly PlanRepositoryContract $plans,
        private readonly InvoiceRepositoryContract $invoices,
        private readonly LedgerRepositoryContract $ledger,
        private readonly SubscriptionRenewalService $renewals,
        private readonly InvoiceCheckoutService $invoiceCheckout,
        private readonly FinancePageService $finance,
        private readonly StoreProductRepositoryContract $products,
        private readonly VendorStaffRepositoryContract $staff,
        private readonly EffectivePlanLimits $limits,
    ) {}

    public function current(int $tenantId): ?Subscription
    {
        return $this->subscriptions->currentFor($tenantId)?->loadMissing(['plan', 'latestInvoice']);
    }

    /**
     * Plans a store can subscribe to (active, display order).
     *
     * @return Collection<int, Plan>
     */
    public function plans(): Collection
    {
        return $this->plans->ordered()->filter(static fn (Plan $plan): bool => $plan->status === PlanStatus::Active)->values();
    }

    /**
     * Usage vs the store's effective quotas (plan or admin override) for what the store
     * can count today (tenant DB). max null = unlimited.
     *
     * @return list<array{limit: PlanLimit, used: int, max: ?int, percent: int}>
     */
    public function usage(?Plan $plan, int $tenantId): array
    {
        $used = [
            PlanLimit::Products->value => $this->products->query()->count(),
            PlanLimit::Staff->value => $this->staff->query()->count(),
        ];
        $rows = [];

        foreach ($used as $limit => $count) {
            $case = PlanLimit::from($limit);
            $max = $this->limits->forPlan($case, $tenantId, $plan);
            $rows[] = [
                'limit' => $case,
                'used' => $count,
                'max' => $max,
                'percent' => $max === null || $max <= 0 ? 0 : (int) min(100, round($count * 100 / $max)),
            ];
        }

        return $rows;
    }

    /**
     * Balance cards: net (platform owes the store when positive), owed to the platform,
     * scheduled payouts.
     *
     * @return array{net: int, owed: int, open_payouts: int}
     */
    public function balance(int $tenantId): array
    {
        $summary = $this->finance->summary($tenantId);

        return [
            'net' => (int) $summary['net'],
            'owed' => (int) $summary['receivable'],
            'open_payouts' => (int) $summary['open_payouts'],
        ];
    }

    /**
     * @return Collection<int, LedgerTransaction>
     */
    public function recentTransactions(int $tenantId): Collection
    {
        /** @var Collection<int, LedgerTransaction> $rows */
        $rows = $this->ledger->listing([], $tenantId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_TRANSACTIONS)
            ->get();

        return $rows;
    }

    /**
     * Oldest unpaid invoice of the store (shown as "pay now").
     */
    public function unpaidInvoice(int $tenantId): ?Invoice
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->invoices->listing(['tenant_id' => $tenantId, 'status' => InvoiceStatus::Unpaid->value])
            ->orderBy('invoices.id')
            ->first();

        return $invoice;
    }

    /**
     * An invoice of this store (with its lines) — 404 for any other store's.
     */
    public function invoice(int $tenantId, int $invoiceId): Invoice
    {
        $exists = $this->invoices->listing(['tenant_id' => $tenantId])->where('invoices.id', $invoiceId)->exists();

        abort_unless($exists, 404);

        return $this->invoices->findWithItems($invoiceId);
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    public function checkout(int $tenantId, VendorSubscriptionCheckoutData $data, VendorStaff $payer, Closure $returnUrls): array
    {
        $locale = app()->getLocale();
        $meta = ['vendor_staff_id' => $payer->id];

        return match ($data->action) {
            VendorSubscriptionAction::Renew => $this->renewals->startRenewal(
                $this->current($tenantId) ?? throw new BillingRuleException(__('billing::vendor_subscription.errors.no_subscription')),
                $data->gateway,
                $payer->email,
                $locale,
                $meta,
                $returnUrls,
            ),
            VendorSubscriptionAction::Change => $this->renewals->startChange(
                $tenantId,
                $this->changeTarget($tenantId, $data),
                $data->term ?? BillingTerm::Monthly,
                $data->gateway,
                $payer->email,
                $locale,
                $meta,
                $returnUrls,
            ),
            VendorSubscriptionAction::Invoice => $this->payInvoice($tenantId, (int) $data->invoiceId, $data, $payer, $returnUrls),
        };
    }

    /**
     * A renewal / plan-change / invoice payment log of this store, or null.
     */
    public function paymentLog(int $tenantId, int $logId): ?PaymentLog
    {
        return $this->renewals->logForTenant($tenantId, $logId) ?? $this->invoiceCheckout->logFor($tenantId, $logId);
    }

    /**
     * Gateway return: verify (captures) — or record the cancel — and give the outcome.
     */
    public function settle(PaymentLog $log, bool $cancelled): PaymentLog
    {
        $service = $log->purpose === PaymentPurpose::Invoice ? $this->invoiceCheckout : $this->renewals;

        if ($cancelled) {
            return $service->cancel($log);
        }

        try {
            return $service->confirm($log);
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return $log;
        }
    }

    /**
     * What the payment bought: the plan / term (renewal or change) and the paid invoice.
     *
     * @return array{plan: ?Plan, term: ?BillingTerm, invoice: ?Invoice}
     */
    public function resultDetails(PaymentLog $log): array
    {
        $payload = $log->payload ?? [];
        $invoice = $log->invoice()->withoutGlobalScopes()->first();
        $plan = null;
        $term = null;

        if ($log->purpose === PaymentPurpose::Renewal) {
            /** @var Subscription|null $subscription */
            $subscription = isset($payload['subscription_id'])
                ? $this->subscriptions->query()->withoutGlobalScopes()->with('plan')->where('tenant_id', $log->tenant_id)->whereKey((int) $payload['subscription_id'])->first()
                : null;
            /** @var Plan|null $plan */
            $plan = isset($payload['plan_id']) ? $this->plans->find((int) $payload['plan_id']) : $subscription?->plan;
            $term = isset($payload['term']) ? BillingTerm::tryFrom((string) $payload['term']) : $subscription?->term;
            $invoice ??= $subscription?->latestInvoice()->withoutGlobalScopes()->first();
        }

        return ['plan' => $plan, 'term' => $term, 'invoice' => $invoice instanceof Invoice ? $invoice : null];
    }

    /**
     * @throws BillingRuleException
     */
    private function changeTarget(int $tenantId, VendorSubscriptionCheckoutData $data): Plan
    {
        /** @var Plan|null $plan */
        $plan = $this->plans->find((int) $data->planId);

        if ($plan === null || $plan->status !== PlanStatus::Active) {
            throw new BillingRuleException(__('billing::vendor_subscription.errors.plan_unavailable'));
        }

        $current = $this->current($tenantId);

        if ($current !== null && $current->plan_id === $plan->id && $current->term === $data->term && $this->renewals->renewable($current)) {
            throw new BillingRuleException(__('billing::vendor_subscription.errors.same_plan'));
        }

        return $plan;
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    private function payInvoice(int $tenantId, int $invoiceId, VendorSubscriptionCheckoutData $data, VendorStaff $payer, Closure $returnUrls): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->invoices->listing(['tenant_id' => $tenantId])->where('invoices.id', $invoiceId)->first();

        if ($invoice === null) {
            throw new BillingRuleException(__('billing::vendor_subscription.errors.invoice_unavailable'));
        }

        return $this->invoiceCheckout->start(
            $invoice,
            $data->gateway,
            __('billing::vendor_subscription.invoice_payment_description', ['number' => $invoice->number, 'app' => config('app.name')]),
            $returnUrls,
            $payer->email,
            app()->getLocale(),
            ['vendor_staff_id' => $payer->id],
        );
    }
}
