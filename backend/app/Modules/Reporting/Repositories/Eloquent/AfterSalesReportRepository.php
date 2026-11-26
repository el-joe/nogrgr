<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Repositories\Contracts\AfterSalesReportRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<ReturnRequest>
 */
class AfterSalesReportRepository extends BaseRepository implements AfterSalesReportRepositoryContract
{
    protected function model(): string
    {
        return ReturnRequest::class;
    }

    public function query(): Builder
    {
        return ReturnRequest::withoutTenantScope();
    }

    public function returnTotals(ReportFilterData $filters): array
    {
        $row = $this->returns($filters)
            ->selectRaw('COUNT(*) AS returns, COALESCE(SUM(CASE WHEN return_requests.seller_fault = 1 THEN 1 ELSE 0 END), 0) AS seller_fault')
            ->first();

        return ['returns' => (int) ($row->returns ?? 0), 'seller_fault' => (int) ($row->seller_fault ?? 0)];
    }

    public function returnsByReason(ReportFilterData $filters): array
    {
        return $this->returns($filters)
            ->selectRaw('return_requests.reason_code AS reason, COUNT(*) AS total')
            ->groupBy('return_requests.reason_code')
            ->pluck('total', 'reason')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    public function reasonNames(): array
    {
        return ReturnReason::query()->get(['code', 'name'])
            ->mapWithKeys(static fn (ReturnReason $reason): array => [(string) $reason->code => (string) $reason->name])
            ->all();
    }

    public function refundTotals(ReportFilterData $filters): array
    {
        $row = $this->refunds($filters)->selectRaw('COUNT(*) AS refunds, COALESCE(SUM(refunds.amount), 0) AS amount')->first();

        return ['refunds' => (int) ($row->refunds ?? 0), 'amount' => (int) ($row->amount ?? 0)];
    }

    public function refundsDaily(ReportFilterData $filters): array
    {
        return $this->refunds($filters)
            ->selectRaw('DATE(refunds.processed_at) AS day, SUM(refunds.amount) AS amount')
            ->groupByRaw('DATE(refunds.processed_at)')
            ->pluck('amount', 'day')
            ->mapWithKeys(static fn ($amount, $day): array => [(string) $day => (int) $amount])
            ->all();
    }

    public function awaitingInspection(): int
    {
        return $this->query()->where('status', ReturnStatus::ItemReceived->value)->count();
    }

    private function returns(ReportFilterData $filters): QueryBuilder
    {
        return DB::table('return_requests')
            ->whereBetween('return_requests.created_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('return_requests.tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (QueryBuilder $query) => $query
                ->join('order_facts', static fn (JoinClause $join) => $join->on('order_facts.tenant_id', '=', 'return_requests.tenant_id')->on('order_facts.order_uuid', '=', 'return_requests.order_uuid'))
                ->where('order_facts.country_id', $filters->countryId));
    }

    private function refunds(ReportFilterData $filters): QueryBuilder
    {
        return DB::table('refunds')
            ->where('refunds.status', 'completed')
            ->whereBetween('refunds.processed_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('refunds.tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (QueryBuilder $query) => $query
                ->join('order_facts', static fn (JoinClause $join) => $join->on('order_facts.tenant_id', '=', 'refunds.tenant_id')->on('order_facts.order_uuid', '=', 'refunds.order_uuid'))
                ->where('order_facts.country_id', $filters->countryId));
    }
}
