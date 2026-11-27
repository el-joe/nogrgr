<?php

namespace App\Modules\Platform\Legacy\Services;

use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Platform\Legacy\DTOs\LegacyReconciliationData;
use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Support\LegacyReport;
use App\Modules\Platform\Legacy\Support\LegacySource;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Platform\Legacy\Support\LegacyWriter;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Finance reconciliation old ↔ new (`legacy:reconcile`, decision #113). Read only on
 * both sides. Per migrated store and for the platform, in USD cents:
 *
 * - orders: Σ grand total, platform profit, vendor profit — old store DB orders vs
 *   central `order_facts` (n/a when the old store DB is not on the server);
 * - refunds: Σ amount, old central `refunds` vs new `refunds`;
 * - subscription payments: Σ captured old `payment_logs` vs new `payment_logs`;
 * - settlements: Σ old `vendor_settlements.total` vs new `order_settlements.amount`
 *   vs ledger (`settlement` postings);
 * - payouts: Σ paid old `tenant_payouts` vs new paid `tenant_payouts` vs ledger.
 *
 * Old rows of stores that were not migrated are listed apart ("not migrated") so the
 * migrated scope must show 0 difference. Each money value is rounded per row exactly
 * like the migration (LegacyValues::minor) so the comparison is cent-exact.
 */
class LegacyReconciliationService
{
    public function __construct(private readonly LegacySource $source) {}

    public function run(LegacySourceConfig $config): LegacyReconciliationData
    {
        $this->source->configure($config);
        $old = $this->source->central();
        $new = DB::connection((new Tenant)->getConnectionName());
        $map = new LegacyWriter($new, 'legacy_central_ids', true, new LegacyReport, 'central');
        $result = new LegacyReconciliationData;

        $oldTenants = $old->table('tenants')->orderBy('created_at')->orderBy('id')->get(['id', 'data']);
        $known = [];

        foreach ($oldTenants as $row) {
            $legacyId = (string) $row->id;
            $tenantId = $map->id('tenant', $legacyId);
            $tenant = $tenantId !== null && $tenantId > 0 ? $new->table('tenants')->where('id', $tenantId)->first(['id', 'name']) : null;

            if ($tenant === null) {
                continue;
            }

            $known[$legacyId] = (int) $tenant->id;
            $scope = $tenant->name.' (#'.$tenant->id.')';
            $data = LegacyValues::json($row->data) ?? [];
            $this->orders($result, $scope, $legacyId, (int) $tenant->id, (string) ($data['tenancy_db_name'] ?? 'dokan_v2_'.$legacyId), $new);
            $this->central($result, $scope, $old, $new, [$legacyId], [(int) $tenant->id]);
        }

        $this->central($result, LegacyReconciliationData::PLATFORM, $old, $new, array_keys($known), array_values($known));
        $this->excluded($result, $old, array_keys($known));

        return $result;
    }

    private function orders(LegacyReconciliationData $result, string $scope, string $legacyId, int $tenantId, string $database, Connection $new): void
    {
        $facts = $new->table('order_facts')->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(grand_total), 0) AS grand, COALESCE(SUM(platform_profit), 0) AS platform, COALESCE(SUM(vendor_profit), 0) AS vendor')
            ->first();
        $store = $this->source->store($database);
        $old = null;

        if ($store !== null && $this->source->hasTable($store, 'orders')) {
            $old = ['n' => 0, 'grand' => 0, 'platform' => 0, 'vendor' => 0];

            foreach ($store->table('orders')->orderBy('id')->lazyById(500, 'id') as $order) {
                $platform = LegacyValues::minor($order->platform_profit);
                $vendor = LegacyValues::minor($order->vendor_profit);
                $old['n']++;
                $old['platform'] += $platform;
                $old['vendor'] += $vendor;
                $old['grand'] += $platform + $vendor;
            }
        }

        $result->add($scope, 'orders (count)', $old['n'] ?? null, (int) $facts->n, money: false);
        $result->add($scope, 'orders grand total', $old['grand'] ?? null, (int) $facts->grand);
        $result->add($scope, 'platform profit', $old['platform'] ?? null, (int) $facts->platform);
        $result->add($scope, 'vendor profit', $old['vendor'] ?? null, (int) $facts->vendor);

        if ($old === null) {
            $result->note("{$scope}: old store DB {$legacyId} not on the server — order figures n/a (store data not required for launch).");
        }

        $result->addTotal('orders grand total', $old['grand'] ?? null, (int) $facts->grand);
        $result->addTotal('platform profit', $old['platform'] ?? null, (int) $facts->platform);
        $result->addTotal('vendor profit', $old['vendor'] ?? null, (int) $facts->vendor);
    }

    /**
     * @param  list<string>  $legacyIds
     * @param  list<int>  $tenantIds
     */
    private function central(LegacyReconciliationData $result, string $scope, Connection $old, Connection $new, array $legacyIds, array $tenantIds): void
    {
        $ledger = fn (LedgerSource $source): int => (int) $new->table('ledger_transactions')->whereIn('tenant_id', $tenantIds)->where('source', $source->value)->sum('amount');

        $result->add($scope, 'refunds', $this->oldSum($old, 'refunds', 'amount', $legacyIds), (int) $new->table('refunds')->whereIn('tenant_id', $tenantIds)->sum('amount'));
        $result->add($scope, 'subscription payments', $this->oldSum($old, 'payment_logs', 'amount', $legacyIds, ['paid', 'captured', 'succeeded', 'completed']), (int) $new->table('payment_logs')->whereIn('tenant_id', $tenantIds)->where('status', PaymentLogStatus::Captured->value)->sum('amount_minor'));
        $result->add($scope, 'settlements', $this->oldSum($old, 'vendor_settlements', 'total', $legacyIds), (int) $new->table('order_settlements')->whereIn('tenant_id', $tenantIds)->sum('amount'));
        $result->add($scope, 'settlements (ledger)', $this->oldSum($old, 'vendor_settlements', 'total', $legacyIds, ['paid', 'settled', 'completed']), $ledger(LedgerSource::Settlement));
        $result->add($scope, 'payouts', $this->oldSum($old, 'tenant_payouts', 'amount', $legacyIds, ['paid']), (int) $new->table('tenant_payouts')->whereIn('tenant_id', $tenantIds)->where('status', PayoutStatus::Paid->value)->sum('amount'));
        $result->add($scope, 'payouts (ledger)', $this->oldSum($old, 'tenant_payouts', 'amount', $legacyIds, ['paid']), abs($ledger(LedgerSource::Payout)));
    }

    /**
     * Old money of stores that were not migrated (listed, not compared).
     *
     * @param  list<string>  $migrated
     */
    private function excluded(LegacyReconciliationData $result, Connection $old, array $migrated): void
    {
        foreach (['refunds' => 'amount', 'payment_logs' => 'amount', 'vendor_settlements' => 'total', 'tenant_payouts' => 'amount'] as $table => $column) {
            if (! $this->source->hasTable($old, $table)) {
                continue;
            }

            $rows = $old->table($table)->when($migrated !== [], static fn ($query) => $query->whereNotIn('tenant_id', $migrated))->get([$column]);

            if ($rows->isNotEmpty()) {
                $result->exclude($table, $rows->count(), (int) $rows->sum(static fn (object $row): int => LegacyValues::minor($row->{$column})));
            }
        }
    }

    /**
     * @param  list<string>  $legacyIds
     * @param  list<string>|null  $statuses
     */
    private function oldSum(Connection $old, string $table, string $column, array $legacyIds, ?array $statuses = null): ?int
    {
        if (! $this->source->hasTable($old, $table)) {
            return null;
        }

        if ($legacyIds === []) {
            return 0;
        }

        return (int) $old->table($table)
            ->whereIn('tenant_id', $legacyIds)
            ->when($statuses !== null, static fn ($query) => $query->whereIn('status', $statuses))
            ->get([$column])
            ->sum(static fn (object $row): int => LegacyValues::minor($row->{$column}));
    }
}
