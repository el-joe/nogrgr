<?php

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => PaymentGatewayCode::Stripe,
            'is_active' => false,
            'fee_percent_bps' => 0,
            'fee_fixed_minor' => 0,
            'sort_order' => 0,
        ];
    }
}
