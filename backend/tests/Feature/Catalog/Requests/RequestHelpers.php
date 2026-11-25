<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

require_once __DIR__.'/../CatalogHelpers.php';

/*
| Shared helpers for the Requests center feature tests (required by each test file).
*/

if (! function_exists('requestsStore')) {
    /**
     * Central store row (no store database) owned by an Owner account.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Tenant, 1: Owner}
     */
    function requestsStore(array $attributes = []): array
    {
        $owner = Owner::factory()->create();
        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'name' => 'Megz Store',
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'owner_email' => $owner->email,
        ], $attributes)));

        return [$tenant, $owner];
    }
}
