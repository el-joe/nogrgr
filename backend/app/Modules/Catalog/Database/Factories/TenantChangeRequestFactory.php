<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Store change request (ADM-018i) for tests. Tenants are created without the provisioning pipeline (no store database).
 *
 * @extends Factory<TenantChangeRequest>
 */
class TenantChangeRequestFactory extends Factory
{
    protected $model = TenantChangeRequest::class;

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
            'type' => TenantChangeType::Countries,
            'requested_ids' => [1, 2],
            'current_ids' => [1],
            'vendor_note' => null,
            'submitted_by' => fake()->name(),
            'status' => TenantChangeStatus::Pending,
        ];
    }
}
