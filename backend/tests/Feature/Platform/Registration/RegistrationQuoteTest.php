<?php

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Services\RegistrationFlowService;

/*
| Payment-step order summary (WEB-016c): the plan price for the term plus the platform
| tax, in USD minor units, matching the invoice issued at provisioning.
*/

it('prices the chosen term with the platform tax', function (int $taxBp, BillingTerm $term, int $subtotal, int $tax, int $total): void {
    config(['billing.tax_rate_bp' => $taxBp]);
    $plan = new Plan(['name' => 'Growth', 'monthly_price_minor' => 2999, 'yearly_price_minor' => 29900, 'currency' => 'USD']);

    $quote = app(RegistrationFlowService::class)->quote($plan, $term);

    expect($quote->subtotal->minor)->toBe($subtotal)
        ->and($quote->tax->minor)->toBe($tax)
        ->and($quote->total->minor)->toBe($total)
        ->and($quote->total->currency)->toBe('USD')
        ->and($quote->requiresPayment())->toBeTrue();
})->with([
    'monthly, no tax' => [0, BillingTerm::Monthly, 2999, 0, 2999],
    'monthly, 15 %' => [1500, BillingTerm::Monthly, 2999, 450, 3449],
    'yearly, 15 %' => [1500, BillingTerm::Yearly, 29900, 4485, 34385],
    'yearly, 5 %' => [500, BillingTerm::Yearly, 29900, 1495, 31395],
]);

it('needs no payment for a free plan', function (): void {
    $plan = new Plan(['name' => 'Free', 'monthly_price_minor' => 0, 'yearly_price_minor' => 0, 'currency' => 'USD']);

    expect(app(RegistrationFlowService::class)->quote($plan, BillingTerm::Monthly)->requiresPayment())->toBeFalse();
});
