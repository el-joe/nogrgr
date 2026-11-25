<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @extends BaseRepository<Owner>
 */
class OwnerRepository extends BaseRepository implements OwnerRepositoryContract
{
    protected function model(): string
    {
        return Owner::class;
    }

    public function findByEmail(string $email): ?Owner
    {
        return Owner::query()->where('email', Str::lower(trim($email)))->first();
    }

    public function findBySocialId(SocialProvider $provider, string $id): ?Owner
    {
        return Owner::query()->where($provider->idColumn(), $id)->first();
    }

    public function stores(Owner $owner): Collection
    {
        return Tenant::query()->where('owner_id', $owner->id)->latest('id')->get();
    }
}
