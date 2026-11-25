<?php

namespace App\Modules\Integrations\Repositories\Contracts;

use App\Modules\Integrations\Models\IntegrationSetting;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Platform settings store (`integration_settings`): values are decoded (and secrets
 * decrypted) on read, encoded (and encrypted) on write. Results are memoised for the
 * request / job and refreshed after every write.
 *
 * @extends RepositoryContract<IntegrationSetting>
 */
interface SettingsRepositoryContract extends RepositoryContract
{
    /**
     * Every stored setting, group => key => value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function everything(): array;

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array;

    public function get(string $group, string $key, mixed $default = null): mixed;

    public function has(string $group, string $key): bool;

    /**
     * Upsert values of one group; keys listed in $secretKeys are encrypted.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $secretKeys
     */
    public function put(string $group, array $values, array $secretKeys = []): void;

    /**
     * @param  list<string>  $keys
     */
    public function forget(string $group, array $keys): void;

    /**
     * Drop the memoised values (next read hits the database).
     */
    public function refresh(): void;
}
