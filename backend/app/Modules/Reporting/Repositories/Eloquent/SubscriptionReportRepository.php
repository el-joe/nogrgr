<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Repositories\Contracts\SubscriptionReportRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Subscription>
 */
class SubscriptionReportRepository extends BaseRepository implements SubscriptionReportRepositoryContract
{
    protected function model(): string
    {
        return Subscription::class;
    }

    public function lifecycles(ReportFilterData $filters): Collection
    {
        return DB::table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.created_at', '<=', $filters->end())
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('subscriptions.tenant_id', $filters->tenantId))
            ->get([
                'subscriptions.id', 'subscriptions.tenant_id', 'subscriptions.plan_id', 'plans.name AS plan_name', 'subscriptions.term', 'subscriptions.status',
                'subscriptions.amount_minor', 'subscriptions.trial_ends_at', 'subscriptions.created_at', 'subscriptions.cancelled_at', 'subscriptions.expired_at',
            ])
            ->map(static function (object $row): object {
                $row->plan_name = self::translated($row->plan_name);

                return $row;
            });
    }

    /**
     * Plan names are translatable JSON (`{"en": …, "ar": …}`): the current locale, else English.
     */
    private static function translated(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $value;
        }

        $name = $decoded[app()->getLocale()] ?? $decoded['en'] ?? reset($decoded);

        return is_string($name) ? $name : null;
    }

    public function paidInvoices(ReportFilterData $filters): array
    {
        $row = DB::table('invoices')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId))
            ->selectRaw('COUNT(*) AS invoices, COALESCE(SUM(total_minor), 0) AS amount')
            ->first();

        return ['invoices' => (int) ($row->invoices ?? 0), 'amount' => (int) ($row->amount ?? 0)];
    }
}
