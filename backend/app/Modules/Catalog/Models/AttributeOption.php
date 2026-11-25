<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $attribute_id
 * @property string $name
 * @property string|null $swatch
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $external_source
 * @property int|null $external_id
 * @property-read Attribute $attribute
 */
class AttributeOption extends Model
{
    use CentralConnection;

    /** @use HasFactory<AttributeOptionFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['attribute_id', 'name', 'swatch', 'is_active', 'sort_order', 'external_source', 'external_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'attribute_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option');
    }
}
