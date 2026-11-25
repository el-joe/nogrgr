<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\ProductSortRequest;
use App\Modules\Catalog\Http\Requests\Admin\SortRequest;
use App\Modules\Catalog\Services\ProductFormService;
use App\Modules\Catalog\Services\ProductService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Catalog › Products › Sort (ADM-012, Figma 65:21308): storefront order by drag
 * and drop over pages of 20 ("Load more"), filtered by category / country / search;
 * Save order and Reset to newest.
 */
class ProductSortController extends Controller
{
    use AnswersDomainErrors;

    public const PER_PAGE = 20;

    public function __construct(
        private readonly ProductService $products,
        private readonly ProductFormService $form,
    ) {}

    public function index(): View
    {
        return view('catalog::admin.products.sort', $this->form->listOptions());
    }

    public function data(ProductSortRequest $request): JsonResponse
    {
        return response()->json($this->form->sortPage($request->filters(), $request->page(), self::PER_PAGE));
    }

    public function save(SortRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->products->saveOrder($request->ids()), __('catalog::products.sort.saved'));
    }

    public function reset(): JsonResponse
    {
        $this->products->resetOrder();

        return JsonResponder::success(__('catalog::products.sort.reset_done'), redirect: route('admin.catalog.products.sort'));
    }
}
