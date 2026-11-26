<?php

namespace App\Modules\Identity\Models;

use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\AffiliateFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Notifications\Concerns\ReceivesPanelNotifications;
use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Affiliate partner (central DB, guard "affiliate"). Every tenant owns one (tenant_id).
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property AccountStatus $status
 * @property string|null $locale
 * @property int|null $tenant_id
 * @property string|null $code
 * @property AffiliateType $type
 * @property RateType|null $commission_type
 * @property int|null $commission_value
 * @property int $balance
 * @property int $total_earned
 * @property int $total_paid
 * @property PayoutMethod|null $payout_method
 * @property string|null $paypal_email
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_iban
 * @property string|null $notes
 * @property Carbon|null $approved_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Affiliate extends Authenticatable implements HasLocalePreference, HasPanelInbox
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<AffiliateFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable, ReceivesPanelNotifications {
        ReceivesPanelNotifications::notifications insteadof Notifiable;
    }
    use SendsPasswordResetLink;

    protected $table = 'affiliates';

    protected string $guard_name = 'affiliate';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status', 'locale', 'tenant_id',
        'code', 'type', 'commission_type', 'commission_value', 'balance', 'total_earned', 'total_paid',
        'payout_method', 'paypal_email', 'bank_name', 'bank_account_name', 'bank_iban', 'notes', 'approved_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => AffiliateType::class,
            'commission_type' => RateType::class,
            'commission_value' => 'integer',
            'balance' => 'integer',
            'total_earned' => 'integer',
            'total_paid' => 'integer',
            'payout_method' => PayoutMethod::class,
            'bank_iban' => 'encrypted',
            'approved_at' => 'datetime',
        ];
    }

    public function panelSurface(): string
    {
        return 'affiliate';
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function balanceMoney(): Money
    {
        return Money::ofMinor((int) $this->balance);
    }

    /**
     * @return HasMany<AffiliateClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    /**
     * @return HasMany<AffiliateConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    /**
     * @return HasMany<AffiliatePayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    /**
     * @return HasMany<AffiliatePayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(AffiliatePayoutRequest::class);
    }

    /**
     * @return HasMany<AffiliatePromoCode, $this>
     */
    public function promoCodes(): HasMany
    {
        return $this->hasMany(AffiliatePromoCode::class);
    }

    protected function passwordResetRoute(): string
    {
        return 'affiliate.password.reset';
    }

    protected function passwordBroker(): string
    {
        return 'affiliates';
    }
}
