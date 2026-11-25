<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Database\Factories\StoreCouponFactory;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Models\Concerns\CouponFields;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Store-owned coupon (tenant DB, VEN-040): absorbed by the vendor share
 * (source = vendor). Countries are central country ids; none = every country.
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property CouponType $type
 * @property float|null $percentage
 * @property Money|null $amount_minor
 * @property Money|null $max_discount_minor
 * @property Money|null $min_spend_minor
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property bool $first_order_only
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreCoupon extends Model
{
    use CouponFields;

    /** @use HasFactory<StoreCouponFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'store_coupons';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code', 'name', 'type', 'percentage', 'amount_minor', 'max_discount_minor', 'min_spend_minor',
        'usage_limit', 'per_customer_limit', 'first_order_only', 'starts_at', 'ends_at', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->couponCasts();
    }

    protected static function newFactory(): StoreCouponFactory
    {
        return StoreCouponFactory::new();
    }

    /**
     * @return HasMany<StoreCouponCountry, $this>
     */
    public function countryRows(): HasMany
    {
        return $this->hasMany(StoreCouponCountry::class);
    }

    /**
     * @return list<int>
     */
    public function countryIds(): array
    {
        return $this->countryRows->pluck('country_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
    }
}
