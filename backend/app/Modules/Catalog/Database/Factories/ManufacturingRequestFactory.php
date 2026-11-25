<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Manufacturing request (ADM-018e) for tests. Tenants are created without the provisioning pipeline (no store database).
 *
 * @extends Factory<ManufacturingRequest>
 */
class ManufacturingRequestFactory extends Factory
{
    protected $model = ManufacturingRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => static fn (): int => Tenant::withoutEvents(static fn (): Tenant => Tenant::query()->create([
                'name' => fake()->company(),
                'slug' => 'store-'.Str::lower(Str::random(10)),
                'status' => 'active',
                'owner_name' => fake()->name(),
                'owner_email' => 'owner-'.Str::lower(Str::random(8)).'@example.com',
            ]))->id,
            'product_name' => Str::title(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'quantity' => 500,
            'status' => ManufacturingRequestStatus::Requested,
            'submitted_by' => fake()->name(),
        ];
    }
}
