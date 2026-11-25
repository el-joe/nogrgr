<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Payment log of one storefront payment attempt (tenant DB). One attempt pays every
 * order of a checkout group (a split cart is charged once).
 *
 * @property int $id
 * @property string $reference
 * @property string $checkout_group
 * @property PaymentGatewayCode $gateway
 * @property PaymentSource $source
 * @property GatewayMode $mode
 * @property PaymentAttemptStatus $status
 * @property int $amount
 * @property string $currency
 * @property string|null $checkout_id
 * @property string|null $transaction_id
 * @property string|null $redirect_url
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $details
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderPayment extends Model
{
    /**
     * Explicit storage format: admin pages read store orders after leaving the store's
     * tenant context, where the "tenant" connection (default date format) is gone.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference', 'checkout_group', 'gateway', 'source', 'mode', 'status', 'amount', 'currency', 'checkout_id',
        'transaction_id', 'redirect_url', 'failure_reason', 'details', 'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGatewayCode::class,
            'source' => PaymentSource::class,
            'mode' => GatewayMode::class,
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'details' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function amountMoney(): Money
    {
        return Money::ofMinor($this->amount, $this->currency);
    }
}
