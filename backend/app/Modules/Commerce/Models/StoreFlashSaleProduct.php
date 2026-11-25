<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Enums\StoreProductSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Product of a store flash sale: (central, central product id) or (own, store product id).
 *
 * @property int $store_flash_sale_id
 * @property StoreProductSource $product_source
 * @property int $product_id
 */
class StoreFlashSaleProduct extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'store_flash_sale_id';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_flash_sale_id', 'product_source', 'product_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_source' => StoreProductSource::class, 'product_id' => 'integer'];
    }
}
