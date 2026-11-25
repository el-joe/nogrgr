<?php

namespace App\Modules\Shipping\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Weight band of a zone: [min_weight_grams, max_weight_grams] inclusive (null max = open), price in USD minor units.
 *
 * @property int $id
 * @property int $shipping_zone_id
 * @property string $name
 * @property int $min_weight_grams
 * @property int|null $max_weight_grams
 * @property Money $price
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShippingZone $zone
 */
class ShippingZoneBand extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['shipping_zone_id', 'name', 'min_weight_grams', 'max_weight_grams', 'price', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_zone_id' => 'integer',
            'min_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
            'price' => MoneyCast::class.':USD',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
