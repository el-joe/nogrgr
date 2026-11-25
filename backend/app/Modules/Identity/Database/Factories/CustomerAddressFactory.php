<?php

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tenant DB. Pass a real central country: ->create(['country_id' => $country->id]).
 *
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => 'Home',
            'full_name' => fake()->name(),
            'email' => null,
            'phone' => '+966501234567',
            'phone_country' => 'SA',
            'address_line_1' => fake()->streetAddress(),
            'city' => 'Riyadh',
            'state' => null,
            'country_id' => 1,
            'latitude' => null,
            'longitude' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
