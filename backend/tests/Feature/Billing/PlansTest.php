<?php

use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;

require_once __DIR__.'/BillingHelpers.php';

/*
| Plans (ADM-046 / 046a): permissions, list + data, create / edit validation, money in
| minor units, and the delete guard for plans with subscriptions.
*/

function validPlanPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => ['en' => 'Growth', 'ar' => 'النمو'],
        'description' => ['en' => 'For growing stores', 'ar' => 'للمتاجر النامية'],
        'highlights' => ['en' => "5,000 products\nMobile app", 'ar' => "5,000 منتج\nتطبيق الجوال"],
        'icon' => 'star',
        'status' => 'active',
        'monthly_price' => '59.00',
        'yearly_price' => '588.50',
        'trial_days' => 14,
        'sort_order' => 2,
        'limits' => ['products' => '5000', 'staff' => '5', 'ai_calls' => ''],
        'features' => ['ai_translation' => '1', 'mobile_app' => '1', 'custom_themes' => '0'],
    ], $overrides);
}

it('redirects guests and forbids admins without plan permissions', function (): void {
    $plan = Plan::factory()->create();

    $this->get($this->centralUrl('/admin/plans'))->assertRedirect($this->centralUrl('/admin/login'));

    $admin = billingAdmin([]);
    $this->actingAs($admin, 'admin');

    $this->getJson($this->centralUrl('/admin/plans'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/plans/data'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/plans/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/plans'), validPlanPayload())->assertForbidden();
    $this->getJson($this->centralUrl("/admin/plans/{$plan->id}/edit"))->assertForbidden();
    $this->putJson($this->centralUrl("/admin/plans/{$plan->id}"), validPlanPayload())->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/plans/{$plan->id}"))->assertForbidden();
});

it('lets plans.view list but not manage', function (): void {
    Plan::factory()->create();
    $this->actingAs(billingAdmin(['plans.view']), 'admin');

    $this->get($this->centralUrl('/admin/plans'))->assertOk()->assertSee('Growth');
    $this->getJson($this->centralUrl('/admin/plans/create'))->assertForbidden();
});

it('renders the plans page with cards, comparison and empty state', function (): void {
    $this->actingAs(billingAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/plans'))->assertOk()->assertSee(__('billing::plans.empty_title'));

    Plan::factory()->create(['limits' => [PlanLimit::Products->value => 5000]]);

    $this->get($this->centralUrl('/admin/plans'))
        ->assertOk()
        ->assertSee(__('billing::plans.comparison'))
        ->assertSee('5,000')
        ->assertSee(route('admin.plans.data'), false);
});

it('serves the plans table with open subscription counts and filters', function (): void {
    $this->actingAs(billingAdmin(), 'admin');
    $tenant = billingTenant();
    $growth = Plan::factory()->create();
    Plan::factory()->inactive()->create(['name' => ['en' => 'Legacy', 'ar' => 'قديمة']]);
    Subscription::factory()->for($growth)->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->for($growth)->status(SubscriptionStatus::Expired)->create(['tenant_id' => $tenant->id]);

    $json = $this->getJson($this->centralUrl('/admin/plans/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsTotal'])->toBe(2);

    $row = collect($json['data'])->firstWhere('id', $growth->id);
    expect($row['open_subscriptions_count'])->toBe('1')
        ->and($row['monthly_price_minor'])->toContain('59.00');

    $filtered = $this->getJson($this->centralUrl('/admin/plans/data?draw=1&start=0&length=10&status=inactive'))->json();
    expect($filtered['recordsFiltered'])->toBe(1);
});

it('renders the create and edit forms', function (): void {
    $this->actingAs(billingAdmin(), 'admin');
    $plan = Plan::factory()->create();

    $this->get($this->centralUrl('/admin/plans/create'))->assertOk()->assertSee(__('billing::plans.create_title'));
    $this->get($this->centralUrl("/admin/plans/{$plan->id}/edit"))->assertOk()->assertSee('النمو')->assertSee('59.00');
});

it('creates a plan with prices in USD minor units, limits and features', function (): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/plans'), validPlanPayload())
        ->assertCreated()
        ->assertJson(['success' => true, 'redirect' => route('admin.plans.index')]);

    $plan = Plan::query()->sole();
    expect($plan->getTranslation('name', 'ar'))->toBe('النمو')
        ->and($plan->monthly_price_minor)->toBe(5900)
        ->and($plan->yearly_price_minor)->toBe(58850)
        ->and($plan->status)->toBe(PlanStatus::Active)
        ->and($plan->trial_days)->toBe(14)
        ->and($plan->limit(PlanLimit::Products))->toBe(5000)
        ->and($plan->limit(PlanLimit::AiCalls))->toBeNull()
        ->and($plan->hasFeature(PlanFeature::AiTranslation))->toBeTrue()
        ->and($plan->hasFeature(PlanFeature::CustomThemes))->toBeFalse()
        ->and($plan->highlightLines())->toBe(['5,000 products', 'Mobile app']);
});

it('validates the plan form', function (array $overrides, string $field): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/plans'), validPlanPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Plan::query()->count())->toBe(0);
})->with([
    'missing arabic name' => [['name' => ['ar' => '']], 'name.ar'],
    'negative price' => [['monthly_price' => '-1'], 'monthly_price'],
    'three decimals' => [['yearly_price' => '1.999'], 'yearly_price'],
    'unknown status' => [['status' => 'archived'], 'status'],
    'unknown icon' => [['icon' => 'not-an-icon'], 'icon'],
    'trial too long' => [['trial_days' => 400], 'trial_days'],
    'negative limit' => [['limits' => ['products' => '-5']], 'limits.products'],
]);

it('updates a plan', function (): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');
    $plan = Plan::factory()->create();

    $this->putJson($this->centralUrl("/admin/plans/{$plan->id}"), validPlanPayload(['name' => ['en' => 'Pro'], 'monthly_price' => '149', 'status' => 'inactive']))
        ->assertOk()
        ->assertJson(['success' => true]);

    $plan->refresh();
    expect($plan->getTranslation('name', 'en'))->toBe('Pro')
        ->and($plan->monthly_price_minor)->toBe(14900)
        ->and($plan->status)->toBe(PlanStatus::Inactive);
});

it('deletes an unused plan', function (): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');
    $plan = Plan::factory()->create();

    $this->deleteJson($this->centralUrl("/admin/plans/{$plan->id}"))->assertOk()->assertJson(['success' => true]);

    expect(Plan::query()->count())->toBe(0);
});

it('refuses to delete a plan with active subscriptions', function (SubscriptionStatus $status): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');
    $plan = Plan::factory()->create();
    Subscription::factory()->for($plan)->status($status)->create(['tenant_id' => billingTenant()->id]);

    $this->deleteJson($this->centralUrl("/admin/plans/{$plan->id}"))
        ->assertUnprocessable()
        ->assertJson(['success' => false]);

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
})->with([SubscriptionStatus::Trial, SubscriptionStatus::Active, SubscriptionStatus::PastDue]);

it('refuses to delete a plan with past subscriptions', function (): void {
    $this->actingAs(billingAdmin(['plans.manage']), 'admin');
    $plan = Plan::factory()->create();
    Subscription::factory()->for($plan)->status(SubscriptionStatus::Expired)->create(['tenant_id' => billingTenant()->id]);

    $this->deleteJson($this->centralUrl("/admin/plans/{$plan->id}"))
        ->assertUnprocessable()
        ->assertJson(['message' => __('billing::plans.has_history')]);
});

it('renders every billing page in Arabic (RTL)', function (): void {
    $this->actingAs(billingAdmin(), 'admin');
    $tenant = billingTenant();
    $plan = Plan::factory()->create();
    Subscription::factory()->for($plan)->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
    $log = PaymentLog::factory()->create(['tenant_id' => $tenant->id]);

    foreach ([
        '/admin/plans' => 'مقارنة الباقات',
        '/admin/plans/create' => 'باقة جديدة',
        "/admin/plans/{$plan->id}/edit" => 'الحدود',
        '/admin/billing/subscriptions' => 'الإيراد الشهري المتكرر',
        '/admin/finance/invoices' => 'الفواتير',
        "/admin/finance/invoices/{$invoice->id}" => 'فاتورة إلى',
        '/admin/finance/payment-logs' => 'سجلات الدفع',
        "/admin/finance/payment-logs/{$log->id}" => 'تغيير الحالة',
    ] as $path => $text) {
        $this->get($this->centralUrl($path.'?lang=ar'))->assertOk()->assertSee('dir="rtl"', false)->assertSee($text);
    }
});
