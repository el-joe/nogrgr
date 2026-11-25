<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ProductLabel>
 */
class ProductLabelRepository extends BaseRepository implements ProductLabelRepositoryContract
{
    protected function model(): string
    {
        return ProductLabel::class;
    }

    public function ordered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function nextSortOrder(): int
    {
        $max = $this->query()->max('sort_order');

        return $max === null ? 0 : (int) $max + 1;
    }

    public function ids(): array
    {
        return array_map('intval', $this->query()->pluck('id')->all());
    }

    public function saveOrder(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            $this->query()->whereKey($id)->update(['sort_order' => $position]);
        }
    }
}
