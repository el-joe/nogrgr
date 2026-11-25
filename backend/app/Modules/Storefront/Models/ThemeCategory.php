<?php

namespace App\Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Templates gallery category (Fashion, Beauty…). Icons ship as
 * resources/images/central/categories/{slug}.svg.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property int|null $themes_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ThemeCategory extends Model
{
    use CentralConnection;
    use HasTranslations;

    protected $table = 'theme_categories';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'name', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Theme, $this>
     */
    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'theme_category_theme');
    }
}
