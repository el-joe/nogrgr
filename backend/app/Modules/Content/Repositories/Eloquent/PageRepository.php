<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Page>
 */
class PageRepository extends BaseRepository implements PageRepositoryContract
{
    protected function model(): string
    {
        return Page::class;
    }

    public function findPublicBySlug(string $slug): ?Page
    {
        return $this->publicQuery()->where('slug', $slug)->first();
    }

    public function publicPages(): Collection
    {
        return $this->publicQuery()->orderBy('id')->get();
    }

    /**
     * @return Builder<Page>
     */
    private function publicQuery(): Builder
    {
        return Page::query()
            ->where('status', PageStatus::Active->value)
            ->whereIn('target', [PageTarget::Central->value, PageTarget::Both->value]);
    }
}
