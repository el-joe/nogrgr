<?php

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Models\Page;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Central website starter content in ar + en (Database/Data/starter-content.php):
 * about / terms / privacy pages, FAQs, a blog category and 3 posts with covers
 * (resources/images/central/blog, copied to the public disk), plus the theme
 * catalogue. Idempotent: existing slugs are left as the admin edited them.
 */
class ContentStarterSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ThemeCatalogSeeder::class);

        /** @var array{pages: array<string, array<string, array<string, string>>>, faqs: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>, blog_category: array<string, mixed>, posts: list<array<string, mixed>>} $data */
        $data = require dirname(__DIR__).'/Data/starter-content.php';
        $app = (string) config('app.name');
        $fill = fn (array $translations): array => array_map(fn (string $text): string => str_replace(':app', $app, $text), $translations);

        foreach ($data['pages'] as $slug => $page) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $fill($page['title']),
                'body' => $fill($page['body']),
                'meta_title' => null,
                'meta_description' => $fill($page['meta_description']),
                'target' => PageTarget::Central,
                'is_compliance' => $slug !== 'about',
                'is_system' => true,
                'status' => PageStatus::Active,
            ]);
        }

        if (Faq::query()->doesntExist()) {
            foreach ($data['faqs'] as $index => [$group, $questionEn, $questionAr, $answerEn, $answerAr]) {
                Faq::query()->create([
                    'group' => FaqGroup::from($group),
                    'question' => ['en' => $questionEn, 'ar' => $questionAr],
                    'answer' => ['en' => '<p>'.e($answerEn).'</p>', 'ar' => '<p>'.e($answerAr).'</p>'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        $category = BlogCategory::query()->firstOrCreate(['slug' => $data['blog_category']['slug']], [
            'name' => $data['blog_category']['name'],
            'description' => $data['blog_category']['description'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        foreach ($data['posts'] as $post) {
            if (BlogPost::query()->where('slug', $post['slug'])->exists()) {
                continue;
            }

            BlogPost::query()->create([
                'blog_category_id' => $category->id,
                'slug' => $post['slug'],
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'body' => $fill($post['body']),
                'author_name' => $app,
                'status' => PostStatus::Published,
                'published_at' => now()->subDays((int) $post['days_ago'])->setTime(10, 0),
                'reading_minutes' => 4,
                ...$this->cover((string) $post['slug']),
            ]);
        }
    }

    /**
     * @return array{cover_path?: string, cover_webp_path?: string, cover_avif_path?: string}
     */
    private function cover(string $slug): array
    {
        $paths = [];

        foreach (['webp', 'avif'] as $format) {
            $source = resource_path("images/central/blog/{$slug}.{$format}");

            if (is_file($source)) {
                $target = BlogPost::COVER_DIRECTORY."/{$slug}.{$format}";
                Storage::disk(BlogPost::COVER_DISK)->put($target, (string) file_get_contents($source));
                $paths['cover_'.$format.'_path'] = $target;
            }
        }

        if (isset($paths['cover_webp_path'])) {
            $paths['cover_path'] = $paths['cover_webp_path'];
        }

        return $paths;
    }
}
