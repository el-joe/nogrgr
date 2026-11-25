<?php

namespace App\Modules\Integrations\Repositories\Eloquent;

use App\Modules\Integrations\Models\IntegrationSetting;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends BaseRepository<IntegrationSetting>
 */
class SettingsRepository extends BaseRepository implements SettingsRepositoryContract
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $memo = null;

    protected function model(): string
    {
        return IntegrationSetting::class;
    }

    public function everything(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $settings = [];

        foreach ($this->query()->get(['group', 'key', 'value', 'is_secret']) as $row) {
            $settings[$row->group][$row->key] = $this->decode($row);
        }

        return $this->memo = $settings;
    }

    public function group(string $group): array
    {
        return $this->everything()[$group] ?? [];
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($group)[$key] ?? $default;
    }

    public function has(string $group, string $key): bool
    {
        $value = $this->group($group)[$key] ?? null;

        return $value !== null && $value !== '';
    }

    public function put(string $group, array $values, array $secretKeys = []): void
    {
        foreach ($values as $key => $value) {
            $secret = in_array($key, $secretKeys, true);
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $this->query()->updateOrCreate(
                ['group' => $group, 'key' => (string) $key],
                ['value' => $secret ? Crypt::encryptString($json) : $json, 'is_secret' => $secret],
            );
        }

        $this->refresh();
    }

    public function forget(string $group, array $keys): void
    {
        if ($keys !== []) {
            $this->query()->where('group', $group)->whereIn('key', $keys)->delete();
        }

        $this->refresh();
    }

    public function refresh(): void
    {
        $this->memo = null;
    }

    private function decode(IntegrationSetting $row): mixed
    {
        $raw = $row->getAttributes()['value'] ?? null;

        if (! is_string($raw)) {
            return null;
        }

        if ($row->is_secret) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (DecryptException) {
                return null;
            }
        }

        return json_decode($raw, true);
    }
}
