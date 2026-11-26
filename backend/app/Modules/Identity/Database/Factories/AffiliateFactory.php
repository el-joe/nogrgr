<?php

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
{
    protected $model = Affiliate::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => AccountStatus::Active,
            'code' => Str::upper(Str::random(10)),
            'type' => AffiliateType::Independent,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => AccountStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => AccountStatus::Suspended]);
    }
}
