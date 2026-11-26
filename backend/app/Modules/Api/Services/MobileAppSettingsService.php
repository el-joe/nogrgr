<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\Enums\AppUpdateState;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use Throwable;

/**
 * The store's mobile app settings (tenant DB, `store_settings.mobile_app`, decision #69:
 * editable in the panels, never .env) and the update decision for an app version.
 */
class MobileAppSettingsService
{
    public function __construct(private readonly StoreSettingRepositoryContract $settings) {}

    public function get(): MobileAppSettingsData
    {
        try {
            return MobileAppSettingsData::fromSetting($this->settings->get(StoreSettingKey::MobileApp));
        } catch (Throwable) {
            return new MobileAppSettingsData;
        }
    }

    public function save(MobileAppSettingsData $data): void
    {
        $this->settings->put(StoreSettingKey::MobileApp, $data->toSetting());
    }

    /**
     * Below the minimum version → required; below the latest → optional. Unknown
     * platform / version → none (the app keeps running).
     */
    public function updateState(?string $platform, ?string $version, ?MobileAppSettingsData $settings = null): AppUpdateState
    {
        if (! in_array($platform, ['ios', 'android'], true) || $version === null || preg_match('/^\d+(\.\d+){0,3}/', $version, $match) !== 1) {
            return AppUpdateState::None;
        }

        $release = ($settings ?? $this->get())->release($platform);
        $current = $match[0];

        if ($release->minVersion !== null && version_compare($current, $release->minVersion, '<')) {
            return AppUpdateState::Required;
        }

        if ($release->latestVersion !== null && version_compare($current, $release->latestVersion, '<')) {
            return AppUpdateState::Optional;
        }

        return AppUpdateState::None;
    }
}
