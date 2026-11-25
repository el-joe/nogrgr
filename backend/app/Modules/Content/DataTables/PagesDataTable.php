<?php

namespace App\Modules\Content\DataTables;

use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin static pages list (ADM-082): filter status / target, search title.
 *
 * @extends BaseDataTable<Page>
 */
final class PagesDataTable extends BaseDataTable
{
    public function __construct(private readonly PageRepositoryContract $pages) {}

    protected function query(): Builder
    {
        return $this->pages->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $target = $filters['target'] ?? null;
        $search = $filters['search'] ?? null;

        return $query
            ->when(is_string($status) && PageStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('status', $status))
            ->when(is_string($target) && PageTarget::tryFrom($target) !== null, fn (Builder $q) => $q->where('target', $target))
            ->when(is_string($search) && $search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('title', 'like', '%'.addcslashes((string) $search, '%_\\').'%')->orWhere('slug', 'like', '%'.addcslashes((string) $search, '%_\\').'%')));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('title', fn (Page $page): string => view('content::admin.pages._title-cell', ['page' => $page])->render())
            ->editColumn('target', fn (Page $page): string => e($page->target->label()))
            ->editColumn('status', fn (Page $page): string => view('content::admin._badge', ['tone' => $page->status->tone(), 'label' => $page->status->label()])->render())
            ->editColumn('updated_at', fn (Page $page): string => '<bdi dir="ltr">'.e((string) $page->updated_at?->format('Y-m-d H:i')).'</bdi>')
            ->addColumn('actions', fn (Page $page): string => view('content::admin.pages._actions', ['page' => $page])->render())
            ->rawColumns(['title', 'status', 'updated_at', 'actions']);
    }
}
