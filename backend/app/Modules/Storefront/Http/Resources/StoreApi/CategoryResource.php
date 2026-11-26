<?php

namespace App\Modules\Storefront\Http\Resources\StoreApi;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront category (central or store-own, already filtered to the store's visible
 * ones in the store's order) with optional visible children.
 *
 * @property Category $resource
 */
class CategoryResource extends JsonResource
{
    /**
     * @param  list<Category>|null  $children
     */
    public function __construct(Category $resource, private readonly ?array $children = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => (string) $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->thumbnailUrl(),
            'image_webp' => $category->thumbnailWebpUrl(),
            'featured' => (bool) $category->is_featured,
            'show_in_menu' => (bool) $category->show_in_menu,
        ] + ($this->children !== null ? [
            'children' => array_map(static fn (Category $child): array => (new self($child))->toArray($request), $this->children),
        ] : []);
    }
}
