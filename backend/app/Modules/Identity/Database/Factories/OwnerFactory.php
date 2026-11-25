<?php

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Owner>
 */
class OwnerFactory extends Factory
{
    protected $model = Owner::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'google_id' => null,
            'apple_id' => null,
            'locale' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ];
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => AccountStatus::Suspended]);
    }
}
