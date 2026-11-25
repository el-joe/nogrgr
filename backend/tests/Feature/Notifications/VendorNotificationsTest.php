<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Models\TenantNotificationRecord;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

/*
| VEN-090 vendor notifications inbox: feed (own rows only, filters), open (mark read +
| vendor route target), mark read / unread, mark all read, bell "View all" link.
*/

function vendorInboxRow(Tenant $tenant, VendorStaff $staff, array $data = [], ?string $readAt = null, string $type = 'requests.admin_replied'): string
{
    $id = (string) Str::uuid();

    $tenant->run(fn () => TenantNotificationRecord::query()->create([
        'id' => $id,
        'type' => $type,
        'notifiable_type' => $staff->getMorphClass(),
        'notifiable_id' => $staff->id,
        'data' => $data + ['title' => 'New reply on PR-0007', 'body' => 'The platform replied.'],
        'read_at' => $readAt,
    ]));

    return $id;
}

beforeEach(function (): void {
    $this->tenant = $this->createTenant('acme');
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/notifications'.$path);
});

it('sends guests to the login and forbids staff without the permission', function (): void {
    $this->get(($this->url)(''))->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $staff = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo('dashboard.view');

        return $staff;
    });

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)(''))->assertForbidden();
    $this->actingAsVendor($staff, $this->tenant)->getJson(($this->url)('/data'))->assertForbidden();
});

it('renders the inbox and links "View all" from the bell', function (): void {
    vendorInboxRow($this->tenant, $this->owner);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)(''))
        ->assertOk()
        ->assertSee(__('notifications::panel.inbox.mark_all_vendor'))
        ->assertSee(trans_choice('notifications::panel.unread_count', 1, ['count' => 1]))
        ->assertSee(($this->url)(''), false);
});

it('renders the vendor inbox strings as translations, not raw keys (QA-P5-002)', function (): void {
    expect(__('notifications::panel.inbox.mark_all_vendor'))->toBe('Mark all as read')
        ->and(__('notifications::panel.inbox.vendor_empty_description'))->not->toStartWith('notifications::')
        ->and(trans('notifications::panel.inbox.vendor_empty_description', [], 'ar'))->not->toStartWith('notifications::');

    vendorInboxRow($this->tenant, $this->owner);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)(''))
        ->assertOk()
        ->assertDontSee('notifications::panel', false);
});

it('lists only the member\'s own rows with category and state filters', function (): void {
    vendorInboxRow($this->tenant, $this->owner, ['title' => 'Mine unread']);
    vendorInboxRow($this->tenant, $this->owner, ['title' => 'Mine read'], now()->toDateTimeString());
    vendorInboxRow($this->tenant, $this->owner, ['title' => 'Order row'], null, 'orders.created');
    $colleague = $this->createVendorOwner($this->tenant);
    vendorInboxRow($this->tenant, $colleague, ['title' => 'Not mine']);

    $json = $this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsFiltered'])->toBe(3)
        ->and(collect($json['data'])->pluck('notification')->implode(''))->not->toContain('Not mine');

    $unread = $this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/data?draw=1&start=0&length=10&state=unread&category=requests'))->json();
    expect($unread['recordsFiltered'])->toBe(1)->and($unread['data'][0]['notification'])->toContain('Mine unread');
});

it('marks one row read / unread and all rows read', function (): void {
    $id = vendorInboxRow($this->tenant, $this->owner);
    vendorInboxRow($this->tenant, $this->owner);

    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/read"), [])->assertUnprocessable()->assertJsonValidationErrors('read');
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/read"), ['read' => 1])->assertOk()->assertJsonPath('data.unread', 1);
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/read"), ['read' => 0])->assertOk()->assertJsonPath('data.unread', 2);

    $other = $this->createVendorOwner($this->tenant);
    $this->actingAsVendor($other, $this->tenant)->patchJson(($this->url)("/{$id}/read"), ['read' => 1])->assertNotFound();

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/read-all'))->assertOk();
    expect($this->tenant->run(fn () => TenantNotificationRecord::query()->whereNull('read_at')->where('notifiable_id', $this->owner->id)->count()))->toBe(0);
});

it('opens a row at its vendor route and marks it read', function (): void {
    $id = vendorInboxRow($this->tenant, $this->owner, ['vendor_route' => ['name' => 'requests.product.show', 'parameters' => ['id' => 7]]]);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$id}/open"))
        ->assertRedirect($this->tenantUrl($this->tenant, 'admin/requests/product/7'));

    expect($this->tenant->run(fn () => TenantNotificationRecord::query()->find($id)?->read_at))->not->toBeNull();
});
