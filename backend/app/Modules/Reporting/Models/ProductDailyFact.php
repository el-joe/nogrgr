<?php

namespace App\Modules\Reporting\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Daily product activity of one store product (views, add-to-cart, paid orders).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $store_product_id
 * @property Carbon $day
 * @property string|null $product_name
 * @property int $views
 * @property int $cart_adds
 * @property int $orders
 * @property int $units
 * @property int $revenue
 * @property int $profit
 */
class ProductDailyFact extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'store_product_id', 'day', 'product_name', 'views', 'cart_adds', 'orders', 'units', 'revenue', 'profit'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'store_product_id' => 'integer',
            'day' => 'date',
            'views' => 'integer',
            'cart_adds' => 'integer',
            'orders' => 'integer',
            'units' => 'integer',
            'revenue' => 'integer',
            'profit' => 'integer',
        ];
    }
}
