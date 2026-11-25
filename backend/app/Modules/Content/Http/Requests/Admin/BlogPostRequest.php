<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\DTOs\BlogPostData;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Http\Requests\Admin\Concerns\TranslatedContent;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Blog post create / edit (ADM-081a): translations + SEO, slug, category, author,
 * status + publish date, cover through x-ui::image-upload (cover[] new file,
 * cover_keep[] = "current").
 */
class BlogPostRequest extends FormRequest
{
    use TranslatedContent;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslated(['body']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            ...$this->translatedRules([
                'title' => [true, ['max:200']],
                'excerpt' => [false, ['max:400']],
                'body' => [true, ['max:100000']],
                'meta_title' => [false, ['max:70']],
                'meta_description' => [false, ['max:170']],
            ]),
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(BlogPost::class, 'slug')->ignore($post instanceof BlogPost ? $post->id : null)],
            'blog_category_id' => ['nullable', 'integer', Rule::exists(BlogCategory::class, 'id')],
            'author_name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'array', 'max:1'],
            'cover.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600,min_height=315'],
            'cover_keep' => ['nullable', 'array', 'max:1'],
            'cover_keep.*' => ['string', 'in:current'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.en' => __('content::admin.posts.fields.title'),
            'body.en' => __('content::admin.posts.fields.body'),
            'slug' => __('content::admin.common.slug'),
            'blog_category_id' => __('content::admin.posts.fields.category'),
            'status' => __('content::admin.posts.fields.status'),
            'published_at' => __('content::admin.posts.fields.published_at'),
            'cover' => __('content::admin.posts.fields.cover'),
            'cover.*' => __('content::admin.posts.fields.cover'),
        ];
    }

    public function toData(): BlogPostData
    {
        $file = $this->file('cover.0');
        $publishedAt = $this->validated('published_at');

        return new BlogPostData(
            slug: (string) $this->validated('slug'),
            categoryId: $this->validated('blog_category_id') !== null ? (int) $this->validated('blog_category_id') : null,
            title: $this->translations('title'),
            excerpt: $this->translations('excerpt'),
            body: $this->translations('body'),
            metaTitle: $this->translations('meta_title'),
            metaDescription: $this->translations('meta_description'),
            authorName: $this->validated('author_name') ?: null,
            status: PostStatus::from((string) $this->validated('status')),
            publishedAt: is_string($publishedAt) && $publishedAt !== '' ? Carbon::parse($publishedAt) : null,
            cover: $file instanceof UploadedFile ? $file : null,
            removeCover: ! $file instanceof UploadedFile && ! in_array('current', (array) $this->validated('cover_keep', []), true),
        );
    }
}
