<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StorePage>
 */
class StorePageRepository extends BaseRepository implements StorePageRepositoryContract
{
    protected function model(): string
    {
        return StorePage::class;
    }

    public function findBySlug(string $slug, bool $activeOnly = false): ?StorePage
    {
        return $this->query()->where('slug', $slug)->when($activeOnly, fn ($query) => $query->where('is_active', true))->first();
    }

    public function published(bool $footerOnly = false): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->when($footerOnly, fn ($query) => $query->where('show_in_footer', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function slugs(): array
    {
        return $this->query()->pluck('slug')->map(static fn ($slug): string => (string) $slug)->all();
    }
}
