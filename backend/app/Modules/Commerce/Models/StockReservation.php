<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A stock hold of one order line (central; see StockReservationStatus).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $checkout_group
 * @property string $order_uuid
 * @property StoreProductSource $product_source
 * @property int $product_id
 * @property int $variant_key
 * @property int $quantity
 * @property StockReservationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockReservation extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'checkout_group', 'order_uuid', 'product_source', 'product_id', 'variant_key', 'quantity', 'status', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'product_source' => StoreProductSource::class,
            'product_id' => 'integer',
            'variant_key' => 'integer',
            'quantity' => 'integer',
            'status' => StockReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }
}
