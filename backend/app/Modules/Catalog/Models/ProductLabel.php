<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Product label: icon + translatable text that may contain placeholders such as
 * "Delivered by {delivery_date}" (resolved by Catalog\Services\LabelRenderer).
 *
 * @property int $id
 * @property string $text
 * @property string|null $icon
 * @property bool $is_active
 * @property int $sort_order
 */
class ProductLabel extends Model
{
    use CentralConnection;
    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['text'];

    /**
     * @var list<string>
     */
    protected $fillable = ['text', 'icon', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_label_product');
    }
}
