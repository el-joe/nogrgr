<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Jobs\ProvisionTenant;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/VendorHelpers.php';

/*
| Admin › Vendors › Create vendor (ADM-040a): wizard page, validation, slug check,
| provisioning queued with the converted values.
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createVendorInput(array $overrides = []): array
{
    return array_merge([
        'owner_name' => 'Reem Al-Qahtani',
        'owner_email' => 'Reem@DesertRose.sa',
        'owner_phone' => '0552201144',
        'owner_phone_country' => 'sa',
        'owner_password' => 'secret-pass-123',
        'store_name' => 'Desert Rose Perfumes',
        'slug' => 'desertrose-'.strtolower(Str::random(6)),
        'custom_domain' => '',
        'profit_percent' => '62.5',
    ], $overrides);
}

it('protects the create endpoints', function (): void {
    $this->get($this->centralUrl('/admin/vendors/create'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->get($this->centralUrl('/admin/vendors/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/vendors'), createVendorInput())->assertForbidden();
    $this->getJson($this->centralUrl('/admin/vendors/slug-check?slug=abc'))->assertForbidden();
});

it('renders the 4-step wizard without a categories field', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    Plan::factory()->create(['name' => ['en' => 'Growth', 'ar' => 'نمو']]);

    $this->get($this->centralUrl('/admin/vendors/create'))
        ->assertOk()
        ->assertSee(__('platform::vendors.create.steps.owner'))
        ->assertSee(__('platform::vendors.create.steps.review'))
        ->assertSee(__('platform::vendors.create.submit'))
        ->assertSee('Growth')
        ->assertSee(route('admin.vendors.slug-check'), false)
        ->assertDontSee('name="category_ids', false);
});

it('validates the wizard input', function (): void {
    Queue::fake();
    $this->actingAs(vendorsAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/vendors'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_name', 'owner_email', 'owner_password', 'store_name', 'slug', 'profit_percent']);

    $this->postJson($this->centralUrl('/admin/vendors'), createVendorInput([
        'slug' => 'Bad Slug!',
        'custom_domain' => 'not a host',
        'profit_percent' => '120',
        'owner_password' => 'short',
        'owner_phone' => '12',
        'plan_id' => 999999,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug', 'custom_domain', 'profit_percent', 'owner_password', 'owner_phone', 'plan_id']);

    Queue::assertNothingPushed();
});

it('refuses a taken sub-domain and a platform host as custom domain', function (): void {
    Queue::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $taken = vendorsTenant();

    $this->postJson($this->centralUrl('/admin/vendors'), createVendorInput([
        'slug' => $taken->slug,
        'custom_domain' => 'shop.'.config('tenancy.base_domain'),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug', 'custom_domain']);
});

it('creates the store and queues provisioning', function (): void {
    Queue::fake();
    $admin = vendorsAdmin();
    $this->actingAs($admin, 'admin');
    $saudi = Country::factory()->iso('SA')->create();
    $uae = Country::factory()->iso('AE')->create();
    $plan = Plan::factory()->create();
    $input = createVendorInput([
        'custom_domain' => 'DesertRose.sa',
        'default_country_id' => $saudi->id,
        'selling_country_ids' => [$saudi->id, $uae->id],
        'plan_id' => $plan->id,
        'term' => 'yearly',
        'plan_assignment' => 'manual_paid',
    ]);

    $response = $this->postJson($this->centralUrl('/admin/vendors'), $input)->assertCreated();

    /** @var Tenant $tenant */
    $tenant = Tenant::query()->where('slug', $input['slug'])->firstOrFail();
    $response->assertJsonPath('redirect', route('admin.vendors.show', $tenant->id));

    expect($tenant->status)->toBe(TenantStatus::Pending)
        ->and($tenant->owner_email)->toBe('reem@desertrose.sa')
        ->and($tenant->owner_phone)->toBe('+966552201144')
        ->and($tenant->profit_percent_bps)->toBe(6250)
        ->and($tenant->default_country_id)->toBe($saudi->id)
        ->and($tenant->selling_country_ids)->toBe([$saudi->id, $uae->id])
        ->and($tenant->getAttribute(TenantProvisioningService::PAYLOAD))->toMatchArray([
            'plan_id' => $plan->id,
            'term' => 'yearly',
            'plan_assignment' => 'manual_paid',
            'admin_id' => $admin->id,
        ])
        ->and(DomainRequest::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('host'))->toBe('desertrose.sa');

    Queue::assertPushedOn(TenantProvisioningService::QUEUE, ProvisionTenant::class, fn (ProvisionTenant $job): bool => $job->tenantId === $tenant->id);
});

it('answers the live sub-domain check', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $taken = vendorsTenant();

    $this->getJson($this->centralUrl('/admin/vendors/slug-check?slug=fresh-store-'.strtolower(Str::random(5))))
        ->assertOk()
        ->assertJsonPath('available', true);

    $this->getJson($this->centralUrl('/admin/vendors/slug-check?slug='.$taken->slug))
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('message', __('platform::vendors.create.slug_taken'));

    $this->getJson($this->centralUrl('/admin/vendors/slug-check?slug=login'))
        ->assertOk()
        ->assertJsonPath('available', false);

    $this->getJson($this->centralUrl('/admin/vendors/slug-check'))->assertUnprocessable()->assertJsonValidationErrors('slug');
});
