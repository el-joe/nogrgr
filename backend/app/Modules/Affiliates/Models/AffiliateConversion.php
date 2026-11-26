<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Support\CommissionCalculator;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A referred store: pending at sign-up, approved on its first paid subscription
 * payment (commission on that payment, rate snapshotted at sign-up).
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int|null $click_id
 * @property int|null $promo_code_id
 * @property ConversionSource $source
 * @property int|null $tenant_id
 * @property int|null $payment_log_id
 * @property int $sale_amount
 * @property int $commission_amount
 * @property int $paid_amount
 * @property RateType $commission_type
 * @property int $commission_value
 * @property ConversionStatus $status
 * @property string|null $note
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $reversed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate|null $affiliate
 * @property-read Tenant|null $tenant
 * @property-read AffiliatePromoCode|null $promoCode
 */
class AffiliateConversion extends Model
{
    use CentralConnection;

    protected $table = 'affiliate_conversions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliate_id', 'click_id', 'promo_code_id', 'source', 'tenant_id', 'payment_log_id', 'sale_amount',
        'commission_amount', 'paid_amount', 'commission_type', 'commission_value', 'status', 'note',
        'approved_at', 'paid_at', 'reversed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ConversionSource::class,
            'status' => ConversionStatus::class,
            'commission_type' => RateType::class,
            'commission_value' => 'integer',
            'sale_amount' => 'integer',
            'commission_amount' => 'integer',
            'paid_amount' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'CV-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function commission(): Money
    {
        return Money::ofMinor($this->commission_amount);
    }

    public function rateLabel(): string
    {
        return CommissionCalculator::label($this->commission_type, $this->commission_value);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<AffiliatePromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(AffiliatePromoCode::class);
    }
}
