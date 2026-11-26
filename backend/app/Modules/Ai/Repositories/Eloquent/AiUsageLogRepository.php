<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<AiUsageLog>
 */
class AiUsageLogRepository extends BaseRepository implements AiUsageLogRepositoryContract
{
    protected function model(): string
    {
        return AiUsageLog::class;
    }
}
