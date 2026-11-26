<?php

namespace App\Modules\Ai\Http\Controllers\Vendor;

use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Http\Requests\Vendor\PriceFinderRequest;
use App\Modules\Ai\Http\Requests\Vendor\SocialPostRequest;
use App\Modules\Ai\Services\PriceFinder\PriceFinderService;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\SocialPosts\SocialPostService;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-product OpenAI tools of the vendor panel (decision #43, metered by the plan's
 * monthly `ai_calls`): VEN-010b AI price finder (suggestion only, never applied) and
 * VEN-010c AI social posts. Imported products under catalog.products
 * (catalog.products.manage), own products under catalog.own-products
 * (catalog.own-products.manage); the product policy `update` is checked as well.
 * 429 = monthly quota reached ("limit reached" state), 503 = provider problem.
 */
class ProductAiToolsController extends Controller
{
    public function __construct(private readonly AiQuotaService $quota) {}

    public function priceFinder(PriceFinderRequest $request, PriceFinderService $finder, CountryRepositoryContract $countries, int $product): JsonResponse
    {
        $storeProduct = $this->product($request, $product);
        $country = $countries->findOrFail((int) $request->validated('country_id'));

        return $this->guard(fn (): JsonResponse => JsonResponder::success(data: [
            'result' => $finder->find($storeProduct, $country, $this->tenantId()),
            'quota' => $this->quotaPayload(),
        ]));
    }

    public function socialPost(SocialPostRequest $request, SocialPostService $posts, ProductAiTranslationService $languages, int $product): JsonResponse
    {
        $storeProduct = $this->product($request, $product);
        $locale = (string) $request->validated('locale');
        $available = $languages->storeLanguages();

        if (! array_key_exists($locale, $available)) {
            return JsonResponder::error((string) __('ai::tools.errors.language'), 422);
        }

        $tenant = tenant();
        $link = StoreSettingsService::storeUrl($tenant).'/products/'.$storeProduct->id;

        return $this->guard(fn (): JsonResponse => JsonResponder::success(data: [
            'post' => $posts->generate($storeProduct, $request->platform(), $locale, $available[$locale], $link, (string) ($tenant->name ?? ''), null),
            'quota' => $this->quotaPayload(),
        ]));
    }

    /**
     * @param  callable(): JsonResponse  $call
     */
    private function guard(callable $call): JsonResponse
    {
        try {
            return $call();
        } catch (AiQuotaExceededException $exception) {
            return JsonResponder::error($exception->getMessage(), 429, ['quota' => $exception->status->toClient()]);
        } catch (AiRequestException $exception) {
            return JsonResponder::error($exception->getMessage(), 503);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function quotaPayload(): array
    {
        return $this->quota->status(PlanLimit::AiCalls, $this->tenantId())->toClient();
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }

    private function product(Request $request, int $id): StoreProduct
    {
        $own = str_contains((string) $request->route()?->getName(), '.own-products.');
        $product = $own ? app(OwnProductService::class)->find($id) : app(StoreCatalogService::class)->find($id);
        $this->authorizeForUser($request->user('vendor'), 'update', $product);

        return $product;
    }
}
