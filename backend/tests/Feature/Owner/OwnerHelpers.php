<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

/*
| Owner portal test helpers: central-only stores (no tenant database) owned by an
| owner, with a subscription / invoice when needed.
*/

if (! function_exists('ownerStore')) {
    function ownerStore(Owner $owner, array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'name' => 'Store '.Str::upper(Str::random(4)),
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
            'owner_id' => $owner->id,
            'owner_name' => $owner->name,
            'owner_email' => $owner->email,
        ], $attributes)));
    }

    function ownerSubscription(Tenant $tenant, array $attributes = []): Subscription
    {
        return Subscription::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::factory()->create(['name' => 'Growth '.Str::random(3)])->id,
        ], $attributes));
    }

    function ownerInvoice(Tenant $tenant, array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge(['tenant_id' => $tenant->id], $attributes));
    }

    /**
     * DataTables JSON rows of an owner list endpoint.
     *
     * @return list<array<string, mixed>>
     */
    function ownerRows(string $route, array $query = []): array
    {
        return test()->getJson(route($route, ['draw' => 1, 'start' => 0, 'length' => 50, ...$query]))->assertOk()->json('data');
    }
}
