<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central website blog category (ADM-080).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $posts_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BlogCategory extends Model
{
    use CentralConnection;

    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'blog_categories';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'name', 'description', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    protected static function newFactory(): BlogCategoryFactory
    {
        return BlogCategoryFactory::new();
    }
}
