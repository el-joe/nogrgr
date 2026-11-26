<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store order of a product inside a category (tenant DB, decision #74).
 *
 * @property int $id
 * @property string $category_ref "central:<id>" | "store:<id>"
 * @property int $store_product_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreCategoryPosition extends Model
{
    protected $table = 'store_category_positions';

    /**
     * @var list<string>
     */
    protected $fillable = ['category_ref', 'store_product_id', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['store_product_id' => 'integer', 'position' => 'integer'];
    }
}
