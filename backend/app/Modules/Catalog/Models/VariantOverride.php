<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * The current store's overrides of one CENTRAL variant (tenant DB, decision #65): null
 * fields (and locales missing from `title`) fall back to the central variant. Written only
 * by approved edit requests and by AI translation runs (target locales only).
 *
 * @property int $id
 * @property int $product_id central products.id
 * @property int $variant_id central product_variants.id
 * @property string|null $title
 * @property list<string>|null $images
 * @property int|null $weight_grams
 * @property array<string, array<string, mixed>>|null $approvals
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VariantOverride extends Model implements AiTranslatable
{
    use HasTranslations;

    protected $table = 'variant_overrides';

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = ['product_id', 'variant_id', 'title', 'images', 'weight_grams', 'approvals'];

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'images' => 'array',
            'weight_grams' => 'integer',
            'approvals' => 'array',
        ];
    }
}
