<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Repositories\Contracts\StoreSocialLinkRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreSocialLink>
 */
class StoreSocialLinkRepository extends BaseRepository implements StoreSocialLinkRepositoryContract
{
    protected function model(): string
    {
        return StoreSocialLink::class;
    }

    public function ordered(bool $activeOnly = false): Collection
    {
        return $this->query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function usedNetworks(): array
    {
        return $this->query()->pluck('network')->map(static fn ($network): string => $network instanceof BackedEnum ? (string) $network->value : (string) $network)->all();
    }

    public function nextSortOrder(): int
    {
        return (int) $this->query()->max('sort_order') + 1;
    }
}
