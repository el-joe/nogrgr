<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductLabelFactory;
use App\Modules\Catalog\Enums\LabelPlaceholder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property list<int>|null $country_ids
 * @property bool $is_active
 * @property int $sort_order
 */
class ProductLabel extends Model
{
    use CentralConnection;

    /** @use HasFactory<ProductLabelFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['text'];

    /**
     * @var list<string>
     */
    protected $fillable = ['text', 'icon', 'country_ids', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['country_ids' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_label_product');
    }

    /**
     * Placeholders used in any locale of the text.
     *
     * @return list<LabelPlaceholder>
     */
    public function placeholders(): array
    {
        $text = implode(' ', array_filter($this->getTranslations('text'), 'is_string'));

        return array_values(array_filter(LabelPlaceholder::cases(), static fn (LabelPlaceholder $placeholder): bool => str_contains($text, $placeholder->token())));
    }
}
