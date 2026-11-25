<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Database\Factories\FixedShippingCostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Fixed ("weight") shipping rate of one country (ADM-031): USD per gram, one row per country.
 *
 * @property int $id
 * @property int $country_id
 * @property string $price_per_gram decimal(14,6)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 */
class FixedShippingCost extends Model
{
    use CentralConnection;

    /** @use HasFactory<FixedShippingCostFactory> */
    use HasFactory;

    protected $table = 'shipping_fixed_costs';

    /**
     * @var list<string>
     */
    protected $fillable = ['country_id', 'price_per_gram'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['country_id' => 'integer', 'price_per_gram' => 'decimal:6'];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
