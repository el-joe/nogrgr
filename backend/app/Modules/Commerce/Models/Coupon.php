<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Database\Factories\CouponFactory;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\Concerns\CouponFields;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central (admin) coupon, ADM-025. Absorbed by the platform share (source = central);
 * read-only to stores, which may hide it. No country rows = every country; scope
 * "selected" = only the stores of the pivot.
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
 * @property PromotionScope $store_scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Country> $countries
 * @property-read Collection<int, Tenant> $tenants
 * @property-read int|null $redemptions_count
 */
class Coupon extends Model
{
    use CentralConnection;
    use CouponFields;

    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'coupons';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code', 'name', 'type', 'percentage', 'amount_minor', 'max_discount_minor', 'min_spend_minor',
        'usage_limit', 'per_customer_limit', 'first_order_only', 'starts_at', 'ends_at', 'is_active', 'store_scope',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...$this->couponCasts(), 'store_scope' => PromotionScope::class];
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'coupon_country');
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'coupon_tenant');
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
