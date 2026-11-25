<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Product edit request (ADM-018a) for tests; prefer EditRequestService::submit() for real diffs. Tenants are created without the provisioning pipeline (no store database).
 *
 * @extends Factory<EditRequest>
 */
class EditRequestFactory extends Factory
{
    protected $model = EditRequest::class;

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
            'product_id' => static fn (): int => Product::factory()->create()->id,
            'variant_id' => null,
            'changes' => [
                ['field' => 'name', 'locale' => 'en', 'current' => 'Wireless Earbuds Pro', 'requested' => 'Megz Earbuds Pro'],
            ],
            'files' => null,
            'vendor_note' => fake()->sentence(),
            'submitted_by' => fake()->name(),
            'status' => EditRequestStatus::Pending,
        ];
    }
}
