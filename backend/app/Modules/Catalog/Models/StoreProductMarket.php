<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\MarketPlacement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One market (country) a store product is promoted in, with its placements (tenant DB).
 *
 * @property int $id
 * @property int $store_product_id
 * @property int $country_id
 * @property list<string>|null $placements
 */
class StoreProductMarket extends Model
{
    protected $table = 'store_product_markets';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_product_id', 'country_id', 'placements'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['country_id' => 'integer', 'placements' => 'array'];
    }

    public function has(MarketPlacement $placement): bool
    {
        return in_array($placement->value, $this->placements ?? [], true);
    }

    /**
     * @return BelongsTo<StoreProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }
}
