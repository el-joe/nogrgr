<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\Models\Category;

/**
 * Round category tiles of the Elora listing rail (Figma 1:17317): the category's own
 * image, else a photo of one of the products in it (its descendants included), so no tile
 * is left as a bare initial while the category has products.
 */
class CategoryTiles
{
    public function __construct(
        private readonly CategoryCatalog $categories,
        private readonly ProductIndex $index,
    ) {}

    /**
     * @param  list<Category>  $categories
     * @return list<array{category: Category, image: string|null}>
     */
    public function make(array $categories): array
    {
        $used = [];
        $tiles = [];

        foreach ($categories as $category) {
            $image = $category->thumbnailUrl();

            if ($image === null) {
                $ids = array_flip($this->categories->scopeIds($category));

                foreach ($this->index->entries() as $entry) {
                    if ($entry->image !== null && ! isset($used[$entry->image]) && array_intersect_key($ids, array_flip($entry->categoryIds)) !== []) {
                        $used[$image = $entry->image] = true;
                        break;
                    }
                }
            }

            $tiles[] = ['category' => $category, 'image' => $image];
        }

        return $tiles;
    }
}
