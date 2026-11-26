<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The store's visibility / position of one CENTRAL category (tenant DB, decision #74).
 * No row = visible, ordered after the positioned categories.
 *
 * @property int $id
 * @property int $central_category_id
 * @property bool $is_hidden
 * @property int|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreCentralCategory extends Model
{
    protected $table = 'store_central_categories';

    /**
     * @var list<string>
     */
    protected $fillable = ['central_category_id', 'is_hidden', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['central_category_id' => 'integer', 'is_hidden' => 'boolean', 'sort_order' => 'integer'];
    }
}
