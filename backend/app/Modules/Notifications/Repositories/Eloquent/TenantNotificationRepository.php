<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Models\TenantNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\TenantNotificationRepositoryContract;

class TenantNotificationRepository extends PanelNotificationRepository implements TenantNotificationRepositoryContract
{
    protected function model(): string
    {
        return TenantNotificationRecord::class;
    }
}
