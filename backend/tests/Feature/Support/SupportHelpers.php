<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Support\DTOs\OpenTicketData;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Services\SupportTicketService;

/*
| Shared setup of the Support tests (ADM-116, VEN-091, VEN-092, OWN-009, MAIL-037).
*/

if (! function_exists('supportAdmin')) {
    /**
     * @param  list<string>|null  $permissions  null = super-admin
     */
    function supportAdmin(?array $permissions = null, array $attributes = []): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create($attributes);

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }

    /**
     * A ticket opened through the service (first message, reference, events) for a store.
     */
    function supportTicket(?Tenant $tenant, string $subject = 'Tabby not showing at checkout', ?TicketActor $actor = null, ?int $ownerId = null, TicketPriority $priority = TicketPriority::Normal): SupportTicket
    {
        $actor ??= new TicketActor(TicketAuthor::Vendor, 1, 'Mona Khaled', 'mona@example.test');

        return app(SupportTicketService::class)->open(new OpenTicketData(
            actor: $actor,
            tenantId: $tenant?->id,
            ownerId: $ownerId,
            subject: $subject,
            category: TicketCategory::Payments,
            priority: $priority,
            body: 'Customers in KSA do not see Tabby at checkout since this morning.',
        ));
    }

    /**
     * Store staff with only the given tenant permissions.
     *
     * @param  list<string>  $permissions
     */
    function supportStaff(Tenant $tenant, array $permissions): VendorStaff
    {
        return $tenant->run(function () use ($permissions): VendorStaff {
            $staff = VendorStaff::factory()->create();
            $staff->givePermissionTo(['dashboard.view', ...$permissions]);

            return $staff;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    function supportRows(string $url): array
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return test()->getJson($url.$separator.'draw=1&start=0&length=50')->assertOk()->json('data');
    }
}
