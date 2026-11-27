<?php

namespace App\Modules\Ai\Services\Quota;

use App\Modules\Ai\DTOs\AiQuotaStatus;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Services\EffectivePlanLimits;

/**
 * Monthly AI quotas per plan (Admin › Plans limits `ai_calls`, `image_searches`,
 * `ai_logos`, `ai_ads`; per-store admin overrides win, EffectivePlanLimits; null =
 * unlimited, no open subscription = unlimited like every other plan quota). Usage = rows of the central AI usage log of the metered features this
 * calendar month (one row per tool call, AiUsageRecorder::recordCall).
 */
class AiQuotaService
{
    public function __construct(
        private readonly AiUsageLogRepositoryContract $logs,
        private readonly EffectivePlanLimits $limits,
    ) {}

    public function status(PlanLimit $limit, int $tenantId): AiQuotaStatus
    {
        $since = now()->startOfMonth();

        return new AiQuotaStatus(
            limit: $limit,
            used: $this->logs->countCalls($tenantId, AiFeature::meteredBy($limit), $since),
            max: $this->limits->for($limit, $tenantId),
            resetsAt: $since->copy()->addMonth(),
        );
    }

    /**
     * @throws AiQuotaExceededException
     */
    public function ensureAvailable(AiFeature $feature, int $tenantId): void
    {
        $limit = $feature->planLimit();

        if ($limit === null) {
            return;
        }

        $status = $this->status($limit, $tenantId);

        if ($status->reached()) {
            throw new AiQuotaExceededException($status);
        }
    }
}
