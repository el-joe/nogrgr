<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreCategory;

/**
 * A store-own category (decision #74) shown on the storefront next to the central ones
 * (decision #79). It is a read-only, never-saved Category so every storefront view,
 * listing and facet handles both kinds alike: id = −(store category id), parent = the
 * negated store parent, image on the tenant `public` disk.
 */
final class StoreOwnCategory extends Category
{
    private ?string $ownImageUrl = null;

    public static function fromStore(StoreCategory $own, string $slug): self
    {
        $category = new self;
        $category->setRawAttributes([
            'id' => self::idFor($own->id),
            'parent_id' => $own->parent_id !== null ? self::idFor($own->parent_id) : null,
            'slug' => $slug,
            'status' => RecordStatus::Active->value,
            'is_featured' => $own->is_featured,
            'show_in_menu' => true,
            'sort_order' => $own->sort_order,
        ], true);

        foreach (['name', 'description', 'meta_description'] as $field) {
            $category->setTranslations($field, array_filter($own->getTranslations($field), static fn (mixed $value): bool => is_string($value) && $value !== ''));
        }

        $category->exists = false;
        $category->ownImageUrl = $own->imageUrl();

        return $category;
    }

    /**
     * Storefront id of a store category id (negative, never collides with central ids).
     */
    public static function idFor(int $storeCategoryId): int
    {
        return -abs($storeCategoryId);
    }

    public function storeCategoryId(): int
    {
        return abs((int) $this->id);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->ownImageUrl;
    }

    public function thumbnailWebpUrl(): ?string
    {
        return null;
    }

    public function thumbnailAvifUrl(): ?string
    {
        return null;
    }

    /**
     * Never persisted (it mirrors a tenant `store_categories` row).
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return false;
    }
}
