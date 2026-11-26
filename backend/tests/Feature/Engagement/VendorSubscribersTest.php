<?php

use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Identity\Models\VendorStaff;

/*
| VEN-026: vendor Newsletter subscribers — list with language / status / source filters,
| CSV export, unsubscribe / re-subscribe, delete, permissions and store isolation.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('newsshop');
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/sales/subscribers'.$path);
    $this->data = fn (string $query = ''): array => $this->getJson(($this->url)('/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
    $customer = $this->createCustomer($this->tenant, ['email' => 'sara.k@example.com']);
    [$this->sara, $this->khaled, $this->noor] = $this->tenant->run(fn (): array => [
        NewsletterSubscriber::query()->create(['email' => 'sara.k@example.com', 'locale' => 'ar', 'customer_id' => $customer->id, 'subscribed_at' => now()->subDays(3)]),
        NewsletterSubscriber::query()->create(['email' => 'khaled.m@example.com', 'locale' => 'en', 'subscribed_at' => now()->subDays(2)]),
        NewsletterSubscriber::query()->create(['email' => 'noor@example.com', 'locale' => 'ar', 'subscribed_at' => now()->subDay(), 'unsubscribed_at' => now()]),
    ]);
    $this->customer = $customer;
});

it('sends guests to the login and forbids staff without subscriber permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $viewer = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'subscribers.view']);

        return $staff;
    });
    $this->actingAsVendor($viewer, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertDontSee(__('engagement::subscribers.export'));
    expect(($this->data)()['data'][0]['actions'])->not->toContain(__('engagement::subscribers.delete'));
    $this->get(($this->url)('/export'))->assertForbidden();
    $this->patchJson(($this->url)('/'.$this->sara->id.'/status?subscribed=0'))->assertForbidden();
    $this->deleteJson(($this->url)('/'.$this->sara->id))->assertForbidden();

    $none = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view']);

        return $staff;
    });
    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();
});

it('lists, filters and exports subscribers', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertSee(__('engagement::subscribers.title'))->assertSee(__('engagement::subscribers.export'));

    $all = ($this->data)();
    $rows = collect($all['data'])->keyBy(fn (array $row): string => strip_tags($row['email']));
    expect($all['recordsTotal'])->toBe(3)
        ->and($rows['sara.k@example.com']['source'])->toBe(__('engagement::subscribers.sources.account'))
        ->and($rows['sara.k@example.com']['actions'])->toContain('/admin/sales/customers/'.$this->customer->id)
        ->and($rows['khaled.m@example.com']['source'])->toBe(__('engagement::subscribers.sources.signup_form'))
        ->and($rows['noor@example.com']['status'])->toContain(__('engagement::subscribers.statuses.unsubscribed'));

    expect(($this->data)('&status=subscribed')['recordsFiltered'])->toBe(2)
        ->and(($this->data)('&status=unsubscribed')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('&locale=en')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('&source=signup_form')['recordsFiltered'])->toBe(2)
        ->and(($this->data)('&search=khaled')['recordsFiltered'])->toBe(1);

    $csv = $this->get(($this->url)('/export?status=subscribed'))->assertOk()->streamedContent();
    expect($csv)->toContain('sara.k@example.com')->toContain('khaled.m@example.com')->not->toContain('noor@example.com');
});

it('unsubscribes, re-subscribes and deletes a subscriber', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->patchJson(($this->url)('/'.$this->sara->id.'/status'))->assertUnprocessable()->assertJsonValidationErrors(['subscribed']);
    $this->patchJson(($this->url)('/'.$this->sara->id.'/status?subscribed=0'))->assertOk();
    expect($this->tenant->run(fn () => $this->sara->fresh()?->unsubscribed_at))->not->toBeNull();
    $this->patchJson(($this->url)('/'.$this->noor->id.'/status?subscribed=1'))->assertOk();
    expect($this->tenant->run(fn () => $this->noor->fresh()?->unsubscribed_at))->toBeNull();

    $this->deleteJson(($this->url)('/'.$this->khaled->id))->assertOk();
    expect($this->tenant->run(fn () => NewsletterSubscriber::query()->count()))->toBe(2);
    $this->deleteJson(($this->url)('/'.$this->khaled->id))->assertNotFound();
});

it('keeps subscribers of other stores out of reach', function (): void {
    $other = $this->createTenant('othernews');
    $other->run(fn () => NewsletterSubscriber::query()->create(['email' => 'other@example.com', 'subscribed_at' => now()]));
    $this->actingAsVendor($this->owner, $this->tenant);

    $emails = collect(($this->data)()['data'])->pluck('email')->implode(' ');
    expect($emails)->not->toContain('other@example.com');
});
