<?php

namespace App\Modules\Engagement\Services\Reviews;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;

/**
 * Store review setting (tenant `store_settings`): new reviews are published at once
 * (default) or wait for the store's approval.
 */
class ReviewSettingsService
{
    public function __construct(private readonly StoreSettingRepositoryContract $settings) {}

    public function requiresApproval(): bool
    {
        return (bool) $this->settings->get(StoreSettingKey::ReviewsRequireApproval);
    }

    public function setRequiresApproval(bool $required): void
    {
        $this->settings->put(StoreSettingKey::ReviewsRequireApproval, $required);
    }
}
