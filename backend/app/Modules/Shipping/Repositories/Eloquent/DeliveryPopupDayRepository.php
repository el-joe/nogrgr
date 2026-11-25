<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Models\DeliveryPopupDay;
use App\Modules\Shipping\Repositories\Contracts\DeliveryPopupDayRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<DeliveryPopupDay>
 */
class DeliveryPopupDayRepository extends BaseRepository implements DeliveryPopupDayRepositoryContract
{
    protected function model(): string
    {
        return DeliveryPopupDay::class;
    }

    public function percentages(int $countryKey): array
    {
        /** @var array<int, int> $rows */
        $rows = $this->query()->where('country_key', $countryKey)->orderBy('day_number')->pluck('percentage', 'day_number')->map(static fn ($value): int => (int) $value)->all();

        return $rows;
    }

    public function replace(int $countryKey, array $percentages): void
    {
        $now = now();
        $this->clear($countryKey);
        $this->query()->insert(array_map(static fn (int $day, int $percentage): array => [
            'country_key' => $countryKey,
            'day_number' => $day,
            'percentage' => $percentage,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($percentages), array_values($percentages)));
    }

    public function clear(int $countryKey): void
    {
        $this->query()->where('country_key', $countryKey)->delete();
    }

    public function customisedCountryIds(): array
    {
        return $this->query()->where('country_key', '>', DeliveryPopupDay::ALL_COUNTRIES)->distinct()->orderBy('country_key')->pluck('country_key')->map(static fn ($id): int => (int) $id)->all();
    }
}
