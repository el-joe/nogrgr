<?php

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Services\Media\ImageVariantConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the webp / avif derivatives of a category thumbnail after upload.
 * A thumbnail replaced meanwhile is ignored (the newer upload has its own job).
 */
class ConvertCategoryThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $categoryId) {}

    public function handle(CategoryRepositoryContract $categories, ImageVariantConverter $converter): void
    {
        /** @var Category|null $category */
        $category = $categories->find($this->categoryId);
        $path = $category?->thumbnail_path;

        if ($category === null || $path === null) {
            return;
        }

        $variants = $converter->convert(Category::THUMBNAIL_DISK, $path);

        if ($category->refresh()->thumbnail_path !== $path) {
            return;
        }

        $categories->update($category, [
            'thumbnail_webp_path' => $variants['webp'],
            'thumbnail_avif_path' => $variants['avif'],
        ]);
    }
}
