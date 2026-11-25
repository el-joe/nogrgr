<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\DTOs\ConnectionTestResult;
use App\Modules\Integrations\DTOs\IntegrationSettingsData;
use App\Modules\Integrations\Enums\ConnectionStatus;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Enums\SettingFieldType;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\Testing\IntegrationConnectionTester;
use App\Modules\Integrations\Support\SecretMask;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Admin › Integrations cards (mail, Google, Apple, Reverb/Pusher, Cloudflare, AI, Neozena,
 * Geocoding) and Admin › Settings › Platform tabs (billing, commerce, domains):
 * read for the form (secrets masked), save (blank secret = keep), connection tests
 * (result stored as `_last_test[_target]`) and the runtime config overlay refresh.
 */
class IntegrationSettingsService extends BaseService
{
    public const LAST_TEST_KEY = '_last_test';

    public function __construct(
        private readonly SettingsRepositoryContract $settings,
        private readonly IntegrationConnectionTester $tester,
        private readonly IntegrationConfigOverlay $overlay,
        private readonly Auditor $auditor,
    ) {}

    /**
     * Saved values with defaults (decrypted; for services only, never for views).
     *
     * @return array<string, mixed>
     */
    public function values(IntegrationSection $section): array
    {
        $stored = $this->settings->group($section->value);
        $values = [];

        foreach ($section->fields() as $field) {
            $values[$field->key] = $stored[$field->key] ?? $field->default;
        }

        return $values;
    }

    /**
     * Form state: non-secret values, and for secrets only `masked` + `stored`.
     *
     * @return array<string, array{value: mixed, masked: string|null, stored: bool}>
     */
    public function form(IntegrationSection $section): array
    {
        $values = $this->values($section);
        $form = [];

        foreach ($section->fields() as $field) {
            $value = $values[$field->key];

            $form[$field->key] = $field->isSecret()
                ? ['value' => null, 'masked' => $field->type === SettingFieldType::File && filled($value) ? null : SecretMask::mask(is_string($value) ? $value : null), 'stored' => filled($value)]
                : ['value' => $value, 'masked' => null, 'stored' => filled($value)];
        }

        return $form;
    }

    public function hasStored(IntegrationSection $section, string $key): bool
    {
        return $this->settings->has($section->value, $key);
    }

    public function save(IntegrationSettingsData $data): void
    {
        $section = $data->section;
        $before = $this->values($section);
        $changes = [];
        $secretKeys = [];

        foreach ($section->fields() as $field) {
            if ($field->isSecret()) {
                $secretKeys[] = $field->key;
            }

            if ($field->type === SettingFieldType::File) {
                if (isset($data->files[$field->key])) {
                    $changes[$field->key] = trim((string) $data->files[$field->key]->get());
                }

                continue;
            }

            if (! array_key_exists($field->key, $data->values)) {
                continue;
            }

            $value = $data->values[$field->key];

            if ($field->isSecret() && blank($value)) {
                continue;
            }

            $changes[$field->key] = match ($field->type) {
                SettingFieldType::Toggle => (bool) $value,
                SettingFieldType::Number => $value === null || $value === '' ? null : (int) $value,
                default => $value === null ? null : trim((string) $value),
            };
        }

        $changed = array_keys(array_filter($changes, static fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        $this->transaction(function () use ($section, $changes, $secretKeys, $changed): void {
            $this->settings->put($section->value, $changes, $secretKeys);

            if ($changed !== []) {
                $this->settings->forget($section->value, $this->testKeys($section));
            }
        }, $this->connection());

        if ($changed !== []) {
            $this->auditor->record($section->isBusiness() ? 'settings.platform_updated' : 'integrations.updated', null, ['section' => $section->value, 'keys' => $changed]);
        }

        $this->overlay->apply();
    }

    public function test(IntegrationSection $section, ?string $target = null): ConnectionTestResult
    {
        $result = $this->tester->test($section, $this->values($section), $target);

        if ($section !== IntegrationSection::Mail) {
            $this->settings->put($section->value, [$this->testKey($section, $target) => $result->toArray()]);
        }

        $this->auditor->record('integrations.tested', null, ['section' => $section->value, 'target' => $section === IntegrationSection::Mail ? null : $target, 'ok' => $result->ok, 'error' => $result->error?->value]);

        return $result;
    }

    public function lastTest(IntegrationSection $section, ?string $target = null): ?ConnectionTestResult
    {
        $stored = $this->settings->get($section->value, $this->testKey($section, $target));

        return ConnectionTestResult::fromStored(is_array($stored) ? $stored : null);
    }

    public function status(IntegrationSection $section, ?string $target = null): ConnectionStatus
    {
        $values = $this->settings->group($section->value);

        if ($values === [] || ($section->hasEnabledSwitch() && ($values['enabled'] ?? false) !== true)) {
            return ConnectionStatus::NotConfigured;
        }

        return $this->lastTest($section, $target)?->status() ?? ConnectionStatus::NotTested;
    }

    /**
     * @return list<string>
     */
    private function testKeys(IntegrationSection $section): array
    {
        $targets = $section->testTargets();

        return $targets === [] ? [self::LAST_TEST_KEY] : array_map(fn (string|int $target): string => $this->testKey($section, (string) $target), $targets);
    }

    private function testKey(IntegrationSection $section, ?string $target): string
    {
        return $section->testTargets() === [] || $target === null ? self::LAST_TEST_KEY : self::LAST_TEST_KEY.'_'.$target;
    }

    private function connection(): ?string
    {
        return $this->settings->query()->getModel()->getConnectionName();
    }
}
