<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
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

    public function listing(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $type = (string) ($filters['type'] ?? '');

        return $this->query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
                $query->where(fn (Builder $inner) => $inner->where('slug', 'like', $like)->orWhereRaw('LOWER(CAST(title AS CHAR)) LIKE ?', [$like]));
            })
            ->when(in_array($status, ['published', 'draft'], true), fn (Builder $query) => $query->where('is_active', $status === 'published'))
            ->when(in_array($type, ['system', 'custom'], true), fn (Builder $query) => $query->where('type', $type));
    }
}
