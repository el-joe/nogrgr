<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\VendorRegistration;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central DB.
 *
 * @extends RepositoryContract<VendorRegistration>
 */
interface VendorRegistrationRepositoryContract extends RepositoryContract
{
    public function findByTokenHash(string $hash): ?VendorRegistration;

    /**
     * Not-completed registrations; filters: status, plan_id, search (e-mail, owner, store, slug).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<VendorRegistration>
     */
    public function pending(array $filters = []): Builder;

    public function countPending(): int;

    /**
     * An open (not completed) registration already reserved this store slug.
     */
    public function slugReserved(string $slug, ?int $exceptId = null): bool;
}
