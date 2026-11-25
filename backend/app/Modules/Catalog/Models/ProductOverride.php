<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * The current store's overrides of one CENTRAL product (tenant DB, decision #65): per
 * field, null = use the central value. Written ONLY by approved edit requests (ADM-018b);
 * the vendor never writes it directly. `approvals`: "field[:locale]" =>
 * {edit_request_id, approved_by, approved_at, central_value}.
 *
 * @property int $id
 * @property int $product_id central products.id
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property list<string>|null $images paths on the central public disk
 * @property int|null $weight_grams
 * @property array<string, array<string, mixed>>|null $approvals
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductOverride extends Model implements AiTranslatable
{
    use HasTranslations;

    protected $table = 'product_overrides';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'summary', 'description', 'meta_title', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id', 'name', 'summary', 'description', 'meta_title', 'meta_description', 'images',
        'weight_grams', 'approvals',
    ];

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
            'images' => 'array',
            'weight_grams' => 'integer',
            'approvals' => 'array',
        ];
    }
}
