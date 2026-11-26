<?php

namespace App\Modules\Storefront\DataTables;

use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Your store › Pages (VEN-033, Figma Vendor Panel 85:106165): page (title +
 * /pages/slug, "System" badge), status (published / draft switch), footer switch,
 * updated, actions (view on store, edit, delete custom pages).
 * Filters: search, status (published | draft), type (system | custom).
 *
 * @extends BaseDataTable<StorePage>
 */
final class VendorStorePagesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'type'];

    private bool $canManage = false;

    public function __construct(private readonly StorePageRepositoryContract $pages) {}

    public function withAbilities(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->pages->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->pages->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('page', static fn (Builder $query, string $order) => $query->orderBy('sort_order', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('is_active', $direction($order)))
            ->orderColumn('footer', static fn (Builder $query, string $order) => $query->orderBy('show_in_footer', $direction($order)))
            ->orderColumn('updated', static fn (Builder $query, string $order) => $query->orderBy('updated_at', $direction($order))->orderBy('id', $direction($order)))
            ->addColumn('page', static fn (StorePage $page): string => view('storefront::vendor.pages._title-cell', ['page' => $page])->render())
            ->addColumn('status', fn (StorePage $page): string => view('storefront::vendor.pages._flag-cell', ['page' => $page, 'flag' => 'is_active', 'canManage' => $this->canManage])->render())
            ->addColumn('footer', fn (StorePage $page): string => view('storefront::vendor.pages._flag-cell', ['page' => $page, 'flag' => 'show_in_footer', 'canManage' => $this->canManage])->render())
            ->addColumn('updated', static fn (StorePage $page): string => e($page->updated_at?->translatedFormat('d M Y') ?? '—'))
            ->addColumn('actions', fn (StorePage $page): string => view('storefront::vendor.pages._actions-cell', ['page' => $page, 'canManage' => $this->canManage])->render())
            ->rawColumns(['page', 'status', 'footer', 'actions']);
    }
}
