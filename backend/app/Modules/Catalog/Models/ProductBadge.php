<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\BadgeType;
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
 * @property int $sort_order
 */
class ProductBadge extends Model
{
    use CentralConnection;
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'name', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => BadgeType::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return HasMany<ProductBadgeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProductBadgeAssignment::class)->orderBy('sort_order')->orderBy('id');
    }
}
