<?php

namespace App\Modules\Engagement\Repositories\Eloquent;

use App\Modules\Engagement\Models\SupportRequest;
use App\Modules\Engagement\Repositories\Contracts\SupportRequestRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<SupportRequest>
 */
class SupportRequestRepository extends BaseRepository implements SupportRequestRepositoryContract
{
    protected function model(): string
    {
        return SupportRequest::class;
    }
}
