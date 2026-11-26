<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Enums\SocialPlatform;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;

/**
 * View data of the product AI tools (VEN-010b / VEN-010c) on a product page: null
 * when OpenAI is not configured or the staff member cannot manage the product.
 */
class ProductAiToolsPresenter
{
    public function __construct(
        private readonly AiQuotaService $quota,
        private readonly ProductAiTranslationService $languages,
        private readonly CountryRepositoryContract $countries,
    ) {}

    /**
     * @return array{countries: array<int, string>, languages: array<string, string>, platforms: array<string, string>, quota: array<string, mixed>, urls: array{price: string, social: string}}|null
     */
    public function present(StoreProduct $product): ?array
    {
        $staff = auth('vendor')->user();

        if (! OpenAiTools::configured() || $staff === null || ! $staff->can('update', $product) || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();
        $countries = $this->countries->query()->whereKey(array_map('intval', (array) ($tenant->selling_country_ids ?? [])))->get()
            ->mapWithKeys(static fn ($country): array => [$country->id => (string) $country->name])->all();
        $status = $this->quota->status(PlanLimit::AiCalls, (int) $tenant->getTenantKey());
        $prefix = $product->isOwn() ? 'catalog.own-products.' : 'catalog.products.';

        return [
            'countries' => $countries,
            'languages' => $this->languages->storeLanguages(),
            'platforms' => SocialPlatform::options(),
            'quota' => $status->toClient(),
            'urls' => [
                'price' => vendor_route($prefix.'ai-price', ['product' => $product->id]),
                'social' => vendor_route($prefix.'ai-social', ['product' => $product->id]),
            ],
        ];
    }
}
