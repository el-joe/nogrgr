<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\StoreLayoutScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Page-builder layout of one slot (tenant DB, VEN-032): "home", "home:{country}" or
 * "page:{store page}". `draft` = working copy, `published` = what the storefront, the
 * Store API and custom Twig themes render; both are lists of section items
 * {key, type?, enabled, settings, countries}.
 *
 * @property int $id
 * @property string $slot
 * @property StoreLayoutScope $scope
 * @property int|null $country_id
 * @property int|null $page_id
 * @property int|null $theme_id
 * @property list<array<string, mixed>>|null $draft
 * @property list<array<string, mixed>>|null $published
 * @property int|null $published_version_id
 * @property Carbon|null $draft_updated_at
 * @property Carbon|null $published_at
 */
class StoreLayout extends Model
{
    protected $table = 'store_layouts';

    /**
     * @var list<string>
     */
    protected $fillable = ['slot', 'scope', 'country_id', 'page_id', 'theme_id', 'draft', 'published', 'published_version_id', 'draft_updated_at', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => StoreLayoutScope::class,
            'country_id' => 'integer',
            'page_id' => 'integer',
            'theme_id' => 'integer',
            'draft' => 'array',
            'published' => 'array',
            'published_version_id' => 'integer',
            'draft_updated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StoreLayoutVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(StoreLayoutVersion::class)->orderByDesc('number');
    }

    /**
     * The draft differs from what is published (or nothing is published yet).
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->draft !== null && $this->draft !== $this->published;
    }

    public static function homeSlot(?int $countryId): string
    {
        return $countryId === null ? 'home' : 'home:'.$countryId;
    }

    public static function pageSlot(int $pageId): string
    {
        return 'page:'.$pageId;
    }
}
