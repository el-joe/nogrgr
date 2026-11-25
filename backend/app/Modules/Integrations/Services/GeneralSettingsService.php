<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\DTOs\GeneralSettingsData;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Admin › Settings › General (ADM-101): platform name (ar/en), support contacts,
 * default timezone, archive window, social links and branding images (public disk).
 * The default language stays owned by Localization (LanguageService::setDefault).
 */
class GeneralSettingsService extends BaseService
{
    public const GROUP = 'general';

    public const DISK = 'public';

    public const DIRECTORY = 'branding';

    public const IMAGES = ['logo_light', 'logo_dark', 'favicon'];

    public const SOCIALS = ['instagram', 'x', 'tiktok', 'linkedin', 'facebook', 'youtube'];

    public const DEFAULT_ARCHIVE_DAYS = 365;

    public function __construct(
        private readonly SettingsRepositoryContract $settings,
        private readonly LanguageService $languages,
        private readonly IntegrationConfigOverlay $overlay,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return array{platform_name: array<string, string>, support_email: string|null, support_phone: string|null, support_phone_country: string|null, timezone: string, default_language: string, archive_days: int, socials: array<string, string|null>, images: array<string, string|null>}
     */
    public function current(): array
    {
        $stored = $this->settings->group(self::GROUP);
        $socials = is_array($stored['socials'] ?? null) ? $stored['socials'] : [];
        $images = [];

        foreach (self::IMAGES as $image) {
            $path = $stored[$image] ?? null;
            $images[$image] = is_string($path) && $path !== '' ? Storage::disk(self::DISK)->url($path) : null;
        }

        return [
            'platform_name' => is_array($stored['platform_name'] ?? null) ? $stored['platform_name'] : ['en' => (string) config('app.name'), 'ar' => (string) config('app.name')],
            'support_email' => $stored['support_email'] ?? null,
            'support_phone' => $stored['support_phone'] ?? null,
            'support_phone_country' => $stored['support_phone_country'] ?? null,
            'timezone' => (string) ($stored['timezone'] ?? config('app.timezone')),
            'default_language' => $this->languages->default()->code ?? Language::FALLBACK,
            'archive_days' => (int) ($stored['archive_days'] ?? self::DEFAULT_ARCHIVE_DAYS),
            'socials' => array_combine(self::SOCIALS, array_map(static fn (string $network): ?string => $socials[$network] ?? null, self::SOCIALS)),
            'images' => $images,
        ];
    }

    /**
     * Platform name in a locale (falls back to English, then app.name).
     */
    public function platformName(?string $locale = null): string
    {
        $names = $this->current()['platform_name'];

        return (string) ($names[$locale ?? app()->getLocale()] ?? $names['en'] ?? config('app.name'));
    }

    /**
     * code => native name of the active languages (default-language select).
     *
     * @return array<string, string>
     */
    public function languageOptions(): array
    {
        /** @var Collection<int, Language> $active */
        $active = $this->languages->active();

        return $active->mapWithKeys(static fn (Language $language): array => [$language->code => $language->native_name])->all();
    }

    public function save(GeneralSettingsData $data): void
    {
        $stored = $this->settings->group(self::GROUP);
        $values = [
            'platform_name' => $data->platformName,
            'support_email' => $data->supportEmail,
            'support_phone' => $data->supportPhone,
            'support_phone_country' => $data->supportPhone !== null ? $data->supportPhoneCountry : null,
            'timezone' => $data->timezone,
            'archive_days' => $data->archiveDays,
            'socials' => $data->socials,
        ];

        $obsolete = [];

        foreach (self::IMAGES as $image) {
            $previous = is_string($stored[$image] ?? null) ? $stored[$image] : null;

            if (isset($data->images[$image])) {
                $values[$image] = $data->images[$image]->store(self::DIRECTORY, self::DISK);
            } elseif (in_array($image, $data->removeImages, true)) {
                $values[$image] = null;
            } else {
                continue;
            }

            if ($previous !== null) {
                $obsolete[] = $previous;
            }
        }

        $this->transaction(fn () => $this->settings->put(self::GROUP, $values), $this->settings->query()->getModel()->getConnectionName());

        if ($data->defaultLanguage !== ($this->languages->default()->code ?? null)) {
            $this->languages->setDefault($data->defaultLanguage);
        }

        if ($obsolete !== []) {
            Storage::disk(self::DISK)->delete($obsolete);
        }

        $changed = array_keys(array_filter($values, static fn (mixed $value, string $key): bool => ($stored[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            $this->auditor->record('settings.general_updated', null, ['keys' => $changed]);
        }

        $this->overlay->apply();
    }
}
