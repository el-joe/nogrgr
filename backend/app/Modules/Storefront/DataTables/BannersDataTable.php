<?php

namespace App\Modules\Storefront\DataTables;

use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Storefront › Default banners (ADM-085, Figma Admin Panel 78:76059): one card
 * row per banner (drag handle, position, image, title, link · schedule · countries),
 * status, on / off switch, actions. Always in sort order (drag to sort). Filters: country
 * tab (banners shown there, incl. all-country ones; 0 / empty = every banner), placement,
 * search (title).
 *
 * @extends BaseDataTable<Banner>
 */
final class BannersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'country_id', 'placement'];

    public function __construct(private readonly BannerRepositoryContract $banners) {}

    protected function query(): Builder
    {
        return $this->banners->query()->select('banners.*')->with('countries:id,iso2')->withCount('tenants');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $placement = BannerPlacement::tryFrom((string) ($filters['placement'] ?? '')) ?? BannerPlacement::HomeHero;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->where('placement', $placement->value)
            ->when(is_int($country) && $country > 0, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereDoesntHave('countries')
                ->orWhereHas('countries', fn (Builder $countries) => $countries->whereKey($country))))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`title`, "$.en"))) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`title`, "$.ar"))) LIKE ?', [$term])
                    ->orWhere('link_url', 'like', $term));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (Builder $query) => $query->orderBy('sort_order')->orderBy('id'))
            ->setRowAttr(['data-id' => static fn (Banner $banner): int => $banner->id, 'data-sort-item' => '1'])
            ->addColumn('banner', fn (Banner $banner): string => view('storefront::admin.banners._card-cell', ['banner' => $banner])->render())
            ->addColumn('status', fn (Banner $banner): string => view('storefront::admin.banners._status-cell', ['banner' => $banner])->render())
            ->addColumn('actions', fn (Banner $banner): string => view('storefront::admin.banners._actions-cell', ['banner' => $banner])->render())
            ->rawColumns(['banner', 'status', 'actions']);
    }
}
