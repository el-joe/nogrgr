<?php

namespace App\Modules\Storefront\Services\Tracking;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Storefront\DTOs\Appearance\TrackingPixelsData;
use Throwable;

/**
 * Settings › Tracking pixels (VEN-083, tenant `store_settings`): Meta, TikTok, Snap and
 * GA4 ids with on / off. The storefront gets the enabled ones as a JSON island
 * (#sf-tracking) that the bundled loader reads after the shopper's consent (decision #91).
 */
class TrackingPixelService
{
    public const CONSENT_COOKIE = 'sf_tracking_consent';

    private ?TrackingPixelsData $cached = null;

    public function __construct(private readonly StoreSettingRepositoryContract $settings) {}

    public function get(): TrackingPixelsData
    {
        return TrackingPixelsData::fromSetting($this->settings->get(StoreSettingKey::TrackingPixels));
    }

    public function save(TrackingPixelsData $data): void
    {
        $this->settings->put(StoreSettingKey::TrackingPixels, $data->toSetting());
        $this->cached = null;
    }

    /**
     * Island payload for the storefront, or null when no pixel is on.
     *
     * @return array{pixels: array<string, string>, cookie: string}|null
     */
    public function storefrontConfig(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        try {
            $this->cached ??= $this->get();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $active = $this->cached->active();

        return $active === [] ? null : ['pixels' => $active, 'cookie' => self::CONSENT_COOKIE];
    }
}
