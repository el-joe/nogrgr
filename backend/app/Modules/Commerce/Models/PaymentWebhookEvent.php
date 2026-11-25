<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A processed storefront gateway webhook (replay protection: unique gateway + event id).
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property string|null $checkout_id
 * @property Carbon|null $processed_at
 */
class PaymentWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['gateway', 'event_id', 'type', 'checkout_id', 'processed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
