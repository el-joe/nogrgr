<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Enums\LegacyWriteResult;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;

/**
 * Old `app_settings` → `integration_settings` (decision #69: every setting in the DB).
 * Secret values are decrypted with the OLD APP_KEY and stored through the settings
 * repository, which re-encrypts them with ours. Values are never printed or reported;
 * unmapped keys are listed by name only.
 */
class SettingsStep implements LegacyStepHandler
{
    /** old key => [group, new key, secret?, cast] */
    private const MAP = [
        'mail_mailer' => ['mail', 'mailer', false, 'string'],
        'mail_host' => ['mail', 'host', false, 'string'],
        'mail_port' => ['mail', 'port', false, 'int'],
        'mail_username' => ['mail', 'username', false, 'string'],
        'mail_password' => ['mail', 'password', true, 'string'],
        'mail_from_address' => ['mail', 'from_address', false, 'string'],
        'mail_from_name' => ['mail', 'from_name', false, 'string'],
        'openai_api_key' => ['ai', 'openai_api_key', true, 'string'],
        'openai_base_url' => ['ai', 'openai_base_url', false, 'string'],
        'openai_text_model' => ['ai', 'openai_model', false, 'string'],
        'openai_image_model' => ['ai', 'openai_image_model', false, 'string'],
        'affiliate_cookie_days' => ['affiliates', 'cookie_days', false, 'int'],
        'affiliate_default_commission_value' => ['affiliates', 'default_commission_bps', false, 'bps'],
        'affiliate_min_payout' => ['affiliates', 'min_payout_cents', false, 'cents'],
        'support_email' => [GeneralSettingsService::GROUP, 'support_email', false, 'string'],
        'out_of_stock_archive_days' => [GeneralSettingsService::GROUP, 'archive_days', false, 'int'],
    ];

    public function __construct(private readonly SettingsRepositoryContract $settings) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Settings;
    }

    public function run(LegacyContext $context): void
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        /** @var array<string, list<string>> $secrets */
        $secrets = [];

        foreach ($context->source->central()->table('app_settings')->orderBy('id')->get(['id', 'key', 'value', 'type']) as $row) {
            $context->report->read('central', 'settings');
            $map = self::MAP[$row->key] ?? null;

            if ($map === null) {
                $context->report->issue(LegacyIssueLevel::Info, 'central', 'settings', (string) $row->key, 'Old setting has no new equivalent (not migrated)');
                $context->report->skipped('central', 'settings');

                continue;
            }

            [$group, $key, $secret, $cast] = $map;
            $value = $row->value;

            if ($row->type === 'secret' || $secret) {
                $value = $this->secret($context, (string) $row->key, $value);
            }

            if ($value === null || $value === '') {
                $context->report->skipped('central', 'settings');

                continue;
            }

            $groups[$group][$key] = match ($cast) {
                'int' => (int) $value,
                'bps' => (int) round((float) $value * 100),
                'cents' => (int) round((float) $value * 100),
                default => (string) $value,
            };

            if ($secret) {
                $secrets[$group][] = $key;
            }

            $context->report->wrote('central', 'settings', LegacyWriteResult::Updated);
        }

        if (($groups['affiliates']['default_commission_bps'] ?? null) !== null) {
            $type = $context->source->central()->table('app_settings')->where('key', 'affiliate_default_commission_type')->value('value');

            if ($type === 'fixed') {
                unset($groups['affiliates']['default_commission_bps']);
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'settings', 'affiliate_default_commission_type', 'Fixed default affiliate commission has no new equivalent (percentage only); program default kept');
            }
        }

        if ($context->dryRun()) {
            return;
        }

        foreach ($groups as $group => $values) {
            $this->settings->put($group, $values, $secrets[$group] ?? []);
        }
    }

    private function secret(LegacyContext $context, string $key, mixed $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        $plain = $context->source->decrypt($stored);

        if ($plain !== null) {
            return $plain;
        }

        // Some old secrets were saved before encryption was added: keep plain text, never an undecryptable payload.
        if (str_starts_with($stored, 'eyJpdiI6')) {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'settings', $key, 'Encrypted setting does not decrypt with the old APP_KEY (re-enter it in Admin > Integrations)');

            return null;
        }

        return $stored;
    }
}
