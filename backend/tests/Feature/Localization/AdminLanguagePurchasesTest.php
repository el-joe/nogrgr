<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Models\Language;
use App\Modules\Platform\Models\Tenant;

/*
| Admin › Localization › Language purchases (ADM-094): Billing add-on purchases of
| languages / AI translation with gateway, type and status filters and the detail page.
*/

function purchasesAdmin(?string $permission = 'localization.purchases.view'): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_filter(['dashboard.view', $permission])));

    return $admin;
}

/**
 * A purchase with its invoice and (optionally) a payment attempt.
 */
function languagePurchase(Tenant $tenant, string $code, ?PaymentGateway $gateway, array $attributes = []): AddonPurchase
{
    $purchase = AddonPurchase::factory()->create(array_merge(['tenant_id' => $tenant->id, 'item_code' => $code], $attributes));
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => InvoiceType::forAddon($purchase->type),
        'billable_type' => 'addon_purchase',
        'billable_id' => $purchase->id,
        'number' => 'INV-LP-'.$purchase->id,
    ]);

    if ($gateway !== null) {
        PaymentLog::factory()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'gateway' => $gateway,
            'purpose' => PaymentPurpose::Addon,
            'status' => PaymentLogStatus::Captured,
            'gateway_reference' => 'ref_'.$purchase->id,
        ]);
    }

    return $purchase;
}

dataset('purchase endpoints', [
    'index' => ['/admin/localization/purchases'],
    'data' => ['/admin/localization/purchases/data'],
    'show' => ['/admin/localization/purchases/{id}'],
]);

it('redirects guests and forbids admins without the permission', function (string $path): void {
    $purchase = languagePurchase($this->createTenant('guestcheck'), 'fr', null);
    $url = $this->centralUrl(str_replace('{id}', (string) $purchase->id, $path));

    $this->get($url)->assertRedirect($this->centralUrl('/admin/login'));
    $this->actingAs(purchasesAdmin('localization.languages.view'), 'admin')->getJson($url)->assertForbidden();
})->with('purchase endpoints');

it('renders the purchases tab with filters', function (): void {
    $this->actingAs(purchasesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/purchases'))
        ->assertOk()
        ->assertSee(__('localization::purchases.tab'))
        ->assertSee('data-datatable', false)
        ->assertSee('name="gateway"', false)
        ->assertDontSee(route('admin.localization.languages.index', ['tab' => 'all']), false);
});

it('lists language and AI translation purchases with gateway, type, status and search filters', function (): void {
    Language::factory()->code('fr')->create(['name' => ['en' => 'French', 'ar' => 'الفرنسية']]);
    $megz = $this->createTenant('megz');
    $lina = $this->createTenant('lina');
    $paid = languagePurchase($megz, 'fr', PaymentGateway::Stripe, ['status' => AddonPurchaseStatus::Paid, 'paid_at' => now()]);
    languagePurchase($lina, 'tr', PaymentGateway::Paypal);
    languagePurchase($lina, 'fr', null, ['type' => AddonType::AiTranslation]);
    $admin = purchasesAdmin();
    $url = fn (array $query): string => $this->centralUrl('/admin/localization/purchases/data?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 10] + $query));

    $this->actingAs($admin, 'admin')->getJson($url([]))->assertOk()->assertJsonPath('recordsTotal', 3);

    $this->actingAs($admin, 'admin')
        ->getJson($url(['gateway' => 'stripe']))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.id', $paid->id)
        ->assertJsonPath('data.0.purchase', 'French')
        ->assertJsonPath('data.0.gateway', PaymentGateway::Stripe->label())
        ->assertJsonPath('data.0.invoice_number', fn (string $html): bool => str_contains($html, 'INV-LP-'.$paid->id));

    $this->actingAs($admin, 'admin')->getJson($url(['type' => 'ai_translation']))->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.purchase', __('localization::purchases.ai_translation_of', ['language' => 'French']));
    $this->actingAs($admin, 'admin')->getJson($url(['status' => 'paid']))->assertJsonPath('recordsFiltered', 1);
    $this->actingAs($admin, 'admin')->getJson($url(['search' => 'Lina']))->assertJsonPath('recordsFiltered', 2);
});

it('shows a purchase with its invoice and payment attempts', function (): void {
    $purchase = languagePurchase($this->createTenant('detailshop'), 'de', PaymentGateway::Moyasar);

    $this->actingAs(purchasesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/purchases/'.$purchase->id))
        ->assertOk()
        ->assertSee(__('localization::purchases.detail_title', ['id' => $purchase->id]))
        ->assertSee('INV-LP-'.$purchase->id)
        ->assertSee('ref_'.$purchase->id)
        ->assertSee(PaymentGateway::Moyasar->label());

    $unpaid = languagePurchase($this->createTenant('nopay'), 'it', null);

    $this->actingAs(purchasesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/purchases/'.$unpaid->id))
        ->assertOk()
        ->assertSee(__('localization::purchases.no_payments'));
});

it('returns 404 for a purchase that is not a language add-on', function (): void {
    $this->actingAs(purchasesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/purchases/999999'))
        ->assertNotFound();
});
