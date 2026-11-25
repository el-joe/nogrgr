<?php

use App\Modules\Catalog\DTOs\Requests\TenantChangeSubmissionData;
use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Catalog\Services\Requests\TenantChangeService;
use App\Modules\Localization\Models\Country;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/RequestHelpers.php';

/*
| Requests center › Tenant changes (ADM-018i): selling countries / categories, approve
| (applies to the store) or reject (note required).
*/

beforeEach(function (): void {
    $this->countries = collect([
        Country::query()->create(['iso2' => 'SA', 'iso3' => 'SAU', 'name' => ['en' => 'Saudi Arabia', 'ar' => 'السعودية'], 'flag' => '🇸🇦', 'is_active' => true]),
        Country::query()->create(['iso2' => 'KW', 'iso3' => 'KWT', 'name' => ['en' => 'Kuwait', 'ar' => 'الكويت'], 'flag' => '🇰🇼', 'is_active' => true]),
        Country::query()->create(['iso2' => 'BH', 'iso3' => 'BHR', 'name' => ['en' => 'Bahrain', 'ar' => 'البحرين'], 'flag' => '🇧🇭', 'is_active' => true]),
    ]);
    [$this->tenant, $this->owner] = requestsStore(['selling_country_ids' => [$this->countries[0]->id]]);
    $this->change = app(TenantChangeService::class)->submit(new TenantChangeSubmissionData(
        tenantId: $this->tenant->id,
        type: TenantChangeType::Countries,
        requestedIds: [$this->countries[0]->id, $this->countries[1]->id, $this->countries[2]->id],
        vendorNote: 'Expanding to the Gulf.',
        submittedBy: 'Lina',
    ));
});

it('lists change requests with the added items and filters', function (): void {
    $this->actingAs(catalogAdmin(['requests.tenant-changes.view']), 'admin');
    $url = $this->centralUrl('/admin/requests/tenant-changes/data?draw=1&start=0&length=25');

    expect($this->getJson($url)->assertOk()->json('data.0.change'))->toContain('Kuwait, Bahrain')
        ->and($this->getJson($url.'&type=categories')->json('recordsFiltered'))->toBe(0);

    $this->get($this->centralUrl("/admin/requests/tenant-changes/{$this->change->id}"))
        ->assertOk()
        ->assertSee('Expanding to the Gulf.')
        ->assertDontSee('data-decision-form', false);
});

it('applies the requested countries to the store on approval', function (): void {
    Notification::fake();
    $this->actingAs(catalogAdmin(['requests.tenant-changes.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/tenant-changes/{$this->change->id}/decision"), ['decision' => 'approve', 'note' => 'Welcome to the Gulf'])->assertOk();

    expect($this->change->refresh()->status)->toBe(TenantChangeStatus::Approved)
        ->and($this->change->admin_note)->toBe('Welcome to the Gulf')
        ->and($this->tenant->refresh()->selling_country_ids)->toEqualCanonicalizing($this->countries->pluck('id')->all());
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'tenant_change_approved');

    $this->postJson($this->centralUrl("/admin/requests/tenant-changes/{$this->change->id}/decision"), ['decision' => 'reject', 'note' => 'Too late'])->assertUnprocessable();
});

it('applies categories to selling_category_ids', function (): void {
    $categories = Category::factory()->count(2)->create();
    $change = app(TenantChangeService::class)->submit(new TenantChangeSubmissionData($this->tenant->id, TenantChangeType::Categories, $categories->pluck('id')->all()));
    $this->actingAs(catalogAdmin(['requests.tenant-changes.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/tenant-changes/{$change->id}/decision"), ['decision' => 'approve'])->assertOk();

    expect($this->tenant->refresh()->selling_category_ids)->toEqualCanonicalizing($categories->pluck('id')->all());
});

it('requires a note to reject and keeps the store unchanged', function (): void {
    $this->actingAs(catalogAdmin(['requests.tenant-changes.manage']), 'admin');
    $url = $this->centralUrl("/admin/requests/tenant-changes/{$this->change->id}/decision");

    $this->postJson($url, ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson($url, ['decision' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('decision');
    $this->postJson($url, ['decision' => 'reject', 'note' => 'Bahrain is not supported yet.'])->assertOk();

    expect($this->change->refresh()->status)->toBe(TenantChangeStatus::Rejected)
        ->and($this->tenant->refresh()->selling_country_ids)->toBe([$this->countries[0]->id]);
});

it('forbids decisions without the manage permission', function (): void {
    $this->actingAs(catalogAdmin(['requests.tenant-changes.view']), 'admin');
    $this->postJson($this->centralUrl("/admin/requests/tenant-changes/{$this->change->id}/decision"), ['decision' => 'approve'])->assertForbidden();
});
