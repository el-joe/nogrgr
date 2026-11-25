<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\AddonPurchasePaid;
use App\Modules\Billing\Events\LanguagePurchasePaid;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Services\AddonPurchaseService;
use App\Modules\Billing\Services\InvoiceService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/BillingHelpers.php';

/*
| Add-on purchases (decision #45): a paid language creates a pending purchase + invoice;
| paying the invoice settles it and fires LanguagePurchasePaid for the AI translation run.
*/

it('creates a language purchase with its invoice, idempotently', function (): void {
    $tenant = billingTenant();
    $service = app(AddonPurchaseService::class);

    $purchase = $service->purchaseLanguage($tenant->id, 7, 'FR', 'French', Money::of('49.00'), 'Megz Store');
    $again = $service->purchaseLanguage($tenant->id, 7, 'fr', 'French', Money::of('49.00'));

    $invoice = $service->invoiceFor($purchase);

    expect($again->id)->toBe($purchase->id)
        ->and($purchase->type)->toBe(AddonType::Language)
        ->and($purchase->item_code)->toBe('fr')
        ->and($purchase->status)->toBe(AddonPurchaseStatus::PendingPayment)
        ->and($purchase->meta['language_id'])->toBe(7)
        ->and($invoice->type)->toBe(InvoiceType::LanguageAddon)
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->total_minor)->toBe(4900)
        ->and($invoice->items->sole()->description)->toBe('Paid language: French');
});

it('fires LanguagePurchasePaid once the invoice is paid', function (): void {
    Event::fake([AddonPurchasePaid::class, LanguagePurchasePaid::class]);
    $tenant = billingTenant();
    $service = app(AddonPurchaseService::class);
    $purchase = $service->purchaseLanguage($tenant->id, 12, 'de', 'German', Money::of('30'));

    app(InvoiceService::class)->markPaid($service->invoiceFor($purchase), 'stripe');

    expect($purchase->refresh()->status)->toBe(AddonPurchaseStatus::Paid)
        ->and($purchase->paid_at)->not->toBeNull();
    Event::assertDispatched(AddonPurchasePaid::class);
    Event::assertDispatched(LanguagePurchasePaid::class, fn (LanguagePurchasePaid $event): bool => $event->tenantId === $tenant->id
        && $event->languageId === 12
        && $event->languageCode === 'de'
        && $event->addonPurchaseId === $purchase->id);
});

it('refuses free languages and non-USD amounts', function (): void {
    $tenant = billingTenant();
    $service = app(AddonPurchaseService::class);

    expect(fn () => $service->purchaseLanguage($tenant->id, 1, 'ar', 'Arabic', Money::of('10')))->toThrow(BillingRuleException::class)
        ->and(fn () => $service->purchase($tenant->id, AddonType::AiTranslation, 'all', Money::of('10', 'EUR'), 'AI'))->toThrow(BillingRuleException::class);
});

it('cancels an unpaid purchase and voids its invoice', function (): void {
    $service = app(AddonPurchaseService::class);
    $purchase = $service->purchaseLanguage(billingTenant()->id, 3, 'es', 'Spanish', Money::of('20'));

    $service->cancel($purchase);

    expect($purchase->status)->toBe(AddonPurchaseStatus::Cancelled)
        ->and($service->invoiceFor($purchase)->status)->toBe(InvoiceStatus::Void);
});
