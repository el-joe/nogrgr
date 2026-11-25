<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ImportDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A source category seen by the importer and the central category it maps to
 * (null = unmapped: products in it are skipped until mapped).
 *
 * @property int $id
 * @property ImportDriver $driver
 * @property int $external_id
 * @property array<string, string> $name locale => source name (not a catalog translation)
 * @property int|null $parent_external_id
 * @property int|null $category_id
 * @property-read Category|null $category
 */
class CatalogImportCategoryMap extends Model
{
    use CentralConnection;

    protected $table = 'catalog_import_category_maps';

    /**
     * @var list<string>
     */
    protected $fillable = ['driver', 'external_id', 'name', 'parent_external_id', 'category_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => ImportDriver::class,
            'name' => 'array',
            'external_id' => 'integer',
            'parent_external_id' => 'integer',
            'category_id' => 'integer',
        ];
    }

    /**
     * Source name in the current locale, falling back to English / the first one.
     */
    public function label(): string
    {
        $names = is_array($this->name) ? $this->name : [];

        return (string) ($names[app()->getLocale()] ?? $names['en'] ?? array_values($names)[0] ?? '#'.$this->external_id);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
