<?php

namespace App\Modules\Integrations\Enums;

use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Integrations\DTOs\SettingField;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Settings tabs of Admin › Integrations (besides Payment gateways), Figma Admin Panel
 * 53:18783 / 79:87472. Each section is one settings group in `integration_settings`;
 * `configMap()` overlays the saved values on config() at runtime (env stays the
 * fallback while a section is disabled or never saved).
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
        };
    }

    /**
     * Sections with an "Enabled" switch: while off, config keeps the env values.
     */
    public function hasEnabledSwitch(): bool
    {
        return $this !== self::Ai;
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
            ],
            self::Apple => [
                $enabled,
                new SettingField('client_id', required: true),
                new SettingField('team_id', required: true, maxLength: 20),
                new SettingField('key_id', required: true, maxLength: 20),
                new SettingField('private_key', SettingFieldType::File, true, accept: '.p8,text/plain'),
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
                new SettingField('freellmapi_base_url', SettingFieldType::Url, true, default: TranslationProvider::FreeLlmApi->defaultBaseUrl()),
                new SettingField('freellmapi_api_key', SettingFieldType::Secret),
                new SettingField('freellmapi_model', required: true, maxLength: 120, default: TranslationProvider::FreeLlmApi->defaultModel()),
            ],
            self::Neozena => [
                $enabled,
                new SettingField('base_url', SettingFieldType::Url, true, default: 'https://neozena.com/api/catalog'),
                new SettingField('api_token', SettingFieldType::Secret),
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
            ],
            self::Apple => [
                'enabled' => ['services.apple.enabled'],
                'client_id' => ['services.apple.client_id'],
                'team_id' => ['services.apple.team_id'],
                'key_id' => ['services.apple.key_id'],
                'private_key' => ['services.apple.private_key'],
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
                'freellmapi_base_url' => ['ai.providers.freellmapi.base_url'],
                'freellmapi_api_key' => ['ai.providers.freellmapi.api_key'],
                'freellmapi_model' => ['ai.providers.freellmapi.model'],
            ],
            self::Neozena => [],
        };
    }

    /**
     * Test targets offered by the section's "Test" action (AI tests one provider).
     *
     * @return list<string>
     */
    public function testTargets(): array
    {
        return $this === self::Ai ? TranslationProvider::values() : [];
    }
}
