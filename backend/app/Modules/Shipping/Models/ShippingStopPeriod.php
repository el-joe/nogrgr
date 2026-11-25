<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Enums\StopPeriodRecurrence;
use App\Modules\Shipping\Enums\Weekday;
use App\Modules\Shipping\Support\StopPeriodRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Days a company (null = all companies) does not deliver in a country (null = all
 * countries): a date range, or a weekday every week (ADM-033c).
 *
 * @property int $id
 * @property string $name
 * @property int|null $shipping_company_id
 * @property int|null $country_id
 * @property StopPeriodRecurrence $recurrence
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Weekday|null $weekday
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShippingCompany|null $company
 * @property-read Country|null $country
 */
class ShippingStopPeriod extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'shipping_company_id', 'country_id', 'recurrence', 'starts_on', 'ends_on', 'weekday', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_company_id' => 'integer',
            'country_id' => 'integer',
            'recurrence' => StopPeriodRecurrence::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'weekday' => Weekday::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShippingCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function toRule(): StopPeriodRule
    {
        return new StopPeriodRule(
            startsOn: $this->starts_on->toDateString(),
            endsOn: $this->ends_on?->toDateString(),
            weekday: $this->recurrence === StopPeriodRecurrence::Weekly ? $this->weekday?->value : null,
            name: $this->name,
        );
    }
}
