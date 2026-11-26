<?php

namespace App\Modules\Ai\Repositories\Contracts;

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AiUsageLog;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Central DB.
 *
 * @extends RepositoryContract<AiUsageLog>
 */
interface AiUsageLogRepositoryContract extends RepositoryContract
{
    /**
     * Per-product "Translate with AI" calls of one store (VEN-012b) in the VEN-074
     * run-history shape (kind = product, subject = "store_product:<id>").
     */
    public function productTranslationRows(int $tenantId): QueryBuilder;

    /**
     * Per-product translation calls of one store: count, calls this month and tokens.
     *
     * @return array{calls: int, this_month: int, tokens: int}
     */
    public function productTranslationStats(int $tenantId): array;

    /**
     * Calls of the given features a store made since $since (monthly plan quotas).
     *
     * @param  list<AiFeature>  $features
     */
    public function countCalls(int $tenantId, array $features, Carbon $since): int;
}
