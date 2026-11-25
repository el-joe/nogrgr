<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\DTOs\BlogPostData;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Jobs\ConvertBlogCover;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Storage;

/**
 * Blog posts (ADM-081 / 081a): CRUD, publish (now or scheduled), archive, and the
 * cover upload (original on the public disk + queued webp / avif conversion).
 */
class BlogPostService extends BaseService
{
    public function __construct(private readonly BlogPostRepositoryContract $posts) {}

    public function create(BlogPostData $data): BlogPost
    {
        $attributes = $data->attributes();

        if ($data->cover !== null) {
            $attributes['cover_path'] = $data->cover->store(BlogPost::COVER_DIRECTORY, BlogPost::COVER_DISK) ?: null;
        }

        /** @var BlogPost $post */
        $post = $this->posts->create($attributes);
        $this->queueConversion($post);

        return $post;
    }

    public function update(BlogPost $post, BlogPostData $data): BlogPost
    {
        $attributes = $data->attributes();
        $previous = array_filter([$post->cover_path, $post->cover_webp_path, $post->cover_avif_path]);
        $replaced = false;

        if ($data->cover !== null) {
            $attributes['cover_path'] = $data->cover->store(BlogPost::COVER_DIRECTORY, BlogPost::COVER_DISK) ?: null;
            $attributes['cover_webp_path'] = $attributes['cover_avif_path'] = null;
            $replaced = true;
        } elseif ($data->removeCover) {
            $attributes['cover_path'] = $attributes['cover_webp_path'] = $attributes['cover_avif_path'] = null;
            $replaced = true;
        }

        /** @var BlogPost $post */
        $post = $this->posts->update($post, $attributes);

        if ($replaced && $previous !== []) {
            Storage::disk(BlogPost::COVER_DISK)->delete($previous);
        }

        if ($data->cover !== null) {
            $this->queueConversion($post);
        }

        return $post;
    }

    public function publish(BlogPost $post): BlogPost
    {
        /** @var BlogPost $post */
        $post = $this->posts->update($post, [
            'status' => PostStatus::Published,
            'published_at' => $post->published_at ?? now(),
        ]);

        return $post;
    }

    public function archive(BlogPost $post): BlogPost
    {
        /** @var BlogPost $post */
        $post = $this->posts->update($post, ['status' => PostStatus::Archived]);

        return $post;
    }

    public function delete(BlogPost $post): void
    {
        $files = array_filter([$post->cover_path, $post->cover_webp_path, $post->cover_avif_path]);
        $this->posts->delete($post);

        if ($files !== []) {
            Storage::disk(BlogPost::COVER_DISK)->delete($files);
        }
    }

    private function queueConversion(BlogPost $post): void
    {
        if ($post->cover_path !== null) {
            ConvertBlogCover::dispatch($post->id)->afterCommit();
        }
    }
}
