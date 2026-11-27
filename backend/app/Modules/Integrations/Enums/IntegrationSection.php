<?php

namespace App\Modules\Integrations\Enums;

use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Integrations\DTOs\SettingField;
use App\Modules\Localization\Enums\GeocodingProvider;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * DB-backed settings groups in `integration_settings` (decision #69: integrations and
 * business settings never come from .env). Integration sections are the tabs of
 * Admin › Integrations (besides Payment gateways, Figma Admin Panel 53:18783 / 79:87472);
 * business sections (billing, commerce, domains) are the tabs of Admin › Settings ›
 * Platform. `configMap()` overlays saved values on config() at runtime; config files
 * only hold safe defaults, so a disabled / never-saved section is "not configured".
 */
enum IntegrationSection: string
{
    use HasEnumHelpers;

    case Mail = 'mail';
    case Google = 'google';
    case Apple = 'apple';
    case Broadcasting = 'broadcasting';
    case Cloudflare = 'cloudflare';
    case Ai = 'ai';
    case Neozena = 'neozena';
    case Geocoding = 'geocoding';

    /** Firebase Cloud Messaging: push notifications of the mobile apps (P11). */
    case Firebase = 'firebase';
    case Billing = 'billing';
    case Commerce = 'commerce';
    case Domains = 'domains';
    case Affiliates = 'affiliates';

    /**
     * Business settings (Admin › Settings › Platform) rather than an integration.
     */
    public function isBusiness(): bool
    {
        return in_array($this, [self::Billing, self::Commerce, self::Domains, self::Affiliates], true);
    }

    /**
     * @return list<self>
     */
    public static function integrations(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $section): bool => ! $section->isBusiness()));
    }

    /**
     * @return list<self>
     */
    public static function business(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $section): bool => $section->isBusiness()));
    }

    /**
     * Route names of the section's page / save / test.
     */
    public function route(string $action): string
    {
        return ($this->isBusiness() ? 'admin.settings.platform.' : 'admin.integrations.').$action;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Mail => 'mail',
            self::Google => 'google',
            self::Apple => 'apple',
            self::Broadcasting => 'bell',
            self::Cloudflare => 'globe',
            self::Ai => 'sparkles',
            self::Neozena => 'download',
            self::Geocoding => 'map',
            self::Firebase => 'notification',
            self::Billing => 'percent',
            self::Commerce => 'cart',
            self::Domains => 'link',
            self::Affiliates => 'gift',
        };
    }

    /**
     * Sections with an "Enabled" switch: while off, config keeps its empty defaults.
     */
    public function hasEnabledSwitch(): bool
    {
        return $this !== self::Ai && $this !== self::Neozena && ! $this->isBusiness();
    }

    /**
     * Sections that work out of the box with their defaults, before anything is saved:
     * Neozena's public catalog API needs no token or switch (client feedback on #112).
     */
    public function worksWithDefaults(): bool
    {
        return $this === self::Neozena;
    }

    /**
     * @return list<SettingField>
     */
    public function fields(): array
    {
        $enabled = new SettingField('enabled', SettingFieldType::Toggle, default: false);

        return match ($this) {
            self::Mail => [
                $enabled,
                new SettingField('mailer', SettingFieldType::Select, true, MailDriver::options(), MailDriver::Smtp->value),
                new SettingField('host', required: true),
                new SettingField('port', SettingFieldType::Number, true, default: 587),
                new SettingField('encryption', SettingFieldType::Select, true, MailEncryption::options(), MailEncryption::Tls->value),
                new SettingField('username'),
                new SettingField('password', SettingFieldType::Secret),
                new SettingField('from_name', required: true, maxLength: 120),
                new SettingField('from_address', SettingFieldType::Email, true),
            ],
            self::Google => [
                $enabled,
                new SettingField('client_id', required: true),
                new SettingField('client_secret', SettingFieldType::Secret, true),
                new SettingField('customer_client_ids', SettingFieldType::Textarea, maxLength: 2000),
            ],
            self::Apple => [
                $enabled,
                new SettingField('client_id', required: true),
                new SettingField('team_id', required: true, maxLength: 20),
                new SettingField('key_id', required: true, maxLength: 20),
                new SettingField('private_key', SettingFieldType::File, true, accept: '.p8,text/plain'),
                new SettingField('customer_client_ids', SettingFieldType::Textarea, maxLength: 2000),
            ],
            self::Broadcasting => [
                $enabled,
                new SettingField('driver', SettingFieldType::Select, true, BroadcastDriver::options(), BroadcastDriver::Reverb->value),
                new SettingField('app_id', required: true, maxLength: 64),
                new SettingField('key', required: true, maxLength: 128),
                new SettingField('secret', SettingFieldType::Secret, true),
                new SettingField('host', maxLength: 191),
                new SettingField('port', SettingFieldType::Number, default: 443),
                new SettingField('scheme', SettingFieldType::Select, true, UrlScheme::options(), UrlScheme::Https->value),
                new SettingField('cluster', maxLength: 32),
            ],
            self::Cloudflare => [
                $enabled,
                new SettingField('account_id', required: true, maxLength: 64),
                new SettingField('api_token', SettingFieldType::Secret, true),
                new SettingField('zone_id', required: true, maxLength: 64),
                new SettingField('zone_name', required: true, maxLength: 191),
                new SettingField('ssl_for_saas', SettingFieldType::Toggle, default: true),
            ],
            self::Ai => [
                new SettingField('provider', SettingFieldType::Select, true, TranslationProvider::options(), TranslationProvider::FreeLlmApi->value),
                new SettingField('custom_provider', SettingFieldType::Select, true, TranslationProvider::options(), TranslationProvider::FreeLlmApi->value),
                new SettingField('paid_provider', SettingFieldType::Select, true, TranslationProvider::options(), TranslationProvider::OpenAi->value),
                new SettingField('openai_base_url', SettingFieldType::Url, true, default: TranslationProvider::OpenAi->defaultBaseUrl()),
                new SettingField('openai_api_key', SettingFieldType::Secret),
                new SettingField('openai_model', required: true, maxLength: 120, default: TranslationProvider::OpenAi->defaultModel()),
                new SettingField('openai_embedding_model', maxLength: 120, default: 'text-embedding-3-small'),
                new SettingField('openai_image_model', maxLength: 120, default: 'gpt-image-1'),
                new SettingField('freellmapi_base_url', SettingFieldType::Url, true, default: TranslationProvider::FreeLlmApi->defaultBaseUrl()),
                new SettingField('freellmapi_api_key', SettingFieldType::Secret),
                new SettingField('freellmapi_model', required: true, maxLength: 120, default: TranslationProvider::FreeLlmApi->defaultModel()),
            ],
            // Public API: no "Enabled" switch, the token is optional (#112).
            self::Neozena => [
                new SettingField('base_url', SettingFieldType::Url, true, default: 'https://neozena.com/api/catalog'),
                new SettingField('api_token', SettingFieldType::Secret),
                // Daily `catalog:import-neozena --update-existing` (off by default).
                new SettingField('daily_sync', SettingFieldType::Toggle, default: false),
                new SettingField('daily_sync_at', SettingFieldType::Time, true, default: '03:30', maxLength: 5),
            ],
            self::Geocoding => [
                $enabled,
                new SettingField('reverse_provider', SettingFieldType::Select, true, [GeocodingProvider::Nominatim->value => GeocodingProvider::Nominatim->label()], GeocodingProvider::Nominatim->value),
                new SettingField('ip_provider', SettingFieldType::Select, true, [GeocodingProvider::IpWhoIs->value => GeocodingProvider::IpWhoIs->label()], GeocodingProvider::IpWhoIs->value),
                new SettingField('nominatim_url', SettingFieldType::Url, true, default: 'https://nominatim.openstreetmap.org'),
                new SettingField('contact_email', SettingFieldType::Email, maxLength: 191),
                new SettingField('ipwhois_url', SettingFieldType::Url, true, default: 'https://ipwho.is'),
                new SettingField('user_agent', required: true, maxLength: 191, default: 'NogrgrStorefront/1.0 (address lookup)'),
                new SettingField('timeout', SettingFieldType::Number, true, default: 5, min: 1, max: 30),
                new SettingField('cache_days', SettingFieldType::Number, true, default: 7, min: 1, max: 90),
            ],
            self::Firebase => [
                $enabled,
                new SettingField('project_id', required: true, maxLength: 120),
                new SettingField('service_account', SettingFieldType::File, true, accept: '.json,application/json'),
            ],
            self::Billing => [
                new SettingField('tax_rate_bp', SettingFieldType::Number, true, default: 0, min: 0, max: 10000),
                new SettingField('invoice_due_days', SettingFieldType::Number, true, default: 7, min: 0, max: 365),
                new SettingField('grace_days', SettingFieldType::Number, true, default: 7, min: 0, max: 365),
                new SettingField('settlement_overdue_days', SettingFieldType::Number, true, default: 30, min: 1, max: 365),
                new SettingField('seller_name', maxLength: 120),
                new SettingField('seller_tax_number', maxLength: 64),
                new SettingField('seller_address', SettingFieldType::Textarea, maxLength: 500),
            ],
            self::Commerce => [
                new SettingField('reservation_minutes', SettingFieldType::Number, true, default: 30, min: 5, max: 1440),
                new SettingField('store_api_rate_limit', SettingFieldType::Number, true, default: 120, min: 10, max: 10000),
                new SettingField('checkout_rate_limit', SettingFieldType::Number, true, default: 10, min: 1, max: 1000),
            ],
            self::Domains => [
                new SettingField('edge_ipv4', SettingFieldType::Ipv4, maxLength: 15),
            ],
            self::Affiliates => [
                new SettingField('cookie_days', SettingFieldType::Number, true, default: 30, min: 1, max: 365),
                new SettingField('default_commission_bps', SettingFieldType::Number, true, default: 3000, min: 0, max: 10000),
                new SettingField('min_payout_cents', SettingFieldType::Number, true, default: 5000, min: 0, max: 100000000),
            ],
        };
    }

    public function field(string $key): ?SettingField
    {
        foreach ($this->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Setting key => config path(s). Mail encryption, the redirect URIs and the
     * Reverb server app are handled by IntegrationConfigOverlay.
     *
     * @return array<string, list<string>>
     */
    public function configMap(): array
    {
        return match ($this) {
            self::Mail => [
                'mailer' => ['mail.default'],
                'host' => ['mail.mailers.smtp.host'],
                'port' => ['mail.mailers.smtp.port'],
                'username' => ['mail.mailers.smtp.username'],
                'password' => ['mail.mailers.smtp.password'],
                'from_address' => ['mail.from.address'],
                'from_name' => ['mail.from.name'],
            ],
            self::Google => [
                'enabled' => ['services.google.enabled'],
                'client_id' => ['services.google.client_id'],
                'client_secret' => ['services.google.client_secret'],
                'customer_client_ids' => ['services.google.customer_client_ids'],
            ],
            self::Apple => [
                'enabled' => ['services.apple.enabled'],
                'client_id' => ['services.apple.client_id'],
                'team_id' => ['services.apple.team_id'],
                'key_id' => ['services.apple.key_id'],
                'private_key' => ['services.apple.private_key'],
                'customer_client_ids' => ['services.apple.customer_client_ids'],
            ],
            self::Broadcasting => [],
            self::Cloudflare => [
                'enabled' => ['services.cloudflare.enabled'],
                'account_id' => ['services.cloudflare.account_id'],
                'api_token' => ['services.cloudflare.api_token'],
                'zone_id' => ['services.cloudflare.zone_id'],
                'zone_name' => ['services.cloudflare.zone_name'],
                'ssl_for_saas' => ['services.cloudflare.ssl_for_saas'],
            ],
            self::Ai => [
                'provider' => ['ai.translation.provider'],
                'custom_provider' => ['ai.translation.custom_provider'],
                'paid_provider' => ['ai.translation.paid_provider'],
                'openai_base_url' => ['ai.providers.openai.base_url', 'openai.base_uri'],
                'openai_api_key' => ['ai.providers.openai.api_key', 'openai.api_key'],
                'openai_model' => ['ai.providers.openai.model'],
                'openai_embedding_model' => ['ai.providers.openai.embedding_model'],
                'openai_image_model' => ['ai.providers.openai.image_model'],
                'freellmapi_base_url' => ['ai.providers.freellmapi.base_url'],
                'freellmapi_api_key' => ['ai.providers.freellmapi.api_key'],
                'freellmapi_model' => ['ai.providers.freellmapi.model'],
            ],
            self::Neozena => [],
            self::Geocoding => [
                'enabled' => ['services.geocoding.enabled'],
                'reverse_provider' => ['services.geocoding.reverse_provider'],
                'ip_provider' => ['services.geocoding.ip_provider'],
                'nominatim_url' => ['services.geocoding.nominatim.url'],
                'contact_email' => ['services.geocoding.nominatim.email'],
                'ipwhois_url' => ['services.geocoding.ipwhois.url'],
                'user_agent' => ['services.geocoding.user_agent'],
                'timeout' => ['services.geocoding.timeout'],
            ],
            self::Firebase => [
                'enabled' => ['services.fcm.enabled'],
                'project_id' => ['services.fcm.project_id'],
                'service_account' => ['services.fcm.service_account'],
            ],
            self::Billing => [
                'tax_rate_bp' => ['billing.tax_rate_bp'],
                'invoice_due_days' => ['billing.invoice_due_days'],
                'grace_days' => ['billing.grace_days'],
                'settlement_overdue_days' => ['billing.settlement_overdue_days'],
                'seller_name' => ['billing.seller.name'],
                'seller_address' => ['billing.seller.address'],
                'seller_tax_number' => ['billing.seller.tax_number'],
            ],
            self::Commerce => [
                'reservation_minutes' => ['commerce.reservation_minutes'],
                'store_api_rate_limit' => ['commerce.store_api_rate_limit'],
                'checkout_rate_limit' => ['commerce.checkout_rate_limit'],
            ],
            self::Domains => [],
            self::Affiliates => [],
        };
    }

    /**
     * Test targets offered by the section's "Test" action (AI tests one provider).
     *
     * @return list<string>
     */
    public function testTargets(): array
    {
        if ($this->isBusiness()) {
            return [];
        }

        return $this === self::Ai ? TranslationProvider::values() : [];
    }
}
