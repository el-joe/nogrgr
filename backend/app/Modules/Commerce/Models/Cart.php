<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Storefront cart (tenant DB): a guest cart is found by its token (X-Cart-Token), a
 * customer cart by customer_id. The country drives prices, availability and shipping.
 *
 * @property int $id
 * @property string $token
 * @property int|null $customer_id
 * @property int|null $country_id
 * @property int|null $city_id
 * @property string|null $coupon_code
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CartItem> $items
 */
class Cart extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['token', 'customer_id', 'country_id', 'city_id', 'coupon_code', 'last_activity_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'country_id' => 'integer',
            'city_id' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
