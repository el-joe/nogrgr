<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use Illuminate\Support\Str;

/**
 * Central website content (decision #113): blog categories + posts, FAQs and static
 * pages. Old texts live in the polymorphic `translations` table → `{locale: text}` JSON.
 * Pages are matched by slug, so a seeded system page (about, terms…) receives the old
 * content instead of being duplicated (its `is_system` flag is kept).
 */
class ContentStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Content;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        if ($context->source->hasTable($source, 'blog_categories')) {
            $this->blog($context);
        }

        if ($context->source->hasTable($source, 'faqs')) {
            $this->faqs($context);
        }

        if ($context->source->hasTable($source, 'static_pages')) {
            $this->pages($context);
        }
    }

    private function blog(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $categories = $source->table('blog_categories')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\BlogCategory', $categories->pluck('id')->map('intval')->all());

        $writer->transaction(function () use ($context, $writer, $categories, $texts): void {
            foreach ($categories as $index => $row) {
                $context->report->read('central', 'blog_categories');
                $id = (int) $row->id;
                $slug = Str::slug((string) $row->slug) ?: 'category-'.$id;
                $writer->upsert('blog_category', $id, 'blog_categories', [
                    'slug' => LegacyUnique::value($writer, 'blog_categories', 'slug', $slug, $writer->id('blog_category', $id), $id, 120),
                    'name' => LegacyTranslations::field($texts, $id, 'name', Str::headline($slug)),
                    'is_active' => in_array((string) $row->status, ['active', 'published'], true),
                    'sort_order' => $index,
                    'created_at' => $row->created_at,
                ]);
            }
        });

        if (! $context->source->hasTable($source, 'blog_posts')) {
            return;
        }

        $posts = $source->table('blog_posts')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\BlogPost', $posts->pluck('id')->map('intval')->all());

        $writer->transaction(function () use ($context, $writer, $posts, $texts): void {
            foreach ($posts as $row) {
                $context->report->read('central', 'blog_posts');
                $id = (int) $row->id;
                $slug = Str::slug((string) $row->slug) ?: 'post-'.$id;
                $body = LegacyTranslations::field($texts, $id, 'content') ?? ['en' => ''];
                $status = match ((string) $row->status) {
                    'published', 'active' => PostStatus::Published,
                    'archived' => PostStatus::Archived,
                    default => PostStatus::Draft,
                };
                $category = $writer->id('blog_category', $row->blog_category_id);
                $words = str_word_count(strip_tags((string) ($body['en'] ?? reset($body))));
                $writer->upsert('blog_post', $id, 'blog_posts', [
                    'blog_category_id' => $category !== null && $category > 0 ? $category : null,
                    'slug' => LegacyUnique::value($writer, 'blog_posts', 'slug', $slug, $writer->id('blog_post', $id), $id, 160),
                    'title' => LegacyTranslations::field($texts, $id, 'title', Str::headline($slug)),
                    'excerpt' => LegacyTranslations::field($texts, $id, 'excerpt'),
                    'body' => $body,
                    'status' => $status->value,
                    'published_at' => $row->published_at ?? ($status === PostStatus::Published ? $row->created_at : null),
                    'reading_minutes' => max(1, (int) ceil($words / 200)),
                    'created_at' => $row->created_at,
                ]);
            }
        });
    }

    private function faqs(LegacyContext $context): void
    {
        $writer = $context->central;
        $rows = $context->source->central()->table('faqs')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\Faq', $rows->pluck('id')->map('intval')->all());

        $writer->transaction(function () use ($context, $writer, $rows, $texts): void {
            foreach ($rows as $index => $row) {
                $context->report->read('central', 'faqs');
                $id = (int) $row->id;
                $category = Str::lower((string) ($texts[$id]['category']['en'] ?? ''));
                $writer->upsert('faq', $id, 'faqs', [
                    'group' => (FaqGroup::tryFrom($category) ?? FaqGroup::General)->value,
                    'question' => LegacyTranslations::field($texts, $id, 'question') ?? ['en' => 'FAQ '.$id],
                    'answer' => LegacyTranslations::field($texts, $id, 'answer') ?? ['en' => ''],
                    'is_active' => in_array((string) $row->status, ['active', 'published'], true),
                    'sort_order' => $index,
                    'created_at' => $row->created_at,
                ]);
            }
        });
    }

    private function pages(LegacyContext $context): void
    {
        $writer = $context->central;
        $rows = $context->source->central()->table('static_pages')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\StaticPage', $rows->pluck('id')->map('intval')->all());

        $writer->transaction(function () use ($context, $writer, $rows, $texts): void {
            foreach ($rows as $row) {
                $context->report->read('central', 'static_pages');
                $id = (int) $row->id;
                $slug = Str::limit(Str::slug((string) $row->slug) ?: 'page-'.$id, 120, '');
                $writer->upsert('page', $id, 'pages', [
                    'slug' => $slug,
                    'title' => LegacyTranslations::field($texts, $id, 'title', Str::headline($slug)),
                    'body' => LegacyTranslations::field($texts, $id, 'content') ?? ['en' => ''],
                    'target' => PageTarget::Central->value,
                    'is_compliance' => (bool) $row->is_compliance,
                    'status' => (match ((string) $row->status) {
                        'active', 'published' => PageStatus::Active,
                        'archived' => PageStatus::Archived,
                        default => PageStatus::Draft,
                    })->value,
                    'created_at' => $row->created_at,
                ], ['slug' => $slug]);
            }
        });
    }
}
