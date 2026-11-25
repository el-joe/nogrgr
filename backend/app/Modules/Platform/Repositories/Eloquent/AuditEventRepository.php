<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<AuditEvent>
 */
class AuditEventRepository extends BaseRepository implements AuditEventRepositoryContract
{
    protected function model(): string
    {
        return AuditEvent::class;
    }
}
