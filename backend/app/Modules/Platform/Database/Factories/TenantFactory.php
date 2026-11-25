<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Note: creating a tenant fires the provisioning pipeline (create + migrate + seed DB).
 * In tests prefer the InteractsWithTenancy helper, which also cleans up the database.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
        ];
    }
}
