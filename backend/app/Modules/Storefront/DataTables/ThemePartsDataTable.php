<?php

namespace App\Modules\Storefront\DataTables;

use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Models\ThemePart;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Storefront › Template parts (ADM-088): part (preview, name, type), theme,
 * default badge, status + on / off switch, actions. Filters: theme, part type, search (name).
 *
 * @extends BaseDataTable<ThemePart>
 */
final class ThemePartsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'theme_id', 'type'];

    protected function query(): Builder
    {
        return ThemePart::query()->select('theme_parts.*')->with('theme:id,slug,name,family,variant');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $theme = filter_var($filters['theme_id'] ?? null, FILTER_VALIDATE_INT);
        $type = ThemePartType::tryFrom((string) ($filters['type'] ?? ''));
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when(is_int($theme) && $theme > 0, fn (Builder $query) => $query->where('theme_id', $theme))
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type?->value))
            ->when($search !== null, fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (Builder $query) => $query->orderBy('theme_id')->orderBy('type')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id'))
            ->addColumn('part', fn (ThemePart $part): string => view('storefront::admin.template-parts._part-cell', ['part' => $part])->render())
            ->addColumn('theme', fn (ThemePart $part): string => e((string) $part->theme?->name))
            ->addColumn('status', fn (ThemePart $part): string => view('storefront::admin.template-parts._status-cell', ['part' => $part])->render())
            ->addColumn('actions', fn (ThemePart $part): string => view('storefront::admin.template-parts._actions-cell', ['part' => $part])->render())
            ->rawColumns(['part', 'status', 'actions']);
    }
}
