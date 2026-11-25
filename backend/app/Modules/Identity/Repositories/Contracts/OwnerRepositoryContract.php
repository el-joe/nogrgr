<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store owners (central DB).
 *
 * @extends RepositoryContract<Owner>
 */
interface OwnerRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?Owner;

    public function findBySocialId(SocialProvider $provider, string $id): ?Owner;

    /**
     * The owner's stores, newest first.
     *
     * @return Collection<int, Tenant>
     */
    public function stores(Owner $owner): Collection;
}
