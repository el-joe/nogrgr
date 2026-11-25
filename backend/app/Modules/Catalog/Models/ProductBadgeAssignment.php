<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A product on a central badge list (product_badge_product). country_id 0 = every country.
 *
 * @property int $id
 * @property int $product_badge_id
 * @property int $product_id
 * @property int $country_id
 * @property int $sort_order
 * @property-read Product $product
 * @property-read ProductBadge $badge
 */
class ProductBadgeAssignment extends Model
{
    use CentralConnection;

    public const ALL_COUNTRIES = 0;

    protected $table = 'product_badge_product';

    /**
     * @var list<string>
     */
    protected $fillable = ['product_badge_id', 'product_id', 'country_id', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_badge_id' => 'integer', 'product_id' => 'integer', 'country_id' => 'integer', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBadge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(ProductBadge::class, 'product_badge_id');
    }
}
