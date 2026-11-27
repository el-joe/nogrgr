<?php

namespace App\Modules\Storefront\DataTables;

use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Storefront › Theme review queue (ADM-089, Figma Admin Panel 78:80277): submitted
 * custom theme versions across stores — store, theme + version, submitted, validation
 * (errors / warnings), status, reviewer, open. Filters: status (default pending), search
 * (store, theme name, version). Oldest pending first.
 *
 * @extends BaseDataTable<CustomThemeVersion>
 */
final class CustomThemeReviewsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status'];

    public function __construct(private readonly CustomThemeRepositoryContract $themes) {}

    protected function query(): Builder
    {
        return $this->themes->reviewQuery()->select('custom_theme_versions.*');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = CustomThemeVersionStatus::tryFrom((string) ($filters['status'] ?? ''));
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('version', 'like', $term)
                    ->orWhereHas('tenant', fn (Builder $tenants) => $tenants->where('name', 'like', $term)->orWhere('slug', 'like', $term)));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (Builder $query) => $query->orderByRaw("status = 'pending_review' desc")->orderBy('submitted_at')->orderByDesc('id'))
            ->addColumn('store', static fn (CustomThemeVersion $version): string => e((string) ($version->tenant?->getAttribute('name') ?? '#'.$version->tenant_id)))
            ->addColumn('theme', static fn (CustomThemeVersion $version): string => view('storefront::admin.theme-reviews._theme-cell', ['version' => $version])->render())
            ->addColumn('submitted', static fn (CustomThemeVersion $version): string => e($version->submitted_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—'))
            ->addColumn('validation', static fn (CustomThemeVersion $version): string => view('storefront::admin.theme-reviews._validation-cell', ['version' => $version])->render())
            ->addColumn('status', static fn (CustomThemeVersion $version): string => view('storefront::admin.theme-reviews._status-cell', ['version' => $version])->render())
            ->addColumn('reviewer', static fn (CustomThemeVersion $version): string => e($version->reviewed_by_name ?? '—'))
            ->addColumn('actions', static fn (CustomThemeVersion $version): string => view('storefront::admin.theme-reviews._actions-cell', ['version' => $version])->render())
            ->rawColumns(['theme', 'validation', 'status', 'actions']);
    }
}
