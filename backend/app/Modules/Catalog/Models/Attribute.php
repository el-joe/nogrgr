<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Database\Factories\AttributeFactory;
use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Variant attribute (old "variation"), e.g. Color, with options Red / Blue.
 *
 * @property int $id
 * @property string $name
 * @property AttributeDisplayType $display_type
 * @property RecordStatus $status
 * @property int $sort_order
 * @property string|null $external_source
 * @property int|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attribute extends Model implements AiTranslatable
{
    use CentralConnection;

    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'display_type', 'status', 'sort_order', 'external_source', 'external_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_type' => AttributeDisplayType::class,
            'status' => RecordStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<AttributeOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute');
    }
}
