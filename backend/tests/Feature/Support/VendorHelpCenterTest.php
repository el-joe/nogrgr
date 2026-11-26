<?php

use App\Modules\Support\Enums\HelpTopic;
use App\Modules\Support\Models\HelpArticleFeedback;

require_once __DIR__.'/SupportHelpers.php';

/*
| VEN-092 / 092a Vendor › Help center: topics with counts, topic articles, search, article
| page (en + ar), 404 for unknown slugs, helpful votes stored per store member.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('helpacme');
    $this->staff = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/help'.$path);
    $this->as = fn () => $this->actingAsVendor($this->staff, $this->tenant);
});

it('sends guests to the login page', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));
});

it('lists the topics with counts and the articles of a topic', function (): void {
    ($this->as)()->get(($this->url)())
        ->assertOk()
        ->assertSee(__('support::help.title'))
        ->assertSee(HelpTopic::Products->label())
        ->assertSee(trans_choice('support::help.articles_count', 4, ['count' => 4]))
        ->assertSee(__('support::help.articles.setup-checklist.title'))
        ->assertSee('data-page="vendor-help"', false);

    ($this->as)()->get(($this->url)('?topic=products'))
        ->assertOk()
        ->assertSee(__('support::help.articles.markup-per-country.title'))
        ->assertSee(__('support::help.articles.own-products.title'));

    ($this->as)()->get(($this->url)('?topic=nope'))->assertRedirect();
});

it('searches every article and shows the empty result state', function (): void {
    ($this->as)()->get(($this->url)('?q=markup country'))
        ->assertOk()
        ->assertSee(__('support::help.results_title', ['term' => 'markup country']))
        ->assertSee('data-help-result="markup-per-country"', false);

    ($this->as)()->get(($this->url)('?q=zzqxv'))
        ->assertOk()
        ->assertSee(__('support::help.no_results_title', ['term' => 'zzqxv']));
});

it('shows an article in english and arabic, with related articles and the ticket call to action', function (): void {
    ($this->as)()->get(($this->url)('/articles/markup-per-country'))
        ->assertOk()
        ->assertSee(__('support::help.articles.markup-per-country.title'))
        ->assertSee('Selling price = cost + profit')
        ->assertSee(__('support::help.articles.own-products.title'))
        ->assertSee(__('support::help.open_ticket'))
        ->assertSee(__('support::help.feedback.question'));

    ($this->as)()->get(($this->url)('/articles/markup-per-country?lang=ar'))
        ->assertOk()
        ->assertSee('كيف يعمل هامش الربح لكل دولة');

    ($this->as)()->get(($this->url)('/articles/unknown-article'))->assertNotFound();
});

it('stores one helpful vote per store member and article', function (): void {
    ($this->as)()->postJson(($this->url)('/articles/coupons/feedback'), [])->assertUnprocessable()->assertJsonValidationErrors('helpful');
    ($this->as)()->postJson(($this->url)('/articles/unknown/feedback'), ['helpful' => 1])->assertNotFound();

    ($this->as)()->postJson(($this->url)('/articles/coupons/feedback'), ['helpful' => 1])->assertOk();
    ($this->as)()->postJson(($this->url)('/articles/coupons/feedback'), ['helpful' => 0])->assertOk();

    $vote = HelpArticleFeedback::withoutTenantScope()->sole();
    expect($vote->helpful)->toBeFalse()->and($vote->tenant_id)->toBe($this->tenant->id)->and($vote->article)->toBe('coupons');

    ($this->as)()->get(($this->url)('/articles/coupons'))->assertOk()->assertSee(__('support::help.feedback.voted_no'));
});

it('has an english and arabic text for every article of every topic', function (): void {
    foreach (HelpTopic::cases() as $topic) {
        foreach ($topic->articles() as $slug) {
            foreach (['en', 'ar'] as $locale) {
                $content = __('support::help.articles.'.$slug, [], $locale);
                expect($content)->toBeArray()
                    ->and($content['title'])->toBeString()->not->toBe('')
                    ->and($content['sections'])->not->toBeEmpty();
            }
        }
    }
});
