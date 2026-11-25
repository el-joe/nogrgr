<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\DTOs\BlogCategoryData;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Blog categories (ADM-080 / 080a). Deleting a category keeps its posts (uncategorised).
 */
class BlogCategoryService extends BaseService
{
    public function __construct(private readonly BlogCategoryRepositoryContract $categories) {}

    public function create(BlogCategoryData $data): BlogCategory
    {
        /** @var BlogCategory $category */
        $category = $this->categories->create($data->attributes());

        return $category;
    }

    public function update(BlogCategory $category, BlogCategoryData $data): BlogCategory
    {
        /** @var BlogCategory $category */
        $category = $this->categories->update($category, $data->attributes());

        return $category;
    }

    public function delete(BlogCategory $category): void
    {
        $this->categories->delete($category);
    }
}
