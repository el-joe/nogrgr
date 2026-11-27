<?php

namespace App\Modules\Api\Http\Requests\Panel;

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\DTOs\PlatformReleaseData;
use App\Modules\Api\Enums\MobileFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Mobile app settings form of the vendor (VEN-037) and the admin (ADM-041g): versions +
 * store URL per platform (latest ≥ minimum), maintenance switch + message per locale,
 * splash colour + logo, feature switches. Access is checked by the route permission.
 */
class MobileAppSettingsRequest extends FormRequest
{
    public const VERSION = 'regex:/^\d+(\.\d+){0,3}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'maintenance' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'array'],
            'maintenance_message.*' => ['nullable', 'string', 'max:300'],
            'splash_background' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'splash_logo' => ['nullable', 'array', 'max:1'],
            'splash_logo.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
            'splash_logo_keep' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
        ];

        foreach (['ios', 'android'] as $platform) {
            $rules[$platform.'_min_version'] = ['nullable', 'string', 'max:20', self::VERSION];
            $rules[$platform.'_latest_version'] = ['nullable', 'string', 'max:20', self::VERSION];
            $rules[$platform.'_store_url'] = ['nullable', 'url:https', 'max:500'];
        }

        foreach (MobileFeature::cases() as $feature) {
            $rules['features.'.$feature->value] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['ios', 'android'] as $platform) {
                $min = $this->input($platform.'_min_version');
                $latest = $this->input($platform.'_latest_version');

                $valid = static fn (mixed $version): bool => is_string($version) && preg_match('/^\d+(\.\d+){0,3}$/', $version) === 1;

                if ($valid($min) && $valid($latest) && version_compare((string) $latest, (string) $min, '<')) {
                    $validator->errors()->add($platform.'_latest_version', (string) __('api::panel.errors.latest_below_min'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['ios', 'android'] as $platform) {
            foreach (['min_version', 'latest_version', 'store_url'] as $field) {
                $attributes[$platform.'_'.$field] = (string) __('api::panel.fields.'.$field).' ('.__('api::panel.platforms.'.$platform).')';
            }
        }

        return $attributes + [
            'splash_background' => (string) __('api::panel.fields.splash_background'),
            'splash_logo' => (string) __('api::panel.fields.splash_logo'),
            'splash_logo.*' => (string) __('api::panel.fields.splash_logo'),
            'maintenance_message.*' => (string) __('api::panel.fields.maintenance_message'),
        ];
    }

    /**
     * @param  list<string>  $locales  the store's languages (messages of other locales are dropped)
     */
    public function toData(array $locales): MobileAppSettingsData
    {
        $messages = [];

        foreach ($locales as $locale) {
            $text = trim((string) $this->input('maintenance_message.'.$locale, ''));

            if ($text !== '') {
                $messages[$locale] = $text;
            }
        }

        $features = [];

        foreach (MobileFeature::cases() as $feature) {
            $features[$feature->value] = $this->boolean('features.'.$feature->value);
        }

        $splash = $this->input('splash_background');

        return new MobileAppSettingsData(
            ios: $this->release('ios'),
            android: $this->release('android'),
            maintenance: $this->boolean('maintenance'),
            maintenanceMessage: $messages,
            splashBackground: is_string($splash) && $splash !== '' ? strtolower($splash) : null,
            features: $features,
        );
    }

    public function splashLogo(): ?UploadedFile
    {
        $file = ((array) $this->file('splash_logo', []))[0] ?? null;

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * The image upload keeps the current logo tile as splash_logo_keep[]; without it (and
     * without a new file) the logo was removed.
     */
    public function removesSplashLogo(): bool
    {
        return $this->splashLogo() === null && ! in_array('current', (array) $this->input('splash_logo_keep', []), true);
    }

    private function release(string $platform): PlatformReleaseData
    {
        return PlatformReleaseData::fromSetting([
            'min_version' => $this->input($platform.'_min_version'),
            'latest_version' => $this->input($platform.'_latest_version'),
            'store_url' => $this->input($platform.'_store_url'),
        ]);
    }
}
