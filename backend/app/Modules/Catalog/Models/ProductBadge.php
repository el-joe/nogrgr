<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Database\Factories\ProductBadgeFactory;
use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Modules\Catalog\Enums\BadgeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One row per BadgeType; products are assigned and ordered per badge (and country).
 *
 * @property int $id
 * @property BadgeType $type
 * @property string $name
 * @property bool $is_active
 * @property BadgeAutoFill $auto_fill
 * @property int $auto_fill_limit
 * @property int $sort_order
 */
class ProductBadge extends Model implements AiTranslatable
{
    use CentralConnection;

    /** @use HasFactory<ProductBadgeFactory> */
    use HasFactory;

    use HasTranslations;

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
    protected $fillable = ['type', 'name', 'is_active', 'auto_fill', 'auto_fill_limit', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => BadgeType::class, 'is_active' => 'boolean', 'auto_fill' => BadgeAutoFill::class, 'auto_fill_limit' => 'integer', 'sort_order' => 'integer'];
    }

    /**
     * @return HasMany<ProductBadgeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProductBadgeAssignment::class)->orderBy('sort_order')->orderBy('id');
    }
}
