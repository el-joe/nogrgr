<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\Enums\MobileFeature;
use App\Modules\Notifications\Support\Push\FcmClient;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Http\UploadedFile;

/**
 * Page data + save of the mobile app settings panels (vendor VEN-037 / 037a, admin
 * ADM-041g) on top of MobileAppSettingsService. The admin works on another store: every
 * read / write runs in that store's tenant context.
 */
class MobileAppPanelService
{
    public function __construct(private readonly FcmClient $fcm) {}

    /**
     * @return array{settings: MobileAppSettingsData, locales: list<array{code: string, name: string, dir: string}>, logoUrl: string|null, features: array<string, bool>, pushReady: bool, platforms: array<string, array{label: string, live: string|null, state: string, url: string|null}>}
     */
    public function page(?Tenant $tenant = null): array
    {
        return $this->inStore($tenant, function (): array {
            $service = app(MobileAppSettingsService::class);
            $settings = $service->get();
            $platforms = [];

            foreach (['ios', 'android'] as $platform) {
                $release = $settings->release($platform);
                $platforms[$platform] = [
                    'label' => (string) __('api::panel.platforms.'.$platform),
                    'live' => $release->latestVersion,
                    'state' => $release->storeUrl !== null && $release->latestVersion !== null ? 'live' : 'not_set',
                    'url' => $release->storeUrl,
                ];
            }

            return [
                'settings' => $settings,
                'locales' => array_map(static fn (array $language): array => ['code' => $language['code'], 'name' => $language['name'], 'dir' => $language['dir']], app(MobileLocales::class)->all()),
                'logoUrl' => $service->splashLogoUrl($settings),
                'features' => $settings->featureFlags(),
                'pushReady' => $this->fcm->isConfigured(),
                'platforms' => $platforms,
            ];
        });
    }

    /**
     * Builds the settings with the store's languages and saves them (+ splash logo).
     *
     * @param  callable(list<string>): MobileAppSettingsData  $build
     */
    public function save(?Tenant $tenant, callable $build, ?UploadedFile $logo, bool $removeLogo): MobileAppSettingsData
    {
        return $this->inStore($tenant, static function () use ($build, $logo, $removeLogo): MobileAppSettingsData {
            $locales = app(MobileLocales::class)->codes();

            return app(MobileAppSettingsService::class)->update($build($locales), $logo, $removeLogo);
        });
    }

    /**
     * @return array<string, string> feature value => label
     */
    public static function featureOptions(): array
    {
        $options = [];

        foreach (MobileFeature::cases() as $feature) {
            $options[$feature->value] = $feature->label();
        }

        return $options;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inStore(?Tenant $tenant, callable $callback): mixed
    {
        if ($tenant === null) {
            return $callback();
        }

        return $tenant->run(static function () use ($callback): mixed {
            app()->forgetScopedInstances();

            return $callback();
        });
    }
}
