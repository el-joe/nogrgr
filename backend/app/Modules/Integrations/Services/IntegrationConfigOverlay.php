<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Enums\BroadcastDriver;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Enums\MailEncryption;
use App\Modules\Integrations\Enums\UrlScheme;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Support\SocialRedirectUri;
use Illuminate\Database\QueryException;
use PDOException;

/**
 * Overlays saved settings on config() so the framework (mail, broadcasting, openai-php,
 * services.*) and the business modules (billing.*, commerce.*, tenancy DNS defaults) use
 * them without code changes. Runs after boot and before every queued job. Config files
 * hold only safe defaults (decision #69, no env), so a disabled / never-saved section
 * stays "not configured". A missing table (fresh install, before migrations) is ignored.
 */
class IntegrationConfigOverlay
{
    public function __construct(private readonly SettingsRepositoryContract $settings) {}

    public function apply(): void
    {
        try {
            $this->settings->refresh();
            $all = $this->settings->everything();
        } catch (QueryException|PDOException) {
            return;
        }

        foreach (IntegrationSection::cases() as $section) {
            $values = $all[$section->value] ?? [];

            if ($values === [] || ($section->hasEnabledSwitch() && ($values['enabled'] ?? false) !== true)) {
                continue;
            }

            $this->applyMap($section, $values);
            $this->applySpecial($section, $values);
        }

        $name = $all['general']['platform_name']['en'] ?? null;

        if (is_string($name) && $name !== '') {
            config(['app.name' => $name]);
        }

        // Until Mail is set up, the platform address shown on pages is the support e-mail.
        $support = $all['general']['support_email'] ?? null;

        if (is_string($support) && $support !== '' && ($all['mail']['enabled'] ?? false) !== true) {
            config(['mail.from.address' => $support]);
        }

        // Invoices always name a seller: the platform name until Billing settings set one.
        if (blank(config('billing.seller.name'))) {
            config(['billing.seller.name' => config('app.name')]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyMap(IntegrationSection $section, array $values): void
    {
        $overrides = [];

        foreach ($section->configMap() as $key => $paths) {
            if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                continue;
            }

            foreach ($paths as $path) {
                $overrides[$path] = $values[$key];
            }
        }

        config($overrides);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applySpecial(IntegrationSection $section, array $values): void
    {
        match ($section) {
            IntegrationSection::Mail => config([
                'mail.mailers.smtp.scheme' => (MailEncryption::tryFrom((string) ($values['encryption'] ?? '')) ?? MailEncryption::Tls)->scheme(),
            ]),
            IntegrationSection::Google => config(['services.google.redirect' => SocialRedirectUri::for('google')]),
            IntegrationSection::Apple => config(['services.apple.redirect' => SocialRedirectUri::for('apple')]),
            IntegrationSection::Broadcasting => $this->broadcasting($values),
            IntegrationSection::Geocoding => config(['services.geocoding.cache_ttl' => max(1, (int) ($values['cache_days'] ?? 7)) * 86400]),
            IntegrationSection::Domains => $this->domains($values),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function broadcasting(array $values): void
    {
        $driver = BroadcastDriver::tryFrom((string) ($values['driver'] ?? '')) ?? BroadcastDriver::Reverb;
        $scheme = UrlScheme::tryFrom((string) ($values['scheme'] ?? '')) ?? UrlScheme::Https;
        $connection = 'broadcasting.connections.'.$driver->value;

        $overrides = [
            'broadcasting.default' => $driver->value,
            $connection.'.app_id' => (string) ($values['app_id'] ?? ''),
            $connection.'.key' => (string) ($values['key'] ?? ''),
            $connection.'.secret' => (string) ($values['secret'] ?? ''),
            $connection.'.options.scheme' => $scheme->value,
            $connection.'.options.useTLS' => $scheme === UrlScheme::Https,
        ];

        if ($driver === BroadcastDriver::Pusher) {
            $overrides[$connection.'.options.cluster'] = (string) ($values['cluster'] ?? '') ?: 'mt1';
        } else {
            $overrides[$connection.'.options.host'] = (string) ($values['host'] ?? '');
            $overrides[$connection.'.options.port'] = (int) ($values['port'] ?? 443);
            $overrides['reverb.apps.apps.0.app_id'] = (string) ($values['app_id'] ?? '');
            $overrides['reverb.apps.apps.0.key'] = (string) ($values['key'] ?? '');
            $overrides['reverb.apps.apps.0.secret'] = (string) ($values['secret'] ?? '');
            $overrides['reverb.apps.apps.0.options.host'] = (string) ($values['host'] ?? '');
            $overrides['reverb.apps.apps.0.options.port'] = (int) ($values['port'] ?? 443);
            $overrides['reverb.apps.apps.0.options.scheme'] = $scheme->value;
            $overrides['reverb.apps.apps.0.options.useTLS'] = $scheme === UrlScheme::Https;
        }

        config($overrides);
    }

    /**
     * The apex A record of the default custom-domain DNS set points at the edge IPv4.
     *
     * @param  array<string, mixed>  $values
     */
    private function domains(array $values): void
    {
        $records = (array) config('tenancy.custom_domain_records', []);

        foreach ($records as $index => $record) {
            if (is_array($record) && strtoupper((string) ($record['type'] ?? '')) === 'A' && ($record['host'] ?? '@') === '@') {
                $records[$index]['value'] = (string) ($values['edge_ipv4'] ?? '');
            }
        }

        config(['tenancy.custom_domain_records' => $records]);
    }
}
