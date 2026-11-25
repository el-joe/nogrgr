<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Blog list (WEB-013, Figma 127:11911: category filter, search, pagination, empty) and
 * post (WEB-014, 127:12209: related posts, share links, Article JSON-LD).
 */
class BlogController extends Controller
{
    public const PER_PAGE = 9;

    public function __construct(
        private readonly BlogPostRepositoryContract $posts,
        private readonly BlogCategoryRepositoryContract $categories,
        private readonly CentralSeo $seo,
    ) {}

    public function index(Request $request): View
    {
        $slug = $request->query('category');
        $category = is_string($slug) && $slug !== '' ? $this->categories->findActiveBySlug($slug) : null;
        $search = is_string($request->query('q')) ? Str::limit(trim((string) $request->query('q')), 100, '') : null;
        $search = $search === '' ? null : $search;

        $title = $category !== null
            ? __('content::central.seo.blog.category_title', ['category' => $category->name])
            : __('content::central.seo.blog.title');

        return view('content::central.blog.index', [
            'seo' => $this->seo->make(
                $title,
                __('content::central.seo.blog.description'),
                [['name' => __('content::central.seo.blog.title'), 'url' => $category !== null ? route('central.blog.index') : null], ...($category !== null ? [['name' => $category->name, 'url' => null]] : [])],
                noindex: $search !== null,
            ),
            'posts' => $this->posts->publicPaginate($category, $search, self::PER_PAGE),
            'categories' => $this->categories->activeWithCounts(),
            'activeCategory' => $category,
            'search' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        $post = $this->posts->findVisibleBySlug($slug);
        abort_if($post === null, 404);

        $url = $post->url();
        $cover = $post->coverSources();
        $image = $cover['webp'] ?? $cover['src'];
        $description = $post->meta_description ?: ($post->excerpt ?: (string) $post->body);

        $article = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => Str::limit(trim(strip_tags($description)), 200),
            'image' => $image,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'inLanguage' => app()->getLocale(),
            'mainEntityOfPage' => $url,
            'author' => ['@type' => $post->author_name ? 'Person' : 'Organization', 'name' => $post->author_name ?: config('app.name')],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name')],
        ]);

        $crumbs = [['name' => __('content::central.seo.blog.title'), 'url' => route('central.blog.index')]];

        if ($post->category !== null) {
            $crumbs[] = ['name' => $post->category->name, 'url' => route('central.blog.index', ['category' => $post->category->slug])];
        }

        $crumbs[] = ['name' => $post->title, 'url' => null];
        $encoded = rawurlencode($url);
        $text = rawurlencode($post->title);

        return view('content::central.blog.show', [
            'seo' => $this->seo->make(
                $post->meta_title ?: $post->title,
                $description,
                $crumbs,
                [$article],
                image: $image,
                type: 'article',
                publishedTime: $post->published_at?->toAtomString(),
                modifiedTime: $post->updated_at?->toAtomString(),
            ),
            'post' => $post,
            'related' => $this->posts->related($post, 3),
            'shareLinks' => [
                'x' => "https://twitter.com/intent/tweet?url={$encoded}&text={$text}",
                'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encoded}",
                'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encoded}",
                'whatsapp' => "https://wa.me/?text={$text}%20{$encoded}",
            ],
        ]);
    }
}
