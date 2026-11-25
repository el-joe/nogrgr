<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RepositoryContract<NotificationPreference>
 */
interface NotificationPreferenceRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, NotificationPreference>
     */
    public function forNotifiable(Model $notifiable): Collection;

    public function put(Model $notifiable, NotificationCategory $category, NotificationChannel $channel, bool $enabled): NotificationPreference;
}
