<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\City;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Zone of a shipping rule: the cities it covers (none = the whole country) and its
 * weight bands (first matching band wins, no band = unavailable).
 *
 * @property int $id
 * @property int $shipping_rule_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShippingRule $rule
 * @property-read Collection<int, City> $cities
 * @property-read Collection<int, ShippingZoneBand> $bands
 */
class ShippingZone extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['shipping_rule_id', 'name', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shipping_rule_id' => 'integer', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<ShippingRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ShippingRule::class, 'shipping_rule_id');
    }

    /**
     * @return BelongsToMany<City, $this>
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'shipping_zone_cities');
    }

    /**
     * @return HasMany<ShippingZoneBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(ShippingZoneBand::class)->orderBy('position')->orderBy('id');
    }
}
