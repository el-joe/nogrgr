<?php

namespace App\Modules\Content\Jobs;

use App\Modules\Catalog\Services\Media\ImageVariantConverter;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the webp / avif derivatives of a blog cover after upload. A cover replaced
 * meanwhile is ignored (the newer upload has its own job).
 */
class ConvertBlogCover implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $postId) {}

    public function handle(BlogPostRepositoryContract $posts, ImageVariantConverter $converter): void
    {
        /** @var BlogPost|null $post */
        $post = $posts->find($this->postId);
        $path = $post?->cover_path;

        if ($post === null || $path === null) {
            return;
        }

        $variants = $converter->convert(BlogPost::COVER_DISK, $path);

        if ($post->refresh()->cover_path !== $path) {
            return;
        }

        $posts->update($post, ['cover_webp_path' => $variants['webp'], 'cover_avif_path' => $variants['avif']]);
    }
}
