<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Models\ShippingSetting;
use App\Modules\Shipping\Repositories\Contracts\ShippingSettingRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ShippingSetting>
 */
class ShippingSettingRepository extends BaseRepository implements ShippingSettingRepositoryContract
{
    protected function model(): string
    {
        return ShippingSetting::class;
    }

    public function values(): array
    {
        /** @var array<string, mixed> $values */
        $values = $this->query()->get(['key', 'value'])->mapWithKeys(static fn (ShippingSetting $setting): array => [$setting->key => $setting->value])->all();

        return $values;
    }

    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
