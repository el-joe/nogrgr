<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Query\Builder;

/**
 * AI usage log (central `ai_usage_logs`, Ai module) for Admin › Reports › AI usage.
 *
 * @extends RepositoryContract<AiUsageLog>
 */
interface AiUsageReportRepositoryContract extends RepositoryContract
{
    /**
     * @return array{calls: int, prompt: int, completion: int, stores: int}
     */
    public function totals(ReportFilterData $filters): array;

    /**
     * Total tokens per feature value.
     *
     * @return array<string, int>
     */
    public function tokensByFeature(ReportFilterData $filters): array;

    /**
     * One row per store × feature × provider × model.
     */
    public function grouped(ReportFilterData $filters): Builder;
}
