<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Modules\Notifications\DTOs\MailLayoutData;
use App\Modules\Notifications\DTOs\MailLayoutSettingsData;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Auditor;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared branded e-mail layout (MAIL-001, decision #118). Admin › Email layout edits the
 * central variant (settings group `email_layout`: logo, brand + background colour,
 * footer per locale, social links); inside a store (tenant context) the store variant
 * uses the store's name, logo, brand colour and social links with the platform footer.
 */
class MailLayoutService extends BaseService
{
    public const GROUP = 'email_layout';

    public const DISK = 'public';

    public const DIRECTORY = 'branding/mail';

    public const DEFAULT_PRIMARY = '#ff5a2c';

    public const DEFAULT_BACKGROUND = '#f3eeea';

    public function __construct(
        private readonly SettingsRepositoryContract $settings,
        private readonly Auditor $auditor,
    ) {}

    /**
     * Stored central layout with defaults (the admin form).
     *
     * @return array{logo_url: string|null, has_own_logo: bool, primary_color: string, background_color: string, footer: array<string, string>, socials: array<string, string|null>, show_socials: bool, address: string|null, help_url: string|null, privacy_url: string|null}
     */
    public function current(): array
    {
        $stored = $this->settings->group(self::GROUP);
        $general = $this->general();
        $logo = is_string($stored['logo'] ?? null) && $stored['logo'] !== '' ? $stored['logo'] : null;
        $socials = is_array($stored['socials'] ?? null) ? $stored['socials'] : ($general['socials'] ?? []);

        return [
            'logo_url' => $logo !== null ? Storage::disk(self::DISK)->url($logo) : ($general['images']['logo_light'] ?? null),
            'has_own_logo' => $logo !== null,
            'primary_color' => self::color($stored['primary_color'] ?? null, self::DEFAULT_PRIMARY),
            'background_color' => self::color($stored['background_color'] ?? null, self::DEFAULT_BACKGROUND),
            'footer' => is_array($stored['footer'] ?? null) ? array_map('strval', $stored['footer']) : [],
            'socials' => array_combine(GeneralSettingsService::SOCIALS, array_map(static fn (string $network): ?string => is_string($socials[$network] ?? null) && $socials[$network] !== '' ? $socials[$network] : null, GeneralSettingsService::SOCIALS)),
            'show_socials' => (bool) ($stored['show_socials'] ?? true),
            'address' => is_string($stored['address'] ?? null) && $stored['address'] !== '' ? $stored['address'] : null,
            'help_url' => is_string($stored['help_url'] ?? null) && $stored['help_url'] !== '' ? $stored['help_url'] : null,
            'privacy_url' => is_string($stored['privacy_url'] ?? null) && $stored['privacy_url'] !== '' ? $stored['privacy_url'] : null,
        ];
    }

    public function save(MailLayoutSettingsData $data): void
    {
        $stored = $this->settings->group(self::GROUP);
        $previous = is_string($stored['logo'] ?? null) && $stored['logo'] !== '' ? $stored['logo'] : null;
        $values = [
            'primary_color' => strtolower($data->primaryColor),
            'background_color' => strtolower($data->backgroundColor),
            'footer' => array_filter(array_map('trim', $data->footer), static fn (string $text): bool => $text !== ''),
            'socials' => $data->socials,
            'show_socials' => $data->showSocials,
            'address' => $data->address,
            'help_url' => $data->helpUrl,
            'privacy_url' => $data->privacyUrl,
        ];

        if ($data->logo !== null) {
            $values['logo'] = $data->logo->store(self::DIRECTORY, self::DISK);
        } elseif ($data->removeLogo) {
            $values['logo'] = null;
        }

        $this->settings->put(self::GROUP, $values);

        if ($previous !== null && array_key_exists('logo', $values)) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $this->auditor->record('email_layout.updated', null, ['logo' => array_key_exists('logo', $values) ? ($values['logo'] === null ? 'removed' : 'replaced') : 'kept']);
    }

    /**
     * Layout of an e-mail sent now: the store variant inside a store, else central.
     */
    public function forMail(string $locale): MailLayoutData
    {
        $central = $this->current();
        $platform = $this->platformName($locale);
        $footer = (string) ($central['footer'][$locale] ?? $central['footer']['en'] ?? '');
        $footer = $footer !== '' ? $footer : (string) __('notifications::mail_layout.default_footer', ['platform' => $platform], $locale);
        $socials = $central['show_socials'] ? $this->socialList($central['socials']) : [];
        $address = (string) ($central['address'] ?? '');
        $links = [];

        foreach (['help_url' => 'help', 'privacy_url' => 'privacy'] as $field => $label) {
            if ($central[$field] !== null) {
                $links[] = ['label' => (string) __('notifications::mail_layout.links.'.$label, [], $locale), 'url' => $central[$field]];
            }
        }

        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant) {
            return new MailLayoutData($platform, $central['logo_url'], $this->centralUrl(), $central['primary_color'], $central['background_color'], $footer, $socials, false, $address, $links);
        }

        $store = $this->storeBranding();

        return new MailLayoutData(
            brandName: (string) $tenant->name,
            logoUrl: $store['logo'],
            homeUrl: StoreSettingsService::storeUrl($tenant),
            primaryColor: $store['primary'] ?? $central['primary_color'],
            backgroundColor: $central['background_color'],
            footer: (string) __('notifications::mail_layout.default_footer', ['platform' => (string) $tenant->name], $locale),
            socials: $store['socials'],
            isStore: true,
            links: $links,
            poweredBy: (string) __('notifications::mail_layout.powered_by', ['platform' => $platform], $locale),
        );
    }

    /**
     * @return array{logo: string|null, primary: string|null, socials: list<array{label: string, url: string}>}
     */
    private function storeBranding(): array
    {
        try {
            $appearance = app(StorefrontAppearance::class);

            return [
                'logo' => app(StoreAppearanceService::class)->logoUrl(),
                'primary' => $appearance->colors()->primary,
                'socials' => array_map(static fn (array $link): array => ['label' => $link['label'], 'url' => $link['url']], $appearance->socialLinks()),
            ];
        } catch (Throwable) {
            return ['logo' => null, 'primary' => null, 'socials' => []];
        }
    }

    /**
     * @param  array<string, string|null>  $socials
     * @return list<array{label: string, url: string}>
     */
    private function socialList(array $socials): array
    {
        $list = [];

        foreach ($socials as $network => $url) {
            if (is_string($url) && preg_match('#^https?://#i', $url) === 1) {
                $list[] = ['label' => (string) __('notifications::mail_layout.socials.'.$network), 'url' => $url];
            }
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function general(): array
    {
        try {
            return app(GeneralSettingsService::class)->current();
        } catch (Throwable) {
            return [];
        }
    }

    private function platformName(string $locale): string
    {
        try {
            return app(GeneralSettingsService::class)->platformName($locale);
        } catch (Throwable) {
            return (string) config('app.name');
        }
    }

    private function centralUrl(): ?string
    {
        $url = config('app.url');

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    private static function color(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtolower($value) : $default;
    }
}
