<?php

namespace App\Modules\Ai\Http\Controllers\Vendor;

use App\Modules\Ai\Http\Requests\Vendor\ProductAiTranslationRequest;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * VEN-012b "Translate with AI" (Content & translations tab): preview the AI translation
 * of one store product + variants into chosen store languages, then apply it. Imported
 * products under catalog.products (catalog.products.manage), own products under
 * catalog.own-products (catalog.own-products.manage); the policy checks the same.
 */
class ProductAiTranslationController extends Controller
{
    public function __construct(private readonly ProductAiTranslationService $translations) {}

    public function preview(ProductAiTranslationRequest $request, int $product): JsonResponse
    {
        $storeProduct = $this->product($request, $product);

        try {
            $preview = $this->translations->preview($storeProduct, $request->locales());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('ai::product_translation.preview_ready'), data: ['translations' => $preview]);
    }

    public function apply(ProductAiTranslationRequest $request, int $product): JsonResponse
    {
        $storeProduct = $this->product($request, $product);

        try {
            $written = $this->translations->apply($storeProduct, $request->translations());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        if ($written === 0) {
            return JsonResponder::error((string) __('ai::product_translation.errors.nothing_saved'), 422);
        }

        $route = $storeProduct->isOwn() ? 'catalog.own-products.edit' : 'catalog.products.edit';
        $params = $storeProduct->isOwn() ? ['product' => $storeProduct->id] : ['product' => $storeProduct->id, 'tab' => 'content'];

        return JsonResponder::success((string) trans_choice('ai::product_translation.saved', $written, ['count' => $written]), redirect: vendor_route($route, $params));
    }

    private function product(ProductAiTranslationRequest $request, int $id): StoreProduct
    {
        $own = str_contains((string) $request->route()?->getName(), '.own-products.');
        $product = $own ? app(OwnProductService::class)->find($id) : app(StoreCatalogService::class)->find($id);
        $this->authorizeForUser($request->user('vendor'), 'update', $product);

        return $product->load($own ? ['variants'] : ['centralProduct.variants']);
    }
}
