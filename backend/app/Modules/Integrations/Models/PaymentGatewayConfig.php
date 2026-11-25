<?php

namespace App\Modules\Integrations\Models;

use App\Modules\Integrations\Enums\ConnectionStatus;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One purpose of a gateway: enabled switch, test/live mode, encrypted credentials
 * (`encrypted:array`, hidden from serialisation), settlement currencies and the
 * last connection test.
 *
 * @property int $id
 * @property int $payment_gateway_id
 * @property GatewayPurpose $purpose
 * @property bool $is_enabled
 * @property GatewayMode $mode
 * @property array<string, string>|null $credentials
 * @property list<string>|null $currencies
 * @property Carbon|null $verified_at
 * @property string|null $last_error
 * @property Carbon|null $last_tested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PaymentGateway $gateway
 */
class PaymentGatewayConfig extends Model
{
    use CentralConnection;

    protected $table = 'payment_gateway_configs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_gateway_id', 'purpose', 'is_enabled', 'mode', 'credentials', 'currencies',
        'verified_at', 'last_error', 'last_tested_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => GatewayPurpose::class,
            'is_enabled' => 'boolean',
            'mode' => GatewayMode::class,
            'credentials' => 'encrypted:array',
            'currencies' => 'array',
            'verified_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function status(): ConnectionStatus
    {
        return match (true) {
            $this->last_tested_at === null => ($this->credentials ?? []) === [] ? ConnectionStatus::NotConfigured : ConnectionStatus::NotTested,
            $this->last_error !== null => ConnectionStatus::Failed,
            default => ConnectionStatus::Connected,
        };
    }
}
