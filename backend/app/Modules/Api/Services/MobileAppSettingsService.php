<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\Enums\AppUpdateState;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
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

    public const SPLASH_DIRECTORY = 'mobile-app';

    /**
     * Panel save (VEN-037 / ADM-041g): the submitted settings keep the current splash logo
     * unless a new file replaces it or $removeLogo clears it (old file deleted from the
     * store's public disk). Runs inside the store's tenant context.
     */
    public function update(MobileAppSettingsData $data, ?UploadedFile $logo = null, bool $removeLogo = false): MobileAppSettingsData
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Mobile app settings are written inside tenant context.');
        }

        $previous = $this->get()->splashLogoPath;
        $path = $removeLogo ? null : $previous;

        if ($logo !== null) {
            $stored = $logo->storeAs(self::SPLASH_DIRECTORY, 'splash-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.strtolower($logo->guessExtension() ?: $logo->getClientOriginalExtension() ?: 'png'), 'public');
            $path = is_string($stored) ? $stored : $previous;
        }

        $saved = $data->withSplashLogo($path);
        $this->save($saved);

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $saved;
    }

    public function splashLogoUrl(?MobileAppSettingsData $settings = null): ?string
    {
        $path = ($settings ?? $this->get())->splashLogoPath;

        return $path !== null && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
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
