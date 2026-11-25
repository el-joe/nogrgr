<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * tenant_id must be given (tenants are provisioned by the test helpers).
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'term' => BillingTerm::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount_minor' => 5900,
            'currency' => 'USD',
            'current_period_starts_at' => now()->subDays(5),
            'current_period_ends_at' => now()->addDays(25),
            'renewals_count' => 0,
            'auto_renew' => true,
        ];
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function trial(int $daysLeft = 7): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays($daysLeft),
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
        ]);
    }
}
