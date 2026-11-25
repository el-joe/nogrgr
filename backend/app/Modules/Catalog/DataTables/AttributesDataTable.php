<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Catalog › Attributes list (server-side). Filters: type, search (en / ar name).
 * "Used by" = number of products using the attribute.
 *
 * @extends BaseDataTable<Attribute>
 */
final class AttributesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'display_type'];

    /**
     * Option names shown in the Options column before "+N".
     */
    public const OPTIONS_PREVIEW = 5;

    public function __construct(private readonly AttributeRepositoryContract $attributes) {}

    protected function query(): Builder
    {
        return $this->attributes->query()
            ->select('attributes.*')
            ->with('options')
            ->withCount(['options', 'products']);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $type = AttributeDisplayType::tryFrom((string) ($filters['display_type'] ?? ''));
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($type !== null, fn (Builder $query) => $query->where('display_type', $type?->value))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(function (Builder $query) use ($term): void {
                    foreach (['en', 'ar'] as $locale) {
                        $query->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`attributes`.`name`, ?))) LIKE ?', ['$.'.$locale, $term]);
                    }
                });
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name->'.$locale, $direction($order)))
            ->orderColumn('options', static fn (Builder $query, string $order) => $query->orderBy('options_count', $direction($order)))
            ->orderColumn('products_count', static fn (Builder $query, string $order) => $query->orderBy('products_count', $direction($order)))
            ->editColumn('name', fn (Attribute $attribute): string => view('catalog::admin.attributes._name-cell', ['attribute' => $attribute])->render())
            ->editColumn('display_type', fn (Attribute $attribute): string => e($attribute->display_type->label()))
            ->addColumn('options', fn (Attribute $attribute): string => view('catalog::admin.attributes._options-cell', ['attribute' => $attribute, 'preview' => self::OPTIONS_PREVIEW])->render())
            ->editColumn('products_count', fn (Attribute $attribute): string => '<span dir="ltr" class="font-medium">'.e(number_format((int) $attribute->products_count)).'</span>')
            ->addColumn('actions', fn (Attribute $attribute): string => view('catalog::admin.attributes._actions-cell', ['attribute' => $attribute])->render())
            ->rawColumns(['name', 'options', 'products_count', 'actions']);
    }
}
