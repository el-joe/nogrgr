<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Support\CommissionCalculator;
use App\Modules\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Affiliate promo code for the first subscription payment (discount for the store,
 * attribution + optional own commission rate for the affiliate).
 *
 * @property int $id
 * @property int $affiliate_id
 * @property string $code
 * @property RateType $discount_type
 * @property int $discount_value
 * @property RateType|null $commission_type
 * @property int|null $commission_value
 * @property int $min_spend
 * @property int|null $max_uses
 * @property int $uses
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property-read Affiliate|null $affiliate
 */
class AffiliatePromoCode extends Model
{
    use CentralConnection;

    protected $table = 'affiliate_promo_codes';

    /**
     * @var list<string>
     */
    protected $fillable = ['affiliate_id', 'code', 'discount_type', 'discount_value', 'commission_type', 'commission_value', 'min_spend', 'max_uses', 'uses', 'starts_at', 'ends_at', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => RateType::class,
            'discount_value' => 'integer',
            'commission_type' => RateType::class,
            'commission_value' => 'integer',
            'min_spend' => 'integer',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isUsable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gte($at))
            && ($this->max_uses === null || $this->uses < $this->max_uses);
    }

    public function discountLabel(): string
    {
        return CommissionCalculator::label($this->discount_type, $this->discount_value);
    }

    public function discountFor(int $amountMinor): int
    {
        return CommissionCalculator::amount($this->discount_type, $this->discount_value, $amountMinor);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
