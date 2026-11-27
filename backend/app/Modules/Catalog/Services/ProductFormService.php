<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\MediaType;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Read side of the product screens: option lists, the form's initial state (variant
 * builder + stock grid JSON), the sort page rows and the tenant picker.
 */
class ProductFormService
{
    public function __construct(
        private readonly ProductRepositoryContract $products,
        private readonly AttributeRepositoryContract $attributes,
        private readonly ProductLabelRepositoryContract $labels,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly BadgeService $badgeService,
        private readonly CategoryService $categories,
        private readonly BranchService $branches,
        private readonly StockService $stock,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * @var array<int, array{category: Category, depth: int, path: string}>|null
     */
    private ?array $flatCategories = null;

    /**
     * Options of the list filters and the sort page.
     *
     * @return array{categoryOptions: array<int, string>}
     */
    public function listOptions(): array
    {
        return [
            'categoryOptions' => array_map(static fn (array $row): string => $row['path'], $this->categories->flatten()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Product $product): array
    {
        if ($product->exists) {
            $product->load(['variants.options', 'gallery', 'categories', 'labels', 'assignedTenants']);
        }

        $badgeTypes = [];
        $gallery = $product->exists ? $product->gallery->map(static fn (CatalogMedia $media): array => [
            'id' => $media->id,
            'type' => $media->type->value,
            'url' => $media->bestUrl(),
            'poster' => $media->posterUrl(),
            'status' => $media->conversion_status,
            'primary' => $media->is_primary && $media->type === MediaType::Image,
        ])->values()->all() : [];
        // Primary image section = the flagged primary image, else the first image (#115).
        $primaryIndex = collect($gallery)->search(static fn (array $item): bool => $item['primary']);
        $primaryIndex = $primaryIndex === false ? collect($gallery)->search(static fn (array $item): bool => $item['type'] === MediaType::Image->value) : $primaryIndex;
        $primary = $primaryIndex === false ? null : $gallery[$primaryIndex];

        if ($primaryIndex !== false) {
            array_splice($gallery, (int) $primaryIndex, 1);
        }

        if ($product->exists) {
            foreach (BadgeType::cases() as $type) {
                if (in_array($product->id, $this->badges->assignedProductIds($this->badgeService->badge($type)), true)) {
                    $badgeTypes[] = $type->value;
                }
            }
        }

        return [
            'product' => $product,
            'locales' => $this->locales->all(),
            'statuses' => ProductStatus::options(),
            'scopes' => AssignmentScope::options(),
            'categoryTree' => $this->categoryTree(),
            'selectedCategories' => $product->exists ? $product->categories->map(fn ($category): array => ['id' => $category->id, 'path' => $this->categoryPath($category->id) ?? (string) $category->name])->values()->all() : [],
            'labelOptions' => $this->labels->ordered()->mapWithKeys(fn ($label): array => [$label->id => (string) $label->text])->all(),
            'badgeOptions' => BadgeType::options(),
            'tenantOptions' => $product->exists ? $product->assignedTenants->pluck('name', 'id')->all() : [],
            'selectedBadges' => $badgeTypes,
            'primaryImage' => $primary,
            'gallery' => $gallery,
            'builder' => $this->builderState($product),
        ];
    }

    /**
     * Drill-down picker data (#115): every category with its parent and whether it is a
     * leaf (only leaves can be added).
     *
     * @return list<array{id: int, name: string, parent: int|null, path: string, leaf: bool}>
     */
    public function categoryTree(): array
    {
        $flat = $this->flatCategories();
        $parents = [];

        foreach ($flat as $row) {
            if ($row['category']->parent_id !== null) {
                $parents[(int) $row['category']->parent_id] = true;
            }
        }

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['category']->id,
            'name' => (string) $row['category']->name,
            'parent' => $row['category']->parent_id !== null ? (int) $row['category']->parent_id : null,
            'path' => $row['path'],
            'leaf' => ! isset($parents[(int) $row['category']->id]),
        ], $flat));
    }

    private function categoryPath(int $id): ?string
    {
        return $this->flatCategories()[$id]['path'] ?? null;
    }

    /**
     * @return array<int, array{category: Category, depth: int, path: string}>
     */
    private function flatCategories(): array
    {
        return $this->flatCategories ??= $this->categories->flatten();
    }

    /**
     * JSON island of the variant builder and stock grid.
     *
     * @return array<string, mixed>
     */
    public function builderState(Product $product): array
    {
        $attributes = $this->attributes->query()
            ->where('status', 'active')
            ->with('options')
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (Attribute $attribute): array => [
                'id' => $attribute->id,
                'name' => (string) $attribute->name,
                'swatch' => $attribute->display_type->usesSwatch(),
                'options' => $attribute->options->map(static fn ($option): array => [
                    'id' => $option->id,
                    'name' => (string) $option->name,
                    'swatch' => $option->swatch,
                    'active' => $option->is_active,
                ])->values()->all(),
            ])->values()->all();

        $levels = $product->exists ? $this->stock->levels($product) : [];
        $variants = [];
        $stock = [];

        foreach ($product->exists ? $product->variants : [] as $variant) {
            /** @var ProductVariant $variant */
            $optionIds = $variant->options->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $key = VariantMatrix::key($optionIds);
            $variants[$key] = [
                'id' => $variant->id,
                'option_ids' => $optionIds,
                'sku' => $variant->sku,
                'price' => $variant->price?->toDecimal(),
                'weight_grams' => $variant->weight_grams,
                'is_active' => $variant->status->value === 'active',
                'image' => $variant->imageUrl(),
            ];

            foreach ($levels as $branchId => $cells) {
                $stock[$branchId][$key] = $cells[$variant->id] ?? 0;
            }
        }

        foreach ($levels as $branchId => $cells) {
            if (isset($cells[0])) {
                $stock[$branchId]['base'] = $cells[0];
            }
        }

        // The form's stock fields are the default branch's levels (created on first need);
        // other branches keep theirs and only count in the total.
        $default = $this->branches->defaultOrCreate();
        $defaultId = $default !== null ? (int) $default->id : null;
        $quantities = $defaultId !== null ? ($stock[$defaultId] ?? []) : [];

        return [
            'attributes' => $attributes,
            'variants' => $variants,
            'branch' => $default !== null ? ['id' => $defaultId, 'name' => (string) $default->name] : null,
            'branch_count' => $this->branches->active()->count(),
            'quantities' => (object) $quantities,
            'totals' => (object) array_map(static fn (array $cells): int => array_sum($cells), $this->transpose($stock)),
        ];
    }

    /**
     * [branch][key] => qty to [key][branch] => qty.
     *
     * @param  array<int, array<string, int>>  $stock
     * @return array<string, array<int, int>>
     */
    private function transpose(array $stock): array
    {
        $rows = [];

        foreach ($stock as $branchId => $cells) {
            foreach ($cells as $key => $quantity) {
                $rows[(string) $key][$branchId] = (int) $quantity;
            }
        }

        return $rows;
    }

    /**
     * @param  array{category_id: int|null, search: string|null}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, more: bool, total: int, from: int}}
     */
    public function sortPage(array $filters, int $page, int $perPage): array
    {
        $paginator = $this->products->sortPage($filters, $page, $perPage);

        return [
            'data' => $paginator->getCollection()->map(static fn (Product $product): array => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'handle_label' => __('catalog::common.drag_handle', ['name' => (string) $product->name]),
                'subtitle' => trim(($product->categories->first()?->name ? $product->categories->first()->name.' · ' : '').$product->costBasis()->format()),
                'image' => $product->primaryImageUrl(),
                'status' => $product->manage_stock && $product->stock <= 0 ? 'out_of_stock' : $product->status->value,
                'status_label' => $product->manage_stock && $product->stock <= 0 ? __('catalog::products.stock_states.out_of_stock') : $product->status->label(),
            ])->values()->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'more' => $paginator->hasMorePages(),
                'total' => $paginator->total(),
                'from' => (int) ($paginator->firstItem() ?? 0),
            ],
        ];
    }

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function tenantResults(?string $term, int $page): array
    {
        $results = $this->products->searchTenants($term, $page);

        return [
            'results' => $results->getCollection()->map(static fn ($tenant): array => ['id' => (int) $tenant->id, 'text' => (string) $tenant->name])->values()->all(),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    public function findWithTrashed(int $id): Product
    {
        return $this->products->findWithTrashed($id) ?? throw (new ModelNotFoundException)->setModel(Product::class, [$id]);
    }
}
