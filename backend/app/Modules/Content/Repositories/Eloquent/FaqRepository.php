<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Models\Faq;
use App\Modules\Content\Repositories\Contracts\FaqRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Faq>
 */
class FaqRepository extends BaseRepository implements FaqRepositoryContract
{
    protected function model(): string
    {
        return Faq::class;
    }

    public function ordered(bool $activeOnly = false): Collection
    {
        return Faq::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            Faq::query()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }

    public function nextSortOrder(): int
    {
        return (int) Faq::query()->max('sort_order') + 1;
    }
}
