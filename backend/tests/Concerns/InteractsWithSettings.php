<?php

namespace Tests\Concerns;

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\IntegrationConfigOverlay;

/**
 * Decision #69: integrations and business settings come from the DB. Tests configure
 * them the same way (saved settings + the runtime overlay), never through env.
 */
trait InteractsWithSettings
{
    /**
     * Save a settings section (secret fields encrypted) and apply the config overlay.
     *
     * @param  array<string, mixed>  $values
     */
    protected function useSettings(IntegrationSection $section, array $values): void
    {
        $secrets = [];

        foreach ($section->fields() as $field) {
            if ($field->isSecret()) {
                $secrets[] = $field->key;
            }
        }

        app(SettingsRepositoryContract::class)->put($section->value, $values, $secrets);
        app(IntegrationConfigOverlay::class)->apply();
    }
}
