<?php

namespace App\Modules\Content\DTOs;

use App\Modules\Content\Enums\PostStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Blog post form (ADM-081a): translations (title, excerpt, body, SEO), slug, category,
 * status + publish date (future = scheduled), cover upload / removal.
 */
final class BlogPostData extends BaseDTO
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $excerpt
     * @param  array<string, string>  $body  sanitised HTML
     * @param  array<string, string>  $metaTitle
     * @param  array<string, string>  $metaDescription
     */
    public function __construct(
        public readonly string $slug,
        public readonly ?int $categoryId,
        public readonly array $title,
        public readonly array $excerpt,
        public readonly array $body,
        public readonly array $metaTitle,
        public readonly array $metaDescription,
        public readonly ?string $authorName,
        public readonly PostStatus $status,
        public readonly ?Carbon $publishedAt,
        public readonly ?UploadedFile $cover,
        public readonly bool $removeCover,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $words = 0;

        foreach ($this->body as $html) {
            $words = max($words, count(preg_split('/\s+/u', trim(strip_tags($html)), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        }

        return [
            'slug' => $this->slug,
            'blog_category_id' => $this->categoryId,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'author_name' => $this->authorName,
            'status' => $this->status,
            'published_at' => $this->status === PostStatus::Published ? ($this->publishedAt ?? now()) : $this->publishedAt,
            'reading_minutes' => max(1, (int) ceil($words / 200)),
        ];
    }
}
