<?php

namespace App\Modules\Api\DTOs;

use App\Modules\Api\Enums\MobileFeature;
use App\Shared\DTOs\BaseDTO;

/**
 * The store's mobile app settings (tenant `store_settings` key `mobile_app`): releases per
 * platform (force / optional update), maintenance switch + message per locale, splash
 * colours and feature switches. Edited by the vendor (VEN-037) and the admin (ADM-041g).
 */
final class MobileAppSettingsData extends BaseDTO
{
    /**
     * @param  array<string, string>  $maintenanceMessage  locale => text
     * @param  array<string, bool>  $features  MobileFeature value => enabled (missing = default)
     */
    public function __construct(
        public readonly PlatformReleaseData $ios = new PlatformReleaseData,
        public readonly PlatformReleaseData $android = new PlatformReleaseData,
        public readonly bool $maintenance = false,
        public readonly array $maintenanceMessage = [],
        public readonly ?string $splashBackground = null,
        public readonly ?string $splashLogoPath = null,
        public readonly array $features = [],
    ) {}

    public static function fromSetting(mixed $value): self
    {
        $value = is_array($value) ? $value : [];
        $messages = array_filter((array) ($value['maintenance_message'] ?? []), static fn (mixed $text, mixed $locale): bool => is_string($text) && $text !== '' && is_string($locale), ARRAY_FILTER_USE_BOTH);
        $features = [];

        foreach ((array) ($value['features'] ?? []) as $key => $enabled) {
            if (MobileFeature::tryFrom((string) $key) !== null) {
                $features[(string) $key] = (bool) $enabled;
            }
        }

        $splash = $value['splash_background'] ?? null;

        return new self(
            ios: PlatformReleaseData::fromSetting(is_array($value['ios'] ?? null) ? $value['ios'] : null),
            android: PlatformReleaseData::fromSetting(is_array($value['android'] ?? null) ? $value['android'] : null),
            maintenance: (bool) ($value['maintenance'] ?? false),
            maintenanceMessage: $messages,
            splashBackground: is_string($splash) && preg_match('/^#[0-9a-f]{6}$/i', $splash) === 1 ? strtolower($splash) : null,
            splashLogoPath: is_string($value['splash_logo_path'] ?? null) ? $value['splash_logo_path'] : null,
            features: $features,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSetting(): array
    {
        return [
            'ios' => $this->ios->toSetting(),
            'android' => $this->android->toSetting(),
            'maintenance' => $this->maintenance,
            'maintenance_message' => $this->maintenanceMessage,
            'splash_background' => $this->splashBackground,
            'splash_logo_path' => $this->splashLogoPath,
            'features' => $this->features,
        ];
    }

    public function withSplashLogo(?string $path): self
    {
        return new self($this->ios, $this->android, $this->maintenance, $this->maintenanceMessage, $this->splashBackground, $path, $this->features);
    }

    public function release(string $platform): PlatformReleaseData
    {
        return $platform === 'ios' ? $this->ios : $this->android;
    }

    /**
     * @return array<string, bool>
     */
    public function featureFlags(): array
    {
        $flags = [];

        foreach (MobileFeature::cases() as $feature) {
            $flags[$feature->value] = $this->features[$feature->value] ?? $feature->defaultEnabled();
        }

        return $flags;
    }
}
