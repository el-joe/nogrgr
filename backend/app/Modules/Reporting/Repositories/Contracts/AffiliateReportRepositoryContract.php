<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Identity\Models\Affiliate;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read-only affiliate funnel (central Affiliates tables: clicks → conversions → payouts).
 *
 * @extends RepositoryContract<Affiliate>
 */
interface AffiliateReportRepositoryContract extends RepositoryContract
{
    /**
     * @return array{clicks: int, conversions: int, approved: int, commission: int, sales: int, payouts: int}
     */
    public function funnel(ReportFilterData $filters): array;

    /**
     * Per affiliate: clicks, conversions, approved, sales, commission, paid out.
     */
    public function byAffiliate(ReportFilterData $filters): QueryBuilder;
}
