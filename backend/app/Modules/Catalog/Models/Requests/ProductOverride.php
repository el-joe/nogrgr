<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Models\Product;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Approved per-tenant value of one product (or variant) field, written when an edit
 * request is approved. Tenants read override → fall back to the central value.
 * Central table (admin-owned, read-only to the vendor); `value` is text or JSON (images).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $variant_key
 * @property string $field
 * @property string $locale
 * @property string|null $value
 * @property string|null $central_value
 * @property int|null $edit_request_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class ProductOverride extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'catalog_product_overrides';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
