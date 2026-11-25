<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorRegistration>
 */
class VendorRegistrationFactory extends Factory
{
    protected $model = VendorRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9665'.fake()->numerify('########'),
            'locale' => 'en',
            'term' => BillingTerm::Monthly,
            'status' => RegistrationStatus::EmailUnverified,
            'owner_name' => fake()->name(),
            'store_name' => fake()->company(),
            'slug' => 'reg-'.Str::lower(Str::random(8)),
            'expires_at' => now()->addDays(3),
        ];
    }

    public function status(RegistrationStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
