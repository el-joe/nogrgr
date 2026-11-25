<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Content\Database\Seeders\ContentStarterSeeder;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Models\Page;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
| Central website public pages (WEB-001 … 014, 025 … 029): 200 + locale + SEO head,
| starter content, visibility rules (drafts, scheduled, inactive pages), sitemap, robots,
| locale switch and the central error pages.
*/

beforeEach(function (): void {
    $this->seed(ContentStarterSeeder::class);
});

dataset('central pages', [
    'home' => ['/'],
    'templates' => ['/templates'],
    'templates by category' => ['/templates/category/fashion'],
    'pricing' => ['/pricing'],
    'how it works' => ['/how-it-works'],
    'about' => ['/about'],
    'contact' => ['/contact'],
    'faqs' => ['/faqs'],
    'terms' => ['/terms'],
    'privacy' => ['/privacy'],
    'blog' => ['/blog'],
    'blog post' => ['/blog/launch-your-online-store'],
    'login' => ['/login'],
    'forgot password' => ['/forgot-password'],
]);

it('renders every central page in English with SEO tags', function (string $path): void {
    $response = $this->get($this->centralUrl($path).'?lang=en')->assertOk();

    $response->assertSeeInOrder(['<html', 'lang="en"', 'dir="ltr"', '<head>'], false)
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('"@type":"Organization"', false);
})->with('central pages');

it('renders every central page in Arabic right to left', function (string $path): void {
    $this->get($this->centralUrl($path).'?lang=ar')
        ->assertOk()
        ->assertSeeInOrder(['<html', 'lang="ar"', 'dir="rtl"', '<head>'], false);
})->with('central pages');

it('shows active plans with sign-up links on the pricing page', function (): void {
    $plan = Plan::factory()->create(['name' => ['en' => 'Growth Plus', 'ar' => 'النمو بلس'], 'trial_days' => 14]);

    $this->get($this->centralUrl('/pricing?lang=en&term=yearly'))
        ->assertOk()
        ->assertSee('Growth Plus')
        ->assertSee(e(route('central.register', ['plan' => $plan->id, 'term' => 'yearly'])), false);
});

it('lists published posts only and outputs Article and FAQ JSON-LD', function (): void {
    BlogPost::factory()->draft()->create(['slug' => 'hidden-draft', 'title' => ['en' => 'Hidden draft', 'ar' => 'مسودة']]);
    BlogPost::factory()->scheduled()->create(['slug' => 'future-post', 'title' => ['en' => 'Future post', 'ar' => 'لاحق']]);

    $this->get($this->centralUrl('/blog?lang=en'))
        ->assertOk()
        ->assertSee('How to launch your online store in one day')
        ->assertDontSee('Hidden draft')
        ->assertDontSee('Future post');

    $this->get($this->centralUrl('/blog/hidden-draft'))->assertNotFound();
    $this->get($this->centralUrl('/blog/future-post'))->assertNotFound();

    $this->get($this->centralUrl('/blog/launch-your-online-store?lang=en'))
        ->assertSee('"@type":"BlogPosting"', false)
        ->assertSee('"@type":"BreadcrumbList"', false);

    $this->get($this->centralUrl('/faqs?lang=en'))->assertSee('"@type":"FAQPage"', false);
});

it('filters the blog by category and search', function (): void {
    $category = BlogCategory::factory()->create(['slug' => 'marketing', 'name' => ['en' => 'Marketing', 'ar' => 'التسويق']]);
    BlogPost::factory()->create(['slug' => 'email-campaigns', 'blog_category_id' => $category->id, 'title' => ['en' => 'Email campaigns that convert', 'ar' => 'حملات البريد']]);

    $this->get($this->centralUrl('/blog?lang=en&category=marketing'))
        ->assertOk()
        ->assertSee('Email campaigns that convert')
        ->assertDontSee('How to launch your online store in one day');

    $this->get($this->centralUrl('/blog?lang=en&q=campaigns'))
        ->assertOk()
        ->assertSee('Email campaigns that convert')
        ->assertDontSee('Product photos that sell');
});

it('serves active central static pages and 404s inactive or store-only ones', function (): void {
    Page::factory()->create(['slug' => 'shipping-policy', 'title' => ['en' => 'Shipping policy', 'ar' => 'سياسة الشحن']]);
    Page::factory()->create(['slug' => 'draft-page', 'status' => PageStatus::Draft]);
    Page::factory()->create(['slug' => 'store-only', 'target' => 'storefront']);

    $this->get($this->centralUrl('/pages/shipping-policy?lang=en'))->assertOk()->assertSee('Shipping policy');
    $this->get($this->centralUrl('/pages/draft-page'))->assertNotFound();
    $this->get($this->centralUrl('/pages/store-only'))->assertNotFound();
    $this->get($this->centralUrl('/pages/terms'))->assertNotFound();
    $this->get($this->centralUrl('/templates/category/unknown'))->assertNotFound();
});

it('publishes a sitemap with localized URLs and a robots file', function (): void {
    $this->get($this->centralUrl('/sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('central.blog.show', 'launch-your-online-store').'?lang=ar', false)
        ->assertSee(route('central.terms').'?lang=en', false)
        ->assertSee('hreflang="ar"', false);

    $this->get($this->centralUrl('/robots.txt'))
        ->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.route('central.sitemap'));
});

it('switches the language with a cookie and returns to the same page', function (): void {
    $this->from($this->centralUrl('/pricing'))
        ->get($this->centralUrl('/locale/ar'))
        ->assertRedirect($this->centralUrl('/pricing'))
        ->assertCookie('locale', 'ar', false);

    $this->get($this->centralUrl('/locale/xx'))->assertNotFound();
});

it('renders the central 404 page on the central host', function (): void {
    $this->get($this->centralUrl('/this-page-does-not-exist?lang=en'))
        ->assertNotFound()
        ->assertSee('data-page="site-error"', false);
});

it('renders the central 500, 419 and maintenance pages and localises unmatched routes', function (): void {
    $this->get($this->centralUrl('/'));

    foreach (['500', '419', '503'] as $code) {
        expect(view('errors.'.$code, ['exception' => new HttpException((int) $code, '', null, ['Retry-After' => 600])])->render())
            ->toContain('data-page="site-error"');
    }

    $this->get($this->centralUrl('/missing-page?lang=ar'))
        ->assertNotFound()
        ->assertSeeInOrder(['<html', 'lang="ar"', 'dir="rtl"'], false);
});
