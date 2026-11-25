<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\CountryAvailability;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Localization\Services\CountryService;
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
        private readonly CountryService $countries,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * Options of the list filters and the sort page.
     *
     * @return array{categoryOptions: array<int, string>, countryOptions: array<int, string>}
     */
    public function listOptions(): array
    {
        return [
            'categoryOptions' => array_map(static fn (array $row): string => $row['path'], $this->categories->flatten()),
            'countryOptions' => $this->countries->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Product $product): array
    {
        if ($product->exists) {
            $product->load(['variants.options', 'media', 'categories', 'labels', 'assignedTenants']);
        }

        $badgeTypes = [];

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
            'deliveryScopes' => DeliveryScope::options(),
            'countryModes' => CountryAvailability::options(),
            'scopes' => AssignmentScope::options(),
            'categoryOptions' => array_map(static fn (array $row): string => $row['path'], $this->categories->flatten()),
            'labelOptions' => $this->labels->ordered()->mapWithKeys(fn ($label): array => [$label->id => (string) $label->text])->all(),
            'badgeOptions' => BadgeType::options(),
            'countryOptions' => $this->countries->options(),
            'tenantOptions' => $product->exists ? $product->assignedTenants->pluck('name', 'id')->all() : [],
            'selectedBadges' => $badgeTypes,
            'countryMode' => $product->exists ? CountryAvailability::fromIds($product->allowed_country_ids) : CountryAvailability::All,
            'gallery' => $product->exists ? $product->media->map(static fn (CatalogMedia $media): array => [
                'id' => $media->id,
                'url' => $media->url(),
                'status' => $media->conversion_status,
            ])->all() : [],
            'builder' => $this->builderState($product),
        ];
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

        return [
            'attributes' => $attributes,
            'variants' => $variants,
            'branches' => $this->branches->active()->map(static fn (Branch $branch): array => ['id' => $branch->id, 'name' => (string) $branch->name])->values()->all(),
            'stock' => $stock,
        ];
    }

    /**
     * @param  array{category_id: int|null, country_id: int|null, search: string|null}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, more: bool, total: int, from: int}}
     */
    public function sortPage(array $filters, int $page, int $perPage): array
    {
        $paginator = $this->products->sortPage($filters, $page, $perPage);

        return [
            'data' => $paginator->getCollection()->map(static fn (Product $product): array => [
                'id' => $product->id,
                'name' => (string) $product->name,
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
