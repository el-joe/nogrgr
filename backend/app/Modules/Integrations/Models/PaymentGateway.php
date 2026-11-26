<?php

namespace App\Modules\Integrations\Models;

use App\Modules\Integrations\Database\Factories\PaymentGatewayFactory;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central payment gateway (ADM-100a/b). Fee = `fee_percent_bps` (100 = 1 %) of the
 * amount + `fee_fixed_minor` USD cents. Configs per GatewayPurpose.
 *
 * @property int $id
 * @property PaymentGatewayCode $code
 * @property bool $is_active
 * @property int $fee_percent_bps
 * @property int $fee_fixed_minor
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PaymentGatewayConfig> $configs
 */
class PaymentGateway extends Model
{
    use CentralConnection;

    /** @use HasFactory<PaymentGatewayFactory> */
    use HasFactory;

    protected $table = 'payment_gateways';

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'is_active', 'fee_percent_bps', 'fee_fixed_minor', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => PaymentGatewayCode::class,
            'is_active' => 'boolean',
            'fee_percent_bps' => 'integer',
            'fee_fixed_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): PaymentGatewayFactory
    {
        return PaymentGatewayFactory::new();
    }

    /**
     * @return HasMany<PaymentGatewayConfig, $this>
     */
    public function configs(): HasMany
    {
        return $this->hasMany(PaymentGatewayConfig::class);
    }

    /**
     * Whether the gateway may be switched on: offline methods always, online ones once a
     * saved config (vendor payments or tenant registration) is enabled. QA-P5-014.
     */
    public function canBeActivated(): bool
    {
        return $this->code->isOffline() || $this->configs->contains(static fn (PaymentGatewayConfig $config): bool => (bool) $config->is_enabled);
    }

    public function config(GatewayPurpose $purpose): ?PaymentGatewayConfig
    {
        return $this->configs->first(static fn (PaymentGatewayConfig $config): bool => $config->purpose === $purpose);
    }

    public function feeFixed(): Money
    {
        return Money::ofMinor($this->fee_fixed_minor);
    }

    /**
     * Platform fee on a USD amount: percentage (bps) + fixed, rounded like Money.
     */
    public function feeFor(Money $amount): Money
    {
        return $amount->multiply($this->fee_percent_bps / 10000)->add($this->feeFixed());
    }
}
