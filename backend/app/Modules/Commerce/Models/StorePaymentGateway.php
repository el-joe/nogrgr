<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A store's storefront gateway row (tenant DB, old tenant payment_gateways of type
 * "orders"): active / hidden switches and, with use_own, the store's own encrypted keys,
 * mode and connection status. Without use_own the platform's central credentials apply.
 *
 * @property int $id
 * @property PaymentGatewayCode $code
 * @property bool $is_active
 * @property bool $is_hidden
 * @property bool $use_own
 * @property bool $is_primary
 * @property GatewayMode $mode
 * @property array<string, string>|null $credentials
 * @property GatewayConnection $connection_status
 * @property Carbon|null $last_tested_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorePaymentGateway extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'is_active', 'is_hidden', 'use_own', 'mode', 'credentials', 'connection_status', 'last_tested_at', 'last_error', 'is_primary'];

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
            'code' => PaymentGatewayCode::class,
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
            'use_own' => 'boolean',
            'is_primary' => 'boolean',
            'mode' => GatewayMode::class,
            'credentials' => 'encrypted:array',
            'connection_status' => GatewayConnection::class,
            'last_tested_at' => 'datetime',
        ];
    }
}
