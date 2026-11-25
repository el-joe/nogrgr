<?php

use App\Modules\Content\Database\Seeders\ContentStarterSeeder;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Jobs\ConvertBlogCover;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/ContentHelpers.php';

/*
| Admin › Website content (ADM-080 … 084): guests, permissions, CRUD with translations,
| SEO + publish / schedule, cover upload (webp / avif queued), system pages, FAQ sort,
| newsletter export / delete.
*/

dataset('content index pages', [
    'blog categories' => ['/admin/content/post-categories', 'content.post-categories.view'],
    'blog posts' => ['/admin/content/posts', 'content.posts.view'],
    'pages' => ['/admin/content/pages', 'content.pages.view'],
    'faqs' => ['/admin/content/faqs', 'content.faqs.view'],
    'newsletter' => ['/admin/content/newsletter', 'content.newsletter.view'],
]);

it('redirects guests and forbids admins without the permission', function (string $path, string $permission): void {
    $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(contentAdmin([]), 'admin');
    $this->getJson($this->centralUrl($path))->assertForbidden();
})->with('content index pages');

it('opens each list for an admin with view permission', function (string $path, string $permission): void {
    $this->seed(ContentStarterSeeder::class);
    $this->actingAs(contentAdmin([$permission]), 'admin');

    $this->get($this->centralUrl($path))->assertOk();
})->with('content index pages');

it('serves the server-side tables', function (): void {
    $this->seed(ContentStarterSeeder::class);
    $this->actingAs(contentAdmin(), 'admin');

    foreach (['post-categories', 'posts', 'pages', 'newsletter'] as $resource) {
        $this->getJson($this->centralUrl("/admin/content/{$resource}/data?draw=1&start=0&length=10"))->assertOk()->assertJsonStructure(['data', 'recordsTotal']);
    }

    $this->getJson($this->centralUrl('/admin/content/posts/data?draw=1&start=0&length=10&status=published'))->assertOk()->assertJsonPath('recordsFiltered', 3);
});

it('keeps view-only admins out of manage actions', function (): void {
    $post = BlogPost::factory()->create();
    $this->actingAs(contentAdmin(['content.posts.view']), 'admin');

    $this->getJson($this->centralUrl('/admin/content/posts/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/content/posts'), [])->assertForbidden();
    $this->postJson($this->centralUrl("/admin/content/posts/{$post->id}/publish"))->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/content/posts/{$post->id}"))->assertForbidden();
});

it('creates a blog post with translations, SEO, schedule and cover', function (): void {
    Storage::fake('public');
    Queue::fake();
    $category = BlogCategory::factory()->create();
    $this->actingAs(contentAdmin(['content.posts.manage']), 'admin');

    $this->get($this->centralUrl('/admin/content/posts/create'))->assertOk();

    $this->post($this->centralUrl('/admin/content/posts'), [
        'title' => ['en' => 'Grow with email marketing', 'ar' => 'نمِّ متجرك بالبريد'],
        'excerpt' => ['en' => 'Build a list and send campaigns.', 'ar' => 'ابنِ قائمة وأرسل حملات.'],
        'body' => ['en' => '<h2>Start</h2><p>Collect e-mails.</p><script>alert(1)</script>', 'ar' => '<p>اجمع العناوين.</p>'],
        'meta_title' => ['en' => 'Email marketing guide'],
        'meta_description' => ['en' => 'How to grow sales with e-mail.'],
        'slug' => '',
        'blog_category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'cover' => [UploadedFile::fake()->image('cover.jpg', 1200, 630)],
    ], ['Accept' => 'application/json'])->assertCreated();

    $post = BlogPost::query()->sole();
    expect($post->slug)->toBe('grow-with-email-marketing')
        ->and($post->getTranslation('body', 'en'))->not->toContain('<script')
        ->and($post->getTranslation('meta_title', 'en'))->toBe('Email marketing guide')
        ->and($post->isScheduled())->toBeTrue()
        ->and($post->cover_path)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $post->cover_path);
    Queue::assertPushed(ConvertBlogCover::class, fn (ConvertBlogCover $job): bool => $job->postId === $post->id);

    $this->get($this->centralUrl('/blog/grow-with-email-marketing'))->assertNotFound();
});

it('validates blog posts and keeps slugs unique', function (): void {
    BlogPost::factory()->create(['slug' => 'taken']);
    $this->actingAs(contentAdmin(['content.posts.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/content/posts'), ['title' => ['en' => ''], 'body' => ['en' => ''], 'slug' => 'taken', 'status' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title.en', 'body.en', 'slug', 'status']);
});

it('updates, publishes, archives and deletes a post', function (): void {
    $post = BlogPost::factory()->draft()->create();
    $this->actingAs(contentAdmin(['content.posts.manage']), 'admin');

    $this->get($this->centralUrl("/admin/content/posts/{$post->id}/edit"))->assertOk();
    $this->putJson($this->centralUrl("/admin/content/posts/{$post->id}"), [
        'title' => ['en' => 'Updated title', 'ar' => 'عنوان محدث'],
        'body' => ['en' => '<p>Body</p>'],
        'slug' => $post->slug,
        'status' => 'draft',
        'cover_keep' => [],
    ])->assertOk();
    expect($post->refresh()->title)->toBe('Updated title');

    $this->postJson($this->centralUrl("/admin/content/posts/{$post->id}/publish"))->assertOk();
    expect($post->refresh()->status)->toBe(PostStatus::Published)->and($post->published_at)->not->toBeNull();

    $this->postJson($this->centralUrl("/admin/content/posts/{$post->id}/archive"))->assertOk();
    expect($post->refresh()->status)->toBe(PostStatus::Archived);

    $this->deleteJson($this->centralUrl("/admin/content/posts/{$post->id}"))->assertOk();
    expect(BlogPost::query()->count())->toBe(0);
});

it('manages blog categories', function (): void {
    $this->actingAs(contentAdmin(['content.post-categories.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/content/post-categories'), ['name' => ['en' => '']])->assertUnprocessable()->assertJsonValidationErrors('name.en');
    $this->postJson($this->centralUrl('/admin/content/post-categories'), ['name' => ['en' => 'Marketing tips', 'ar' => 'نصائح التسويق'], 'is_active' => '1'])->assertCreated();

    $category = BlogCategory::query()->sole();
    expect($category->slug)->toBe('marketing-tips');

    $post = BlogPost::factory()->create(['blog_category_id' => $category->id]);
    $this->deleteJson($this->centralUrl("/admin/content/post-categories/{$category->id}"))->assertOk();
    expect($post->refresh()->blog_category_id)->toBeNull();
});

it('manages static pages and protects system pages', function (): void {
    $this->seed(ContentStarterSeeder::class);
    $this->actingAs(contentAdmin(['content.pages.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/content/pages'), [
        'title' => ['en' => 'Refund policy', 'ar' => 'سياسة الاسترداد'],
        'body' => ['en' => '<p>Refunds within 14 days.</p>', 'ar' => '<p>الاسترداد خلال 14 يومًا.</p>'],
        'slug' => '',
        'target' => 'both',
        'is_compliance' => '1',
        'status' => 'active',
    ])->assertCreated();

    $page = Page::query()->where('slug', 'refund-policy')->sole();
    expect($page->is_compliance)->toBeTrue();
    $this->get($this->centralUrl('/pages/refund-policy?lang=en'))->assertOk()->assertSee('Refunds within 14 days.');

    $this->postJson($this->centralUrl("/admin/content/pages/{$page->id}/archive"))->assertOk();
    expect($page->refresh()->status)->toBe(PageStatus::Archived);
    $this->get($this->centralUrl('/pages/refund-policy'))->assertNotFound();

    $terms = Page::query()->where('slug', 'terms')->sole();
    $this->putJson($this->centralUrl("/admin/content/pages/{$terms->id}"), [
        'title' => ['en' => 'Terms of service'], 'body' => ['en' => '<p>New terms.</p>'], 'slug' => 'renamed', 'target' => 'central', 'status' => 'active',
    ])->assertOk();
    expect($terms->refresh()->slug)->toBe('terms');
    $this->get($this->centralUrl('/terms?lang=en'))->assertSee('New terms.');

    $this->deleteJson($this->centralUrl("/admin/content/pages/{$terms->id}"))->assertUnprocessable();
    $this->deleteJson($this->centralUrl("/admin/content/pages/{$page->id}"))->assertOk();
});

it('manages and sorts FAQs', function (): void {
    $this->actingAs(contentAdmin(['content.faqs.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/content/faqs'), ['question' => ['en' => ''], 'group' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors(['question.en', 'answer.en', 'group']);
    $this->postJson($this->centralUrl('/admin/content/faqs'), ['question' => ['en' => 'Do you charge fees?', 'ar' => 'هل توجد رسوم؟'], 'answer' => ['en' => '<p>No hidden fees.</p>'], 'group' => 'billing', 'is_active' => '1'])->assertCreated();
    $second = Faq::factory()->create(['sort_order' => 5]);
    $first = Faq::query()->where('id', '!=', $second->id)->sole();

    $this->postJson($this->centralUrl('/admin/content/faqs/sort'), ['ids' => [$second->id, $first->id]])->assertOk();
    expect($second->refresh()->sort_order)->toBe(1)->and($first->refresh()->sort_order)->toBe(2);

    $this->get($this->centralUrl('/faqs?lang=en'))->assertSeeInOrder(['Can I use my own domain?', 'Do you charge fees?']);

    $this->deleteJson($this->centralUrl("/admin/content/faqs/{$first->id}"))->assertOk();
    expect(Faq::query()->count())->toBe(1);
});

it('exports and deletes newsletter subscribers', function (): void {
    $subscriber = NewsletterSubscriber::query()->create(['email' => 'reader@example.com', 'locale' => 'en', 'status' => SubscriberStatus::Subscribed, 'token' => str_repeat('b', 64)]);

    $this->actingAs(contentAdmin(['content.newsletter.view']), 'admin');
    $this->get($this->centralUrl('/admin/content/newsletter/export'))->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/content/newsletter/{$subscriber->id}"))->assertForbidden();

    $this->actingAs(contentAdmin(['content.newsletter.export', 'content.newsletter.manage']), 'admin');
    $export = $this->get($this->centralUrl('/admin/content/newsletter/export'))->assertOk();
    expect($export->streamedContent())->toContain('reader@example.com');

    $this->deleteJson($this->centralUrl("/admin/content/newsletter/{$subscriber->id}"))->assertOk();
    expect(NewsletterSubscriber::query()->count())->toBe(0);
});
