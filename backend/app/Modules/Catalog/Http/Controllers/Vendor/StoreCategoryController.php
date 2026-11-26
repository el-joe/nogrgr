<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Vendor\CategorySortRequest;
use App\Modules\Catalog\Http\Requests\Vendor\SortRequest;
use App\Modules\Catalog\Http\Requests\Vendor\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\Vendor\StoreToggleRequest;
use App\Modules\Catalog\Http\Requests\Vendor\TaxonomyProductsRequest;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyPageService;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyService;
use App\Modules\Catalog\Support\StoreCategoryRef;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Products › Categories (VEN-017, decision #74): central categories (hide /
 * order) and store-own categories (create, edit, delete, feature), the category inner
 * page with its products (add, remove, order — VEN-017a) and the category sort (VEN-017c).
 * {category} = "central-<id>" | "store-<id>", {own} = store_categories.id.
 */
class StoreCategoryController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly StoreTaxonomyService $taxonomy,
        private readonly StoreTaxonomyPageService $pages,
    ) {}

    public function index(Request $request): View
    {
        return view('catalog::vendor.categories.index', [
            'categories' => $this->pages->categoriesPage(max(1, (int) $request->query('page', '1')), vendor_route('catalog.categories.index')),
        ]);
    }

    public function products(Request $request, string $category): View
    {
        $row = $this->pages->category($this->ref($category));
        $tab = $request->query('tab') === 'order' ? 'order' : 'products';

        return view('catalog::vendor.categories.products', [
            'row' => $row,
            'tab' => $tab,
            'cards' => $tab === 'products' ? $this->pages->categoryProducts($row, max(1, (int) $request->query('page', '1')), vendor_route('catalog.categories.products', ['category' => $row->ref->routeKey()])) : null,
            'sortRows' => $tab === 'order' ? $this->pages->sortRows($row->productIds) : [],
        ]);
    }

    public function create(CountryService $countries): View
    {
        return view('catalog::vendor.categories.form', $this->formData(new StoreCategory(['is_active' => true]), $countries));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->taxonomy->createCategory($request->toData());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('catalog::taxonomy.categories.created'), ['id' => $category->id], vendor_route('catalog.categories.index'));
    }

    public function edit(int $own, CountryService $countries): View
    {
        return view('catalog::vendor.categories.form', $this->formData($this->taxonomy->findOwn($own), $countries));
    }

    public function update(StoreCategoryRequest $request, int $own): JsonResponse
    {
        $category = $this->taxonomy->findOwn($own);

        return $this->attempt(fn () => $this->taxonomy->updateCategory($category, $request->toData()), (string) __('catalog::taxonomy.categories.updated'), vendor_route('catalog.categories.index'));
    }

    public function destroy(int $own): JsonResponse
    {
        $category = $this->taxonomy->findOwn($own);

        return $this->attempt(fn () => $this->taxonomy->deleteCategory($category), (string) __('catalog::taxonomy.categories.deleted'));
    }

    public function toggle(StoreToggleRequest $request, string $category): JsonResponse
    {
        $ref = $this->ref($category);

        if ($request->routeIs('*.featured')) {
            abort_if($ref->isCentral(), 404);

            return $this->attempt(fn () => $this->taxonomy->setFeatured($this->taxonomy->findOwn($ref->id), $request->value()), (string) __('catalog::taxonomy.categories.featured_saved'));
        }

        return $this->attempt(fn () => $this->taxonomy->setVisible($ref, $request->value()), (string) __($request->value() ? 'catalog::taxonomy.categories.shown' : 'catalog::taxonomy.categories.hidden'));
    }

    public function sort(StoreTaxonomyReader $reader): View
    {
        return view('catalog::vendor.products.sort', [
            'tab' => 'categories',
            'categoryRows' => $reader->categories(),
            'views' => [],
            'prices' => [],
        ]);
    }

    public function sortUpdate(CategorySortRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->taxonomy->sortCategories($request->refs()), (string) __('catalog::taxonomy.sort.saved'));
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('q');

        return response()->json($this->pages->searchProducts(is_string($term) ? mb_substr($term, 0, 100) : null, (int) $request->query('page', '1')));
    }

    public function attach(TaxonomyProductsRequest $request, string $category): JsonResponse
    {
        $ref = $this->ref($category);

        try {
            $added = $this->taxonomy->addProducts($ref, $request->productIds());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(trans_choice('catalog::taxonomy.products.added', $added, ['count' => $added]), redirect: vendor_route('catalog.categories.products', ['category' => $ref->routeKey()]));
    }

    public function detach(string $category, int $product): JsonResponse
    {
        return $this->attempt(fn () => $this->taxonomy->removeProduct($this->ref($category), $product), (string) __('catalog::taxonomy.products.removed'));
    }

    public function sortProducts(SortRequest $request, string $category): JsonResponse
    {
        return $this->attempt(fn () => $this->taxonomy->sortProducts($this->ref($category), $request->ids()), (string) __('catalog::taxonomy.sort.saved'));
    }

    private function ref(string $key): StoreCategoryRef
    {
        return StoreCategoryRef::tryParse($key) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(StoreCategory $category, CountryService $countries): array
    {
        $tenant = tenant();
        $selling = $tenant instanceof Tenant ? array_map('intval', $tenant->selling_country_ids ?? []) : [];

        return [
            'category' => $category,
            'parentOptions' => $this->taxonomy->parentOptions($category->exists ? $category : null),
            'countries' => $selling === [] ? collect() : $countries->active($selling)->values(),
        ];
    }
}
