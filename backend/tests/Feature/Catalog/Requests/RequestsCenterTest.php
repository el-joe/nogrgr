<?php

use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Services\Requests\RequestsCenterService;

require_once __DIR__.'/RequestHelpers.php';

/*
| Requests center hub (ADM-018): access, redirect to the first permitted tab and the
| attention counts per tab (unread / never opened threads, pending reviews).
*/

it('guards every requests center page', function (string $path): void {
    $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin(['catalog.products.view']), 'admin');
    $this->get($this->centralUrl($path))->assertForbidden();
})->with([
    '/admin/requests',
    '/admin/requests/products',
    '/admin/requests/edits',
    '/admin/requests/manufacturing',
    '/admin/requests/brands',
    '/admin/requests/tenant-changes',
]);

it('redirects the hub to the first tab the admin may open', function (): void {
    $this->actingAs(catalogAdmin(['requests.brands.view']), 'admin');
    $this->get($this->centralUrl('/admin/requests'))->assertRedirect(route('admin.requests.brands.index'));

    $this->actingAs(catalogAdmin(), 'admin');
    $this->get($this->centralUrl('/admin/requests'))->assertRedirect(route('admin.requests.products.index'));
});

it('counts unread items per tab and feeds the sidebar badges', function (): void {
    $admin = catalogAdmin();
    $seen = ProductRequest::factory()->create(['admin_seen_at' => now()]);
    ProductRequest::factory()->create();
    ProductRequest::factory()->create(['archived_at' => now()]);
    $seen->messages()->create(['tenant_id' => $seen->tenant_id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz', 'body' => 'Any news?']);
    $answered = ProductRequest::factory()->create(['admin_seen_at' => now()]);
    $answered->messages()->create(['tenant_id' => $answered->tenant_id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz', 'body' => 'Hi', 'read_at' => now()]);
    ManufacturingRequest::factory()->count(2)->create();
    BrandRequest::factory()->create(['admin_seen_at' => now()]);
    EditRequest::factory()->count(3)->create();
    TenantChangeRequest::factory()->create();

    $center = app(RequestsCenterService::class);
    $tabs = $center->tabs($admin);

    expect(array_map(fn (array $tab): int => $tab['count'], $tabs))->toBe([
        'products' => 2,
        'edits' => 3,
        'manufacturing' => 2,
        'brands' => 0,
        'tenant-changes' => 1,
    ])->and($center->badgeCounts($admin))->toMatchArray(['requests' => 8, 'requests.products' => 2, 'requests.edits' => 3]);

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/requests/products'))
        ->assertOk()
        ->assertSee(__('catalog::requests.title'))
        ->assertSee(route('admin.requests.products.data'), false)
        ->assertSee(route('admin.requests.edits.index'), false);
});

it('only shows the tabs the admin may open', function (): void {
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin')
        ->get($this->centralUrl('/admin/requests/products'))
        ->assertOk()
        ->assertDontSee(route('admin.requests.edits.index'), false)
        ->assertDontSee('request-status-modal', false);
});
