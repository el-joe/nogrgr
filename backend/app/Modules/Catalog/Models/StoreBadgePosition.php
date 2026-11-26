<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store order of a product inside a badge (tenant DB, decision #74).
 *
 * @property int $id
 * @property int $badge_id central product_badges.id
 * @property int $store_product_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreBadgePosition extends Model
{
    protected $table = 'store_badge_positions';

    /**
     * @var list<string>
     */
    protected $fillable = ['badge_id', 'store_product_id', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['badge_id' => 'integer', 'store_product_id' => 'integer', 'position' => 'integer'];
    }
}
