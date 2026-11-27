<?php

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Events\GatewayLimitBlocked;
use App\Modules\Integrations\Events\GatewayLimitWarning;
use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Services\Limits\GatewayLimitService;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/IntegrationsHelpers.php';

/*
| ADM-100c gateway limits (decision #124): per gateway the monthly volume a store may
| take through the platform account; warning at the set %, block at 100 % (once per
| store × gateway × month), checkout hides a blocked gateway, admin unblock.
*/

beforeEach(function (): void {
    $this->gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'monthly_store_limit_minor' => 100_00, 'limit_warn_percent' => 80]);
    $this->tenant = $this->createTenant('limits');
});

it('guards the limits tab with the integrations permissions', function (): void {
    $this->get($this->centralUrl('admin/integrations/gateways/limits'))->assertRedirect();
    $this->actingAs(integrationsAdmin(), 'admin')->get($this->centralUrl('admin/integrations/gateways/limits'))->assertForbidden();
    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')->putJson($this->centralUrl('admin/integrations/gateways/limits'), [])->assertForbidden();

    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')->get($this->centralUrl('admin/integrations/gateways/limits'))
        ->assertOk()
        ->assertSee(__('integrations::admin.limits.title'))
        ->assertSee(route('admin.integrations.gateways.limits.data'), false);
});

it('saves the limits of every gateway', function (): void {
    $admin = integrationsAdmin('integrations.view', 'integrations.manage');

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('admin/integrations/gateways/limits'), ['limits' => ['stripe' => ['limit' => '250.50', 'warn_percent' => 120]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('limits.stripe.warn_percent');

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('admin/integrations/gateways/limits'), ['limits' => ['stripe' => ['limit' => '250.50', 'warn_percent' => 75]]])->assertOk();

    expect($this->gateway->fresh()->monthly_store_limit_minor)->toBe(25050)
        ->and($this->gateway->fresh()->limit_warn_percent)->toBe(75);

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('admin/integrations/gateways/limits'), ['limits' => ['stripe' => ['limit' => '', 'warn_percent' => 80]]])->assertOk();
    expect($this->gateway->fresh()->monthly_store_limit_minor)->toBeNull();
});

it('warns at the set percent and blocks at the limit once a month', function (): void {
    Event::fake([GatewayLimitWarning::class, GatewayLimitBlocked::class]);
    $limits = app(GatewayLimitService::class);

    $limits->record($this->tenant->id, PaymentGatewayCode::Stripe, 50_00);
    Event::assertNotDispatched(GatewayLimitWarning::class);

    $limits->record($this->tenant->id, PaymentGatewayCode::Stripe, 35_00);   // 85 %
    $limits->record($this->tenant->id, PaymentGatewayCode::Stripe, 5_00);    // 90 %: no second warning
    Event::assertDispatchedTimes(GatewayLimitWarning::class, 1);
    expect($limits->isBlocked($this->tenant->id, PaymentGatewayCode::Stripe))->toBeFalse();

    $limits->record($this->tenant->id, PaymentGatewayCode::Stripe, 20_00);   // 110 %
    $limits->record($this->tenant->id, PaymentGatewayCode::Stripe, 1_00);
    Event::assertDispatchedTimes(GatewayLimitBlocked::class, 1);
    expect($limits->isBlocked($this->tenant->id, PaymentGatewayCode::Stripe))->toBeTrue()
        ->and(GatewayLimitUsage::query()->sole()->volume_minor)->toBe(111_00);
});

it('only records the volume of gateways without a limit', function (): void {
    Event::fake([GatewayLimitWarning::class, GatewayLimitBlocked::class]);
    $this->gateway->update(['monthly_store_limit_minor' => null]);

    app(GatewayLimitService::class)->record($this->tenant->id, PaymentGatewayCode::Stripe, 1_000_00);

    Event::assertNothingDispatched();
    expect(app(GatewayLimitService::class)->isBlocked($this->tenant->id, PaymentGatewayCode::Stripe))->toBeFalse();
});

it('lists this month usage and lets a manager unblock a store', function (): void {
    Event::fake([GatewayLimitWarning::class, GatewayLimitBlocked::class]);
    app(GatewayLimitService::class)->record($this->tenant->id, PaymentGatewayCode::Stripe, 150_00);
    $usage = GatewayLimitUsage::query()->sole();

    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')
        ->getJson($this->centralUrl('admin/integrations/gateways/limits/data?draw=1&start=0&length=10'))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);

    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')->postJson($this->centralUrl('admin/integrations/gateways/limits/'.$usage->id.'/unblock'))->assertForbidden();
    $this->actingAs(integrationsAdmin('integrations.view', 'integrations.manage'), 'admin')->postJson($this->centralUrl('admin/integrations/gateways/limits/'.$usage->id.'/unblock'))->assertOk();

    expect($usage->fresh()->blocked_at)->toBeNull()
        ->and(app(GatewayLimitService::class)->isBlocked($this->tenant->id, PaymentGatewayCode::Stripe))->toBeFalse();

    // The unblock holds for the rest of the month.
    app(GatewayLimitService::class)->record($this->tenant->id, PaymentGatewayCode::Stripe, 10_00);
    expect(app(GatewayLimitService::class)->isBlocked($this->tenant->id, PaymentGatewayCode::Stripe))->toBeFalse();
});
