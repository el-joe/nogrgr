<?php

namespace App\Modules\Localization\Services;

use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read side of language / AI translation purchases for the admin (ADM-094). Purchases,
 * invoices and payment logs stay in Billing; this only reads them.
 */
class LanguagePurchaseService
{
    /**
     * @var array<string, string>|null
     */
    private ?array $names = null;

    public function __construct(
        private readonly AddonPurchaseRepositoryContract $purchases,
        private readonly LanguageRepositoryContract $languages,
    ) {}

    public function count(): int
    {
        return $this->purchases->adminCount();
    }

    public function find(int $id): AddonPurchase
    {
        return $this->purchases->findForAdmin($id);
    }

    /**
     * @return Collection<int, PaymentLog>
     */
    public function paymentLogs(AddonPurchase $purchase): Collection
    {
        return $this->purchases->paymentLogsFor($purchase);
    }

    /**
     * "French" for a language, "AI translation · French" for an AI translation purchase.
     */
    public function describe(AddonPurchase $purchase): string
    {
        $this->names ??= $this->languages->query()->get(['code', 'name'])
            ->mapWithKeys(fn ($language): array => [(string) $language->code => (string) $language->name])
            ->all();
        $name = $this->names[$purchase->item_code] ?? strtoupper($purchase->item_code);

        return $purchase->type === AddonType::Language
            ? $name
            : __('localization::purchases.ai_translation_of', ['language' => $name]);
    }
}
