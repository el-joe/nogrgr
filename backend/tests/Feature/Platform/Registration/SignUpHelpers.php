<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Testing\TestResponse;

/*
| Shared set-up for the central sign-up wizard tests (WEB-016 … 019b, OWN-007).
*/

if (! function_exists('signUpPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function signUpPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'Founder@Example.com',
            'phone' => '0501234567',
            'phone_country' => 'sa',
            'compliance' => '1',
        ], $overrides);
    }

    /**
     * An active registration gateway (Stripe or PayPal) in test mode.
     *
     * @param  array<string, string>  $credentials
     */
    function registrationGateway(PaymentGatewayCode $code = PaymentGatewayCode::Stripe, array $credentials = [], int $sort = 0): PaymentGateway
    {
        $gateway = PaymentGateway::factory()->create(['code' => $code, 'is_active' => true, 'sort_order' => $sort, 'fee_percent_bps' => 290, 'fee_fixed_minor' => 30]);

        $gateway->configs()->create([
            'purpose' => GatewayPurpose::TenantRegistration,
            'is_enabled' => true,
            'mode' => GatewayMode::Test,
            'credentials' => $credentials !== [] ? $credentials : match ($code) {
                PaymentGatewayCode::Paypal => ['client_id' => 'pp-client', 'client_secret' => 'pp-secret', 'webhook_id' => 'WH-123'],
                default => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret'],
            },
        ]);

        return $gateway->load('configs');
    }

    function paidPlan(int $trialDays = 0): Plan
    {
        return Plan::factory()->create(['trial_days' => $trialDays, 'monthly_price_minor' => 9900, 'yearly_price_minor' => 99000]);
    }

    /**
     * Sign up and choose a plan in one go (returns the plan-step response).
     */
    function signUpAndChoose(Plan $plan, string $choice, string $term = 'monthly', array $signUp = []): TestResponse
    {
        test()->post(route('central.register.store'), signUpPayload($signUp))->assertRedirect(route('central.register.plan'));

        return test()->post(route('central.register.plan.store'), ['plan_id' => $plan->id, 'term' => $term, 'choice' => $choice]);
    }

    function stripeSignature(string $payload, string $secret = 'whsec_test_secret', ?int $time = null): string
    {
        $time ??= time();

        return 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret);
    }
}
