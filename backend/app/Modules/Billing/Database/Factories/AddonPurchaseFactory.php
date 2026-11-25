<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonPurchase>
 */
class AddonPurchaseFactory extends Factory
{
    protected $model = AddonPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AddonType::Language,
            'item_code' => 'fr',
            'amount_minor' => 1500,
            'currency' => 'USD',
            'status' => AddonPurchaseStatus::PendingPayment,
            'payment_intent_id' => null,
            'paid_at' => null,
            'meta' => null,
        ];
    }
}
