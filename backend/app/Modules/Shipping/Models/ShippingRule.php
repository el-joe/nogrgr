<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Database\Factories\ShippingRuleFactory;
use App\Modules\Shipping\Enums\ShippingRuleStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Shipping rule (ADM-032): countries → zones → weight bands. A country belongs to at
 * most one active rule.
 *
 * @property int $id
 * @property string $name
 * @property ShippingRuleStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Country> $countries
 * @property-read Collection<int, ShippingZone> $zones
 * @property-read int|null $zones_count
 * @property-read int|null $bands_count
 */
class ShippingRule extends Model
{
    use CentralConnection;

    /** @use HasFactory<ShippingRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ShippingRuleStatus::class];
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'shipping_rule_countries');
    }

    /**
     * @return HasMany<ShippingZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(ShippingZone::class)->orderBy('position')->orderBy('id');
    }
}
