<?php

use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanLimitOverride;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\EffectivePlanLimits;
use App\Modules\Billing\Services\PlanLimitOverrideService;
use App\Modules\Identity\Services\VendorStaffService;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Storefront\Services\Banners\VendorBannerPageService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Platform/Vendors/VendorHelpers.php';

/*
| Admin › Vendor profile › Limit overrides (ADM-041h) and EffectivePlanLimits: the
| override wins over the plan for every quota check (AI, staff, banners, usage card).
*/

function limitsPayload(array $overrides = []): array
{
    $limits = [];

    foreach (PlanLimit::values() as $limit) {
        $limits[$limit] = $overrides[$limit] ?? ['mode' => 'plan', 'value' => null];
    }

    return ['limits' => $limits];
}

function subscribedTenant(array $limits): array
{
    $tenant = vendorsTenant();
    $plan = Plan::factory()->create(['limits' => $limits]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);

    return [$tenant, $plan];
}

it('protects the limit overrides endpoint', function (): void {
    $tenant = vendorsTenant();
    $url = $this->centralUrl("/admin/vendors/{$tenant->id}/limits");

    $this->putJson($url, limitsPayload())->assertUnauthorized();

    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.manage']), 'admin');
    $this->putJson($url, limitsPayload())->assertForbidden();
});

it('renders the limits tab with plan and effective values, read-only for viewers', function (): void {
    [$tenant] = subscribedTenant(['products' => 500, 'ai_calls' => 100]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'ai_calls', 'value' => 2500]);

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=limits"))
        ->assertOk()
        ->assertSee(__('platform::vendors.limits.title'))
        ->assertSee(PlanLimit::AiAds->label())
        ->assertSee('2,500')
        ->assertSee(__('platform::vendors.limits.read_only'))
        ->assertDontSee(__('platform::vendors.limits.save'));

    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.limits.manage']), 'admin');
    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=limits"))
        ->assertOk()
        ->assertSee(__('platform::vendors.limits.save'))
        ->assertSee(route('admin.vendors.limits', $tenant->id), false);
});

it('saves, audits and clears overrides', function (): void {
    [$tenant] = subscribedTenant(['staff' => 3]);
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.limits.manage']), 'admin');
    $url = $this->centralUrl("/admin/vendors/{$tenant->id}/limits");

    $this->putJson($url, limitsPayload([
        'staff' => ['mode' => 'custom', 'value' => 12],
        'ai_logos' => ['mode' => 'unlimited', 'value' => null],
    ]))->assertOk()->assertJsonPath('success', true);

    expect(PlanLimitOverride::query()->forTenant($tenant->id)->pluck('value', 'limit')->all())
        ->toEqual(['staff' => 12, 'ai_logos' => null]);

    $audit = AuditEvent::query()->where('action', PlanLimitOverrideService::AUDIT_ACTION)->sole();
    expect($audit->subject_id)->toBe((string) $tenant->id)
        ->and($audit->changes['after'])->toEqual(['ai_logos' => null, 'staff' => 12]);

    $this->putJson($url, limitsPayload())->assertOk();
    expect(PlanLimitOverride::query()->forTenant($tenant->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', PlanLimitOverrideService::AUDIT_ACTION)->count())->toBe(2);
});

it('validates the override values', function (): void {
    $tenant = vendorsTenant();
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.limits.manage']), 'admin');
    $url = $this->centralUrl("/admin/vendors/{$tenant->id}/limits");

    $this->putJson($url, limitsPayload(['staff' => ['mode' => 'custom', 'value' => null]]))->assertJsonValidationErrors('limits.staff.value');
    $this->putJson($url, limitsPayload(['staff' => ['mode' => 'custom', 'value' => -1]]))->assertJsonValidationErrors('limits.staff.value');
    $this->putJson($url, limitsPayload(['staff' => ['mode' => 'forever']]))->assertJsonValidationErrors('limits.staff.mode');
    $this->putJson($url, ['limits' => ['nope' => ['mode' => 'plan']]])->assertJsonValidationErrors('limits');
});

it('resolves effective limits: override wins, no override = plan, null override = unlimited', function (): void {
    [$tenant] = subscribedTenant(['products' => 500, 'staff' => 3, 'banners' => null]);
    $other = vendorsTenant();
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'staff', 'value' => 9]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'products', 'value' => null]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'banners', 'value' => 0]);
    PlanLimitOverride::query()->create(['tenant_id' => $other->id, 'limit' => 'ai_calls', 'value' => 7]);

    $limits = app(EffectivePlanLimits::class);

    expect($limits->for(PlanLimit::Staff, $tenant->id))->toBe(9)
        ->and($limits->for(PlanLimit::Products, $tenant->id))->toBeNull()
        ->and($limits->for(PlanLimit::Banners, $tenant->id))->toBe(0)
        ->and($limits->for(PlanLimit::AiCalls, $tenant->id))->toBeNull()
        // no subscription: unlimited unless overridden
        ->and($limits->for(PlanLimit::Staff, $other->id))->toBeNull()
        ->and($limits->for(PlanLimit::AiCalls, $other->id))->toBe(7);

    $rows = collect($limits->rows($tenant->id))->keyBy(fn (array $row) => $row['limit']->value);
    expect($rows['staff']['plan'])->toBe(3)
        ->and($rows['staff']['effective'])->toBe(9)
        ->and($rows['staff']['mode']->value)->toBe('custom')
        ->and($rows['products']['mode']->value)->toBe('unlimited')
        ->and($rows['categories']['mode']->value)->toBe('plan');
});

it('makes the AI quota use the override', function (): void {
    [$tenant] = subscribedTenant(['ai_calls' => 10, 'ai_ads' => 2]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'ai_calls', 'value' => 250]);

    $quota = app(AiQuotaService::class);

    expect($quota->status(PlanLimit::AiCalls, $tenant->id)->max)->toBe(250)
        ->and($quota->status(PlanLimit::AiAds, $tenant->id)->max)->toBe(2);
});

it('makes the staff and banner quotas use the override', function (): void {
    $tenant = $this->createTenant('lim-'.Str::lower(Str::random(6)));
    $plan = Plan::factory()->create(['limits' => ['staff' => 2, 'banners' => 1]]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'staff', 'value' => 15]);
    PlanLimitOverride::query()->create(['tenant_id' => $tenant->id, 'limit' => 'banners', 'value' => null]);

    $this->initializeTenancy($tenant);

    expect(app(VendorStaffService::class)->limit())->toBe(15)
        ->and(app(VendorBannerPageService::class)->quota())->toMatchArray(['max' => null, 'reached' => false]);
});
