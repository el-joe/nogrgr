<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Inventory\Database\Factories\BranchFactory;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central warehouse / pickup point. Default fulfilment branch of the countries in
 * `defaultCountries` (one branch per country) and serves the cities in `zones`.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $country_id
 * @property int|null $city_id
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $pickup_hours
 * @property bool $allows_pickup
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Country $country
 * @property-read City|null $city
 * @property-read int|null $skus_count  withCount alias (admin branches list)
 * @property-read int|string|null $units_sum  withSum alias (admin branches list)
 */
class Branch extends Model implements AiTranslatable
{
    use CentralConnection;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'code', 'country_id', 'city_id', 'address', 'phone', 'pickup_hours', 'allows_pickup', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'city_id' => 'integer',
            'allows_pickup' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Countries this branch fulfils by default.
     *
     * @return BelongsToMany<Country, $this>
     */
    public function defaultCountries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'branch_default_countries');
    }

    /**
     * Cities (zones) the branch serves.
     *
     * @return BelongsToMany<City, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'branch_zones');
    }

    /**
     * @return HasMany<BranchStock, $this>
     */
    public function stock(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }
}
