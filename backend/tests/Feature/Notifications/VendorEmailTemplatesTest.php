<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Events\EmailTemplatesSynced;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Support\Str;

/*
| VEN-080 / 080a Vendor › Settings › Email templates: the shopper e-mails with the platform
| default or the store's own version, edit per locale (sanitised), preview, reset to the
| default, sync from the platform (and the platform's "Sync to tenants" listener), and the
| store's e-mails using its version (EmailTemplateRenderer in tenant context).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('etp-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/email-templates'.$path);
    $this->data = fn (string $query = ''): array => $this->getJson(($this->url)('/data?draw=1&start=0&length=100'.$query))->assertOk()->json();
    $this->platform = EmailTemplate::factory()->event(EmailTemplateEvent::OrderConfirmation)->create([
        'subject' => ['en' => 'Order {{ order.number }} confirmed', 'ar' => 'تم تأكيد طلبك {{ order.number }}'],
        'body' => ['en' => '<p>Thanks {{ customer.first_name }}!</p>', 'ar' => '<p>شكرًا {{ customer.first_name }}!</p>'],
    ]);
    $this->own = fn (EmailTemplateEvent $event): ?StoreEmailTemplate => $this->tenant->run(fn () => StoreEmailTemplate::query()->where('event', $event->value)->first());
});

function storeTemplatePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'subject' => ['en' => 'Megz got your order {{ order.number }}', 'ar' => 'استلمنا طلبك {{ order.number }}'],
        'body' => ['en' => '<h2>Thank you {{ customer.first_name }}!</h2><p>We are packing it.</p><script>alert(1)</script>', 'ar' => '<p>نجهّز طلبك الآن.</p>'],
    ], $overrides);
}

it('sends guests to the login and guards every endpoint with settings.email-templates permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.email-templates.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();
    $this->getJson(($this->url)('/order.confirmation/edit'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('notifications::vendor_templates.subtitle'))->assertDontSee(__('notifications::vendor_templates.sync'));
    $this->get(($this->url)('/order.confirmation/edit'))->assertOk()->assertSee(__('notifications::vendor_templates.read_only'))->assertDontSee('contenteditable="true"', false);
    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload())->assertForbidden();
    $this->deleteJson(($this->url)('/order.confirmation'))->assertForbidden();
    $this->postJson(($this->url)('/sync'))->assertForbidden();
});

it('lists only the store-scoped e-mails with their source and filters', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertSee(__('notifications::vendor_templates.sync'));

    $rows = collect(($this->data)()['data']);
    expect($rows->pluck('id')->all())->toBe(array_map(static fn (EmailTemplateEvent $event): string => $event->value, EmailTemplateEvent::storeScoped()))
        ->and($rows->firstWhere('id', 'order.confirmation')['source'])->toContain(__('notifications::vendor_templates.sources.platform'))
        ->and($rows->firstWhere('id', 'order.confirmation')['languages'])->toBe('EN · AR')
        ->and($rows->firstWhere('id', 'order.shipped')['source'])->toContain(__('notifications::vendor_templates.sources.builtin'));

    expect(($this->data)('&search=shipped')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('&source=platform')['recordsFiltered'])->toBe(1);

    $this->getJson(($this->url)('/vendor.welcome/edit'))->assertNotFound();
    $this->getJson(($this->url)('/order.unknown/edit'))->assertNotFound();
});

it('customises a template per locale, previews it, and resets it to the platform default', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/order.confirmation/edit'))->assertOk()
        ->assertSee('Order {{ order.number }} confirmed', false)
        ->assertSee(__('notifications::vendor_templates.platform_note'))
        ->assertSee('"store.name":"Megz"', false);

    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload(['subject' => ['en' => ''], 'body' => ['en' => '<p> </p>']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['subject.en', 'body.en']);
    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload(['subject' => ['ar' => ''], 'body' => ['ar' => '<p>نص</p>']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['subject.ar']);

    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload())->assertOk();

    $own = ($this->own)(EmailTemplateEvent::OrderConfirmation);
    expect($own->is_customized)->toBeTrue()
        ->and($own->getTranslation('subject', 'en'))->toBe('Megz got your order {{ order.number }}')
        ->and($own->getTranslation('body', 'en'))->toContain('<h2>Thank you')->not->toContain('<script');
    expect(collect(($this->data)()['data'])->firstWhere('id', 'order.confirmation')['source'])->toContain(__('notifications::vendor_templates.sources.custom'));

    $preview = $this->get(($this->url)('/order.confirmation/preview?locale=ar'))->assertOk()->assertHeader('Content-Security-Policy');
    expect($preview->getContent())->toContain('نجهّز طلبك الآن.')->toContain('Megz');
    $this->get(($this->url)('/order.shipped/preview'))->assertOk()->assertSee('Aramex');

    $this->deleteJson(($this->url)('/order.confirmation'))->assertOk()->assertJsonPath('message', __('notifications::vendor_templates.reset_done', ['name' => EmailTemplateEvent::OrderConfirmation->label()]));
    $own = ($this->own)(EmailTemplateEvent::OrderConfirmation);
    expect($own->is_customized)->toBeFalse()->and($own->getTranslation('subject', 'en'))->toBe('Order {{ order.number }} confirmed');

    // A store-only template (no platform template) is removed by a reset.
    $this->putJson(($this->url)('/order.shipped'), storeTemplatePayload())->assertOk();
    $this->deleteJson(($this->url)('/order.shipped'))->assertOk();
    expect(($this->own)(EmailTemplateEvent::OrderShipped))->toBeNull();
});

it('uses the store version for that store\'s e-mails only', function (): void {
    $other = $this->createTenant('etp-o-'.Str::lower(Str::random(6)));
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload())->assertOk();

    $variables = ['order.number' => 'ORD-1', 'customer.first_name' => 'Sara', 'store.name' => 'Megz'];
    $mine = $this->tenant->run(fn () => app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderConfirmation, 'en', $variables));
    $theirs = $other->run(fn () => app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderConfirmation, 'en', $variables));
    $this->endTenancy();
    $central = app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderConfirmation, 'en', $variables);

    expect($mine->subject)->toBe('Megz got your order ORD-1')->and($mine->html)->toContain('Thank you Sara!')
        ->and($theirs->subject)->toBe('Order ORD-1 confirmed')
        ->and($central->subject)->toBe('Order ORD-1 confirmed');

    // An inactive platform template is not revived by a non-customised store copy.
    $this->deleteJson(($this->url)('/order.confirmation'))->assertOk();
    $this->platform->update(['status' => EmailTemplateStatus::Inactive]);
    $this->tenant->run(fn () => StoreEmailTemplate::query()->update(['status' => EmailTemplateStatus::Inactive->value]));
    expect($this->tenant->run(fn () => app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderConfirmation, 'en', $variables)))->toBeNull();
});

it('syncs non-customised templates from the platform, by button and on the platform sync event', function (): void {
    $shipped = EmailTemplate::factory()->event(EmailTemplateEvent::OrderShipped)->create();
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)('/order.confirmation'), storeTemplatePayload())->assertOk();

    $this->postJson(($this->url)('/sync'))->assertOk()->assertJsonPath('message', trans_choice('notifications::vendor_templates.synced', 1, ['count' => 1]));
    expect(($this->own)(EmailTemplateEvent::OrderShipped)->getTranslation('subject', 'en'))->toBe($shipped->getTranslation('subject', 'en'))
        // The customised one is kept.
        ->and(($this->own)(EmailTemplateEvent::OrderConfirmation)->getTranslation('subject', 'en'))->toBe('Megz got your order {{ order.number }}');

    event(new EmailTemplatesSynced([
        ['event' => 'order.shipped', 'audience' => 'shoppers', 'status' => 'active', 'subject' => ['en' => 'Shipped v2'], 'body' => ['en' => '<p>v2</p>']],
        ['event' => 'order.confirmation', 'audience' => 'shoppers', 'status' => 'active', 'subject' => ['en' => 'Confirmed v2'], 'body' => ['en' => '<p>v2</p>']],
        ['event' => 'vendor.welcome', 'audience' => 'vendors', 'status' => 'active', 'subject' => ['en' => 'Welcome'], 'body' => ['en' => '<p>Hi</p>']],
    ]));

    expect(($this->own)(EmailTemplateEvent::OrderShipped)->getTranslation('subject', 'en'))->toBe('Shipped v2')
        ->and(($this->own)(EmailTemplateEvent::OrderShipped)->synced_at)->not->toBeNull()
        ->and(($this->own)(EmailTemplateEvent::OrderConfirmation)->getTranslation('subject', 'en'))->toBe('Megz got your order {{ order.number }}')
        ->and($this->tenant->run(fn () => StoreEmailTemplate::query()->where('event', 'vendor.welcome')->exists()))->toBeFalse();
});
