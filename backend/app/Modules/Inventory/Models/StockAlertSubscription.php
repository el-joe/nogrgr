<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockAlertSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Back-in-stock subscription of a store product (tenant DB). `notified_at` = the one
 * notice was sent; null = waiting for the stock to return.
 *
 * @property int $id
 * @property int $store_product_id
 * @property int|null $customer_id
 * @property string $email
 * @property string|null $locale
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockAlertSubscription extends Model
{
    /** @use HasFactory<StockAlertSubscriptionFactory> */
    use HasFactory;

    protected $table = 'stock_alert_subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_product_id', 'customer_id', 'email', 'locale', 'notified_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_product_id' => 'integer',
            'customer_id' => 'integer',
            'notified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StockAlertSubscriptionFactory
    {
        return StockAlertSubscriptionFactory::new();
    }
}
