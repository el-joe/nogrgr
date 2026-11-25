<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => 'Growth', 'ar' => 'النمو'],
            'description' => ['en' => 'For growing stores', 'ar' => 'للمتاجر النامية'],
            'highlights' => ['en' => "5,000 products\nMobile app", 'ar' => "5,000 منتج\nتطبيق الجوال"],
            'icon' => 'star',
            'status' => PlanStatus::Active,
            'monthly_price_minor' => 5900,
            'yearly_price_minor' => 58800,
            'currency' => 'USD',
            'trial_days' => 0,
            'sort_order' => 0,
            'limits' => [PlanLimit::Products->value => 5000, PlanLimit::Staff->value => 5, PlanLimit::AiCalls->value => null],
            'features' => [PlanFeature::MobileApp->value],
        ];
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(['trial_days' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => PlanStatus::Inactive]);
    }
}
