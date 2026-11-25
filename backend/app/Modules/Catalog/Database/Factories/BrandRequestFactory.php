<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Brand request (ADM-018g) for tests. Tenants are created without the provisioning pipeline (no store database).
 *
 * @extends Factory<BrandRequest>
 */
class BrandRequestFactory extends Factory
{
    protected $model = BrandRequest::class;

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
            'brand_name' => Str::title(fake()->words(2, true)),
            'description' => fake()->sentence(12),
            'status' => BrandRequestStatus::Pending,
            'submitted_by' => fake()->name(),
        ];
    }
}
