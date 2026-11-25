<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentLog>
 */
class PaymentLogFactory extends Factory
{
    protected $model = PaymentLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => PaymentGateway::Stripe,
            'purpose' => PaymentPurpose::Renewal,
            'status' => PaymentLogStatus::Pending,
            'amount_minor' => 14900,
            'currency' => 'USD',
            'gateway_reference' => 'pi_'.Str::random(16),
            'payload' => ['event' => 'charge.pending', 'amount' => 14900, 'currency' => 'usd'],
        ];
    }

    public function status(PaymentLogStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
