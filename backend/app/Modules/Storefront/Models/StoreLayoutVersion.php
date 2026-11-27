<?php

namespace App\Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One published version of a page-builder layout (tenant DB, VEN-032).
 *
 * @property int $id
 * @property int $store_layout_id
 * @property int $number
 * @property int|null $theme_id
 * @property list<array<string, mixed>> $sections
 * @property int|null $published_by
 * @property string|null $published_by_name
 * @property Carbon|null $created_at
 */
class StoreLayoutVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'store_layout_versions';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_layout_id', 'number', 'theme_id', 'sections', 'published_by', 'published_by_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'theme_id' => 'integer',
            'sections' => 'array',
            'published_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StoreLayout, $this>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(StoreLayout::class, 'store_layout_id');
    }
}
