<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;

require_once __DIR__.'/BillingHelpers.php';

/*
| Subscriptions admin (ADM-047): permissions, list + filters, CSV export, store picker,
| assign plan manually, renew and cancel.
*/

it('protects every subscriptions endpoint', function (): void {
    $tenant = billingTenant();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    $this->get($this->centralUrl('/admin/billing/subscriptions'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(billingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/billing/subscriptions'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/billing/subscriptions/data'))->assertForbidden();
    $this->get($this->centralUrl('/admin/billing/subscriptions/export'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/billing/subscriptions/tenants'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/billing/subscriptions/assign'))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$subscription->id}/renew"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$subscription->id}/cancel"))->assertForbidden();
});

it('keeps view-only admins away from export and actions', function (): void {
    $subscription = Subscription::factory()->create(['tenant_id' => billingTenant()->id]);
    $this->actingAs(billingAdmin(['subscriptions.view']), 'admin');

    $this->get($this->centralUrl('/admin/billing/subscriptions'))->assertOk()->assertDontSee(__('billing::subscriptions.assign_submit'));
    $this->get($this->centralUrl('/admin/billing/subscriptions/export'))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$subscription->id}/cancel"))->assertForbidden();
});

it('renders the subscriptions page with KPIs and tabs', function (): void {
    $this->actingAs(billingAdmin(), 'admin');
    $tenant = billingTenant();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'amount_minor' => 5900]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'term' => 'yearly', 'amount_minor' => 58800]);

    $this->get($this->centralUrl('/admin/billing/subscriptions'))
        ->assertOk()
        ->assertSee(__('billing::subscriptions.kpi.mrr'))
        ->assertSee('$108.00')
        ->assertSee(route('admin.billing.subscriptions.data'), false);
});

it('lists subscriptions with status and plan filters and an invoice link', function (): void {
    $this->actingAs(billingAdmin(), 'admin');
    $tenant = billingTenant('Desert Rose');
    $pro = Plan::factory()->create(['name' => ['en' => 'Pro', 'ar' => 'احترافية']]);
    $active = Subscription::factory()->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->for($pro)->trial()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'billable_type' => 'subscription', 'billable_id' => $active->id]);

    $all = $this->getJson($this->centralUrl('/admin/billing/subscriptions/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($all['recordsTotal'])->toBe(2);

    $row = collect($all['data'])->firstWhere('id', $active->id);
    expect($row['store'])->toContain('Desert Rose')
        ->and($row['actions'])->toContain(route('admin.finance.invoices.show', $invoice->id));

    $trial = $this->getJson($this->centralUrl('/admin/billing/subscriptions/data?draw=1&start=0&length=10&status=trial'))->json();
    expect($trial['recordsFiltered'])->toBe(1)
        ->and($trial['data'][0]['kind'])->toBe('Trial');

    $byPlan = $this->getJson($this->centralUrl("/admin/billing/subscriptions/data?draw=1&start=0&length=10&plan_id={$pro->id}"))->json();
    expect($byPlan['recordsFiltered'])->toBe(1);

    $search = $this->getJson($this->centralUrl('/admin/billing/subscriptions/data?draw=1&start=0&length=10&search[value]='.urlencode($invoice->number)))->json();
    expect($search['recordsFiltered'])->toBe(1);
});

it('exports the filtered subscriptions as CSV', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.view', 'subscriptions.export']), 'admin');
    $tenant = billingTenant('Oud & Co');
    Subscription::factory()->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->status(SubscriptionStatus::Cancelled)->create(['tenant_id' => billingTenant('GearUp')->id]);

    $response = $this->get($this->centralUrl('/admin/billing/subscriptions/export?status=active'))->assertOk();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toContain('Oud & Co')
        ->and($csv)->toContain('59.00')
        ->and($csv)->not->toContain('GearUp');
});

it('searches stores for the assign picker', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.manage']), 'admin');
    billingTenant('Megz Store');
    billingTenant('Nile Crafts');

    $this->getJson($this->centralUrl('/admin/billing/subscriptions/tenants?q=nile'))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('pagination.more', false);
});

it('assigns a plan manually with a paid invoice', function (): void {
    $admin = billingAdmin(['subscriptions.manage']);
    $this->actingAs($admin, 'admin');
    $tenant = billingTenant();
    $plan = Plan::factory()->withTrial()->create();
    $old = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson($this->centralUrl('/admin/billing/subscriptions/assign'), [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'term' => 'yearly',
        'mark_paid' => '1',
    ])->assertCreated()->assertJson(['success' => true]);

    $subscription = Subscription::query()->latest('id')->first();
    $invoice = $subscription->invoices()->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->amount_minor)->toBe(58800)
        ->and($subscription->assigned_by_admin_id)->toBe($admin->id)
        ->and($subscription->current_period_ends_at->isSameDay(now()->addYear()))->toBeTrue()
        ->and($old->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->type)->toBe(InvoiceType::Subscription)
        ->and($invoice->total_minor)->toBe(58800)
        ->and($invoice->payment_method)->toBe('manual');
});

it('can leave the assigned plan invoice unpaid', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.manage']), 'admin');
    $tenant = billingTenant();
    $plan = Plan::factory()->create();

    $this->postJson($this->centralUrl('/admin/billing/subscriptions/assign'), [
        'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'term' => 'monthly', 'mark_paid' => '0',
    ])->assertCreated();

    expect(Invoice::query()->sole()->status)->toBe(InvoiceStatus::Unpaid);
});

it('validates the assign plan form', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.manage']), 'admin');
    $inactive = Plan::factory()->inactive()->create();

    $this->postJson($this->centralUrl('/admin/billing/subscriptions/assign'), [
        'tenant_id' => 999999, 'plan_id' => $inactive->id, 'term' => 'weekly',
    ])->assertUnprocessable()->assertJsonValidationErrors(['tenant_id', 'plan_id', 'term']);
});

it('renews and cancels a subscription from the list', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.manage']), 'admin');
    $subscription = Subscription::factory()->create(['tenant_id' => billingTenant()->id]);
    $end = $subscription->current_period_ends_at->copy();

    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$subscription->id}/renew"))->assertOk()->assertJson(['success' => true]);

    $subscription->refresh();
    expect($subscription->renewals_count)->toBe(1)
        ->and($subscription->current_period_ends_at->isSameDay($end->copy()->addMonthNoOverflow()))->toBeTrue()
        ->and($subscription->invoices()->sole()->type)->toBe(InvoiceType::Renewal);

    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$subscription->id}/cancel"))->assertOk();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('refuses invalid subscription transitions', function (): void {
    $this->actingAs(billingAdmin(['subscriptions.manage']), 'admin');
    $expired = Subscription::factory()->status(SubscriptionStatus::Expired)->create(['tenant_id' => billingTenant()->id]);

    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$expired->id}/cancel"))->assertUnprocessable()->assertJson(['success' => false]);
    $this->postJson($this->centralUrl("/admin/billing/subscriptions/{$expired->id}/renew"))->assertUnprocessable();
});
