<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Vendor\SortRequest;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Product sort (VEN-014): drag & drop order of every store product (central + own).
 */
class ProductSortController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly StoreCatalogService $catalog) {}

    public function index(StoreProductPricingService $pricing, StoreCatalogReader $reader): View
    {
        $views = $reader->views($this->catalog->sortable());
        $prices = [];

        foreach ($views as $view) {
            $prices[$view->id()] = $pricing->fromPrice($view->product);
        }

        return view('catalog::vendor.products.sort', ['views' => $views, 'prices' => $prices]);
    }

    public function update(SortRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->catalog->sort($request->ids()), (string) __('catalog::vendor.sort.saved'));
    }
}
