<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Product request (ADM-018c) for tests. Tenants are created without the provisioning pipeline (no store database).
 *
 * @extends Factory<ProductRequest>
 */
class ProductRequestFactory extends Factory
{
    protected $model = ProductRequest::class;

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
            'title' => Str::title(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'product_url' => null,
            'status' => ProductRequestStatus::Pending,
            'priority' => RequestPriority::Normal,
            'submitted_by' => fake()->name(),
        ];
    }
}
