<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Identity\Models\Affiliate;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Repositories\Contracts\AffiliateReportRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Conversions count unless rejected; commission excludes reversed / rejected ones;
 * "approved" = approved or paid. The store filter narrows clicks and conversions.
 *
 * @extends BaseRepository<Affiliate>
 */
class AffiliateReportRepository extends BaseRepository implements AffiliateReportRepositoryContract
{
    private const NOT_COUNTED = ['rejected'];

    private const NO_COMMISSION = ['rejected', 'reversed'];

    private const APPROVED = ['approved', 'paid'];

    protected function model(): string
    {
        return Affiliate::class;
    }

    public function funnel(ReportFilterData $filters): array
    {
        $conversions = $this->conversions($filters)
            ->selectRaw('COUNT(*) AS conversions,'
                .' COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) AS approved,'
                .' COALESCE(SUM(CASE WHEN status NOT IN (?, ?) THEN commission_amount ELSE 0 END), 0) AS commission,'
                .' COALESCE(SUM(CASE WHEN status NOT IN (?, ?) THEN sale_amount ELSE 0 END), 0) AS sales', [...self::APPROVED, ...self::NO_COMMISSION, ...self::NO_COMMISSION])
            ->first();

        return [
            'clicks' => $this->clicks($filters)->count(),
            'conversions' => (int) ($conversions->conversions ?? 0),
            'approved' => (int) ($conversions->approved ?? 0),
            'commission' => (int) ($conversions->commission ?? 0),
            'sales' => (int) ($conversions->sales ?? 0),
            'payouts' => (int) $this->payouts($filters)->sum('amount'),
        ];
    }

    public function byAffiliate(ReportFilterData $filters): QueryBuilder
    {
        $clicks = $this->clicks($filters)->selectRaw('affiliate_id, COUNT(*) AS clicks')->groupBy('affiliate_id');
        $conversions = $this->conversions($filters)
            ->selectRaw('affiliate_id, COUNT(*) AS conversions,'
                .' SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS approved,'
                .' SUM(CASE WHEN status NOT IN (?, ?) THEN commission_amount ELSE 0 END) AS commission,'
                .' SUM(CASE WHEN status NOT IN (?, ?) THEN sale_amount ELSE 0 END) AS sales', [...self::APPROVED, ...self::NO_COMMISSION, ...self::NO_COMMISSION])
            ->groupBy('affiliate_id');
        $payouts = $this->payouts($filters)->selectRaw('affiliate_id, SUM(amount) AS paid')->groupBy('affiliate_id');

        return DB::table('affiliates')
            ->leftJoinSub($clicks, 'cl', 'cl.affiliate_id', '=', 'affiliates.id')
            ->leftJoinSub($conversions, 'cv', 'cv.affiliate_id', '=', 'affiliates.id')
            ->leftJoinSub($payouts, 'po', 'po.affiliate_id', '=', 'affiliates.id')
            ->whereRaw('(cl.clicks IS NOT NULL OR cv.conversions IS NOT NULL OR po.paid IS NOT NULL)')
            ->selectRaw('affiliates.id, affiliates.name AS affiliate_name, affiliates.email AS affiliate_email, COALESCE(cl.clicks, 0) AS clicks,'
                .' COALESCE(cv.conversions, 0) AS conversions, COALESCE(cv.approved, 0) AS approved, COALESCE(cv.sales, 0) AS sales,'
                .' COALESCE(cv.commission, 0) AS commission, COALESCE(po.paid, 0) AS paid');
    }

    private function clicks(ReportFilterData $filters): QueryBuilder
    {
        return DB::table('affiliate_clicks')
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId));
    }

    private function conversions(ReportFilterData $filters): QueryBuilder
    {
        return DB::table('affiliate_conversions')
            ->whereNotIn('status', self::NOT_COUNTED)
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId));
    }

    private function payouts(ReportFilterData $filters): QueryBuilder
    {
        return DB::table('affiliate_payouts')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$filters->start(), $filters->end()]);
    }
}
