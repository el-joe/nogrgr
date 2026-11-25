<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\CheckoutAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Idempotency record of one place-order submit: the same key returns the same result.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property int|null $cart_id
 * @property int|null $customer_id
 * @property string $request_hash
 * @property CheckoutAttemptStatus $status
 * @property string|null $checkout_group
 * @property array<string, mixed>|null $response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CheckoutAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['idempotency_key', 'cart_id', 'customer_id', 'request_hash', 'status', 'checkout_group', 'response'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckoutAttemptStatus::class,
            'cart_id' => 'integer',
            'customer_id' => 'integer',
            'response' => 'array',
        ];
    }
}
