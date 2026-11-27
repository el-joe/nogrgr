<?php

use App\Modules\Platform\Enums\MaintenanceScope;
use App\Modules\Platform\Http\Requests\Admin\MaintenanceWindowRequest;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\MaintenanceWindow;
use App\Modules\Platform\Services\MaintenanceService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › System tools › Maintenance windows (ADM-113) and the platform.maintenance
| middleware on the central website, storefronts and Store API.
*/

function maintenanceWindow(array $attributes = []): MaintenanceWindow
{
    $window = MaintenanceWindow::query()->create(array_merge([
        'scope' => MaintenanceScope::Platform,
        'title' => 'Database upgrade',
        'starts_at' => now()->subMinutes(5),
        'ends_at' => now()->addHour(),
    ], $attributes));
    app(MaintenanceService::class)->forget();

    return $window;
}

function windowPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Database upgrade',
        'notes' => 'MySQL 8.4',
        'scope' => 'platform',
        'starts_now' => '0',
        'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        'message_en' => 'Back soon',
        'message_ar' => 'نعود قريبًا',
        'allowed_ips' => "203.0.113.7\n10.0.0.0/8",
    ], $overrides);
}

it('protects the maintenance endpoints', function (): void {
    $window = maintenanceWindow(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

    $this->get($this->centralUrl('/admin/system/maintenance'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/system/maintenance'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/system/maintenance/data'))->assertForbidden();

    $this->actingAs(vendorsAdmin(['system.maintenance.view']), 'admin');
    $this->get($this->centralUrl('/admin/system/maintenance'))->assertOk()->assertDontSee(__('platform::maintenance.schedule'));
    $this->postJson($this->centralUrl('/admin/system/maintenance'), windowPayload())->assertForbidden();
    $this->putJson($this->centralUrl("/admin/system/maintenance/{$window->id}"), windowPayload())->assertForbidden();
    $this->postJson($this->centralUrl("/admin/system/maintenance/{$window->id}/end"))->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/system/maintenance/{$window->id}"))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/system/maintenance/stores'))->assertForbidden();
});

it('lists windows with status and scope filters', function (): void {
    $store = vendorsTenant(['name' => 'Megz Store']);
    maintenanceWindow(['title' => 'Running now']);
    maintenanceWindow(['title' => 'Reverb move', 'scope' => MaintenanceScope::Store, 'tenant_id' => $store->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30)]);
    maintenanceWindow(['title' => 'Storage migration', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addMinutes(45)]);

    $this->actingAs(vendorsAdmin(['system.maintenance.view', 'system.maintenance.manage']), 'admin');

    $this->get($this->centralUrl('/admin/system/maintenance'))->assertOk()
        ->assertSee(__('platform::maintenance.title'))
        ->assertSee('Running now')
        ->assertSee(__('platform::maintenance.schedule'));

    $rows = $this->getJson($this->centralUrl('/admin/system/maintenance/data?draw=1&start=0&length=25'))->assertOk()->json('data');
    expect($rows)->toHaveCount(3);

    $scheduled = $this->getJson($this->centralUrl('/admin/system/maintenance/data?draw=1&start=0&length=25&status=scheduled'))->json('data');
    expect($scheduled)->toHaveCount(1)
        ->and($scheduled[0]['title'])->toContain('Reverb move')
        ->and($scheduled[0]['scope'])->toContain('Megz Store')
        ->and($scheduled[0]['duration'])->toContain('30');

    expect($this->getJson($this->centralUrl('/admin/system/maintenance/data?draw=1&start=0&length=25&status=completed'))->json('data'))->toHaveCount(1)
        ->and($this->getJson($this->centralUrl('/admin/system/maintenance/data?draw=1&start=0&length=25&scope=store'))->json('data'))->toHaveCount(1);
});

it('creates, edits, ends and deletes windows with audit entries', function (): void {
    $this->actingAs($admin = vendorsAdmin(['system.maintenance.view', 'system.maintenance.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/system/maintenance'), windowPayload())->assertCreated();
    $window = MaintenanceWindow::query()->sole();
    expect($window->allowed_ips)->toBe(['203.0.113.7', '10.0.0.0/8'])
        ->and($window->message)->toEqual(['en' => 'Back soon', 'ar' => 'نعود قريبًا'])
        ->and($window->created_by)->toBe($admin->id)
        ->and($window->status()->value)->toBe('scheduled');

    $this->putJson($this->centralUrl("/admin/system/maintenance/{$window->id}"), windowPayload(['title' => 'DB upgrade v2', 'starts_now' => '1', 'ends_at' => '']))->assertOk();
    $window->refresh();
    expect($window->title)->toBe('DB upgrade v2')
        ->and($window->ends_at)->toBeNull()
        ->and($window->status()->value)->toBe('active');

    $this->deleteJson($this->centralUrl("/admin/system/maintenance/{$window->id}"))->assertStatus(422);

    $this->postJson($this->centralUrl("/admin/system/maintenance/{$window->id}/end"))->assertOk();
    expect($window->refresh()->status()->value)->toBe('completed');
    $this->putJson($this->centralUrl("/admin/system/maintenance/{$window->id}"), windowPayload())->assertStatus(422);

    $this->deleteJson($this->centralUrl("/admin/system/maintenance/{$window->id}"))->assertOk();
    expect(MaintenanceWindow::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'like', 'system.maintenance.%')->pluck('action')->all())
        ->toEqual(['system.maintenance.created', 'system.maintenance.updated', 'system.maintenance.ended', 'system.maintenance.deleted']);
});

it('cancels a scheduled window', function (): void {
    $window = maintenanceWindow(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    $this->actingAs(vendorsAdmin(['system.maintenance.view', 'system.maintenance.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/system/maintenance/{$window->id}/end"))->assertOk()->assertJsonPath('message', __('platform::maintenance.cancelled', ['title' => $window->title]));
    expect($window->refresh()->status()->value)->toBe('cancelled');
});

it('validates windows', function (): void {
    $this->actingAs(vendorsAdmin(['system.maintenance.view', 'system.maintenance.manage']), 'admin');
    $url = $this->centralUrl('/admin/system/maintenance');

    $this->postJson($url, windowPayload(['allowed_ips' => "10.0.0.300\n2001:db8::/129"]))->assertJsonValidationErrors(['allowed_ips.0', 'allowed_ips.1']);
    $this->postJson($url, windowPayload(['scope' => 'store']))->assertJsonValidationErrors('tenant_id');
    $this->postJson($url, windowPayload(['ends_at' => now()->format('Y-m-d\TH:i')]))->assertJsonValidationErrors('ends_at');
    $this->postJson($url, windowPayload(['starts_at' => '']))->assertJsonValidationErrors('starts_at');
    $this->postJson($url, windowPayload(['title' => '', 'scope' => 'everything']))->assertJsonValidationErrors(['title', 'scope']);

    expect(MaintenanceWindowRequest::validIpOrRange('2001:db8::/32'))->toBeTrue()
        ->and(MaintenanceWindowRequest::validIpOrRange('192.168.1.0/24'))->toBeTrue()
        ->and(MaintenanceWindowRequest::validIpOrRange('192.168.1.0/33'))->toBeFalse();
});

it('searches stores for the picker', function (): void {
    vendorsTenant(['name' => 'Megz Store']);
    vendorsTenant(['name' => 'Other Shop']);
    $this->actingAs(vendorsAdmin(['system.maintenance.view', 'system.maintenance.manage']), 'admin');

    $this->getJson($this->centralUrl('/admin/system/maintenance/stores?q=Megz'))->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.text', fn (string $text) => str_starts_with($text, 'Megz Store'));
});

it('answers 503 on the central website during a platform window, admins and allowed IPs pass', function (): void {
    maintenanceWindow(['message' => ['en' => 'Upgrading the database']]);

    $this->get($this->centralUrl('/'))->assertStatus(503)->assertSee('Upgrading the database')->assertHeader('Retry-After');
    $this->getJson($this->centralUrl('/'))->assertStatus(503)->assertJsonPath('message', 'Upgrading the database')->assertJsonPath('data.maintenance', true);

    // panel sign-in keeps working
    $this->get($this->centralUrl('/admin/login'))->assertOk();
    $this->get($this->centralUrl('/login'))->assertSuccessful();

    $this->actingAs(vendorsAdmin(['system.maintenance.view']), 'admin');
    $this->get($this->centralUrl('/'))->assertOk();
    $this->get($this->centralUrl('/admin/system/maintenance'))->assertOk();
});

it('lets allowed IPs through', function (): void {
    maintenanceWindow(['allowed_ips' => ['10.0.0.0/8']]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->get($this->centralUrl('/'))->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get($this->centralUrl('/'))->assertStatus(503);
});

it('ignores scheduled, finished and store-only windows on the central website', function (): void {
    $store = vendorsTenant();
    maintenanceWindow(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    maintenanceWindow(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
    maintenanceWindow(['ended_at' => now()->subMinute()]);
    maintenanceWindow(['scope' => MaintenanceScope::Storefronts]);
    maintenanceWindow(['scope' => MaintenanceScope::Store, 'tenant_id' => $store->id]);

    $this->get($this->centralUrl('/'))->assertOk();
});

it('takes only the targeted store offline, storefront and Store API, vendor panel keeps working', function (): void {
    $store = $this->createTenant('mnt-'.Str::lower(Str::random(6)));
    $other = $this->createTenant('mnt-'.Str::lower(Str::random(6)));
    maintenanceWindow(['scope' => MaintenanceScope::Store, 'tenant_id' => $store->id, 'message' => ['en' => 'Stocktaking today', 'ar' => 'جرد اليوم']]);

    $this->get($this->tenantUrl($store, '/'))->assertStatus(503);
    $this->getJson($this->tenantUrl($store, 'store-api/v1/categories'))->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Stocktaking today');
    $this->get($this->tenantUrl($store, 'login'))->assertOk();

    $this->get($this->tenantUrl($other, '/'))->assertOk();
    $this->get($this->centralUrl('/'))->assertOk();
});

it('takes every store offline for a storefronts window', function (): void {
    $store = $this->createTenant('mnt-'.Str::lower(Str::random(6)));
    maintenanceWindow(['scope' => MaintenanceScope::Storefronts]);

    $this->get($this->tenantUrl($store, '/'))->assertStatus(503);
    $this->get($this->centralUrl('/'))->assertOk();
});
