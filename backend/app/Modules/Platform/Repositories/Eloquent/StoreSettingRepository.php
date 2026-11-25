<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<StoreSetting>
 */
class StoreSettingRepository extends BaseRepository implements StoreSettingRepositoryContract
{
    protected function model(): string
    {
        return StoreSetting::class;
    }

    public function get(StoreSettingKey $key): mixed
    {
        return $this->query()->where('key', $key->value)->first()?->value;
    }

    public function put(StoreSettingKey $key, mixed $value): void
    {
        $this->query()->updateOrCreate(['key' => $key->value], ['value' => $value]);
    }

    public function forget(StoreSettingKey $key): void
    {
        $this->query()->where('key', $key->value)->delete();
    }
}
