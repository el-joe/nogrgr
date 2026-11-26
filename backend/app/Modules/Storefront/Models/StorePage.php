<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Storefront\Enums\StorePageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * Store CMS page (tenant DB, SF-ECO-034 / VEN-033): about, contact, terms, … (system,
 * seeded per store) or custom pages. Body is sanitised HTML per locale.
 *
 * @property int $id
 * @property string $slug
 * @property StorePageType $type
 * @property string $title
 * @property string|null $body
 * @property string|null $meta_description
 * @property bool $is_active
 * @property bool $show_in_footer
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorePage extends Model implements AiTranslatable
{
    use HasTranslations;

    protected $table = 'store_pages';

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body', 'meta_description'];

    /**
     * Fields the paid store translation run translates (decision #44).
     *
     * @return list<string>
     */
    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'type', 'title', 'body', 'meta_description', 'is_active', 'show_in_footer', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StorePageType::class,
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
