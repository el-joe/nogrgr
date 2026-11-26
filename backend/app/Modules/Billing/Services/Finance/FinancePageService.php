<?php

namespace App\Modules\Billing\Services\Finance;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Models\LedgerEntry;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Billing\Services\TenantLookupService;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Read side of the finance pages (ADM-050…053, VEN-062…064): KPI cards, the per-store
 * ledger query with balances / status, a store's summary and lookups.
 */
class FinancePageService
{
    /** An open receivable older than this (days, no settlement since) is overdue. */
    /** Fallback of Admin › Settings › Platform › Billing "settlement overdue after" (decision #69). */
    public const DEFAULT_OVERDUE_DAYS = 30;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerRepositoryContract $entries,
        private readonly TenantPayoutRepositoryContract $payouts,
        private readonly OrderSettlementRepositoryContract $settlements,
        private readonly PayoutService $payoutService,
        private readonly TenantLookupService $lookup,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    /**
     * @return array{owed_to_vendors: int, owed_to_platform: int, settled_recent: int, payouts_open: int, payouts_open_count: int}
     */
    public function ledgerStats(): array
    {
        $balances = $this->entries->balances();
        $payouts = $this->payouts->stats();

        return [
            'owed_to_vendors' => array_sum(array_map(static fn (array $row): int => max(0, $row['payable']), $balances)),
            'owed_to_platform' => array_sum(array_map(static fn (array $row): int => max(0, $row['receivable']), $balances)),
            'settled_recent' => $this->settlements->stats()['paid_recent'],
            'payouts_open' => $payouts['open_amount'],
            'payouts_open_count' => $payouts['scheduled'] + $payouts['processing'],
        ];
    }

    /**
     * Stores with their balances (ADM-050): payable, receivable, last settlement, first
     * open receivable. Filters: search, balance (owes_platform | platform_owes | clear),
     * country_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Tenant>
     */
    public function ledgerQuery(array $filters = []): Builder
    {
        $sum = static fn (string $account, string $expression) => LedgerEntry::withoutTenantScope()
            ->selectRaw('COALESCE('.$expression.', 0)')
            ->whereColumn('ledger_entries.tenant_id', 'tenants.id')
            ->where('account', $account);

        $payable = $sum(LedgerAccount::VendorPayable->value, 'SUM(credit) - SUM(debit)');
        $receivable = $sum(LedgerAccount::VendorReceivable->value, 'SUM(debit) - SUM(credit)');
        $lastSettlement = OrderSettlement::withoutTenantScope()->selectRaw('MAX(paid_at)')->whereColumn('order_settlements.tenant_id', 'tenants.id')->where('status', SettlementStatus::Paid->value);
        $firstOpen = LedgerEntry::withoutTenantScope()->selectRaw('MIN(created_at)')->whereColumn('ledger_entries.tenant_id', 'tenants.id')->where('account', LedgerAccount::VendorReceivable->value)->where('debit', '>', 0);

        $search = trim((string) ($filters['search'] ?? ''));
        $query = Tenant::query()
            ->select('tenants.*')
            ->selectSub($payable, 'ledger_payable')
            ->selectSub($receivable, 'ledger_receivable')
            ->selectSub($lastSettlement, 'last_settlement_at')
            ->selectSub($firstOpen, 'first_receivable_at')
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner->where('tenants.name', 'like', '%'.$search.'%')->orWhere('tenants.slug', 'like', '%'.$search.'%')))
            ->when(filled($filters['country_id'] ?? null), static fn (Builder $q) => $q->where('tenants.default_country_id', (int) $filters['country_id']));

        $payableSql = $this->sql($payable->toBase());
        $receivableSql = $this->sql($receivable->toBase());

        return match ((string) ($filters['balance'] ?? '')) {
            'owes_platform' => $query->whereRaw('('.$payableSql['sql'].') >= 0', $payableSql['bindings'])->whereRaw('('.$receivableSql['sql'].') > 0', $receivableSql['bindings']),
            'platform_owes' => $query->whereRaw('('.$payableSql['sql'].') > 0', $payableSql['bindings']),
            'clear' => $query->whereRaw('('.$payableSql['sql'].') = 0', $payableSql['bindings'])->whereRaw('('.$receivableSql['sql'].') = 0', $receivableSql['bindings']),
            default => $query,
        };
    }

    /**
     * Days before an open receivable turns "overdue" (Billing settings, DB only).
     */
    public static function overdueDays(): int
    {
        $days = (int) config('billing.settlement_overdue_days', self::DEFAULT_OVERDUE_DAYS);

        return $days > 0 ? $days : self::DEFAULT_OVERDUE_DAYS;
    }

    /**
     * clear | due | overdue (Figma ADM-050 status).
     */
    public static function status(int $receivable, ?string $firstOpenAt, ?string $lastSettlementAt): string
    {
        if ($receivable <= 0) {
            return 'clear';
        }

        $limit = CarbonImmutable::now()->subDays(self::overdueDays());
        $stale = $firstOpenAt !== null && CarbonImmutable::parse($firstOpenAt)->lessThan($limit);
        $noRecentSettlement = $lastSettlementAt === null || CarbonImmutable::parse($lastSettlementAt)->lessThan($limit);

        return $stale && $noRecentSettlement ? 'overdue' : 'due';
    }

    public function tenant(int $tenantId): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->tenants->find($tenantId);

        abort_if($tenant === null, 404);

        return $tenant;
    }

    /**
     * @return array{payable: int, receivable: int, net: int, owed: int, available: int, open_payouts: int, payee: array<string, mixed>|null, last_settlement: string|null, open_orders: Collection<string, array{uuid: string, number: string|null, owed: int, since: string}>}
     */
    public function summary(int $tenantId): array
    {
        $balance = $this->ledger->balanceOf($tenantId);
        $open = $this->entries->openReceivables($tenantId);

        return $balance + [
            'owed' => (int) $open->sum('owed'),
            'available' => $this->payoutService->available($tenantId),
            'open_payouts' => $this->payouts->openTotal($tenantId),
            'payee' => $this->payoutService->payee($tenantId),
            'last_settlement' => $this->settlements->lastPaidAt([$tenantId])[$tenantId] ?? null,
            'open_orders' => $open,
        ];
    }

    /**
     * What the store owes the platform (USD minor units, ledger receivable) and how many
     * orders are still open: the vendor dashboard "pay the platform" alert (VEN-009a).
     *
     * @return array{owed: int, orders: int}
     */
    public function owedToPlatform(int $tenantId): array
    {
        return [
            'owed' => max(0, $this->ledger->balanceOf($tenantId)['receivable']),
            'orders' => $this->entries->openReceivables($tenantId)->count(),
        ];
    }

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function storeSearch(?string $term, int $page = 1): array
    {
        return $this->lookup->search($term, $page);
    }

    public function transaction(int $id, ?int $tenantId = null): LedgerTransaction
    {
        return $this->entries->findDetailed($id, $tenantId) ?? abort(404);
    }

    public function payout(int $id, ?int $tenantId = null): TenantPayout
    {
        return $this->payouts->findForTenant($id, $tenantId) ?? abort(404);
    }

    /**
     * @return EloquentCollection<int, OrderSettlement>
     */
    public function settlement(string $reference, ?int $tenantId = null): EloquentCollection
    {
        $rows = $this->settlements->byReference($reference, $tenantId);

        abort_if($rows->isEmpty(), 404);

        return $rows;
    }

    /**
     * @return array{sql: string, bindings: list<mixed>}
     */
    private function sql(QueryBuilder $query): array
    {
        return ['sql' => $query->toSql(), 'bindings' => array_values($query->getBindings())];
    }
}
