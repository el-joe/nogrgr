<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\DTOs\ReturnPolicyData;
use App\Modules\AfterSales\DTOs\ReturnReasonData;
use App\Modules\AfterSales\DTOs\StoreReturnPolicyData;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Repositories\Contracts\ReturnPolicyRepositoryContract;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Platform default return policy (ADM-090): window, restocking fee, who pays the return
 * shipping, non-returnable categories, video evidence, exchanges, and the reasons list
 * (seller-fault reasons waive the fee and refund shipping on a whole-order return), and
 * each store's stricter override with its storefront policy text (VEN-079).
 */
class ReturnPolicyService extends BaseService
{
    public function __construct(
        private readonly ReturnPolicyRepositoryContract $policies,
        private readonly ReturnRequestRepositoryContract $returns,
        private readonly StoreSettingRepositoryContract $storeSettings,
        private readonly Auditor $auditor,
    ) {}

    /**
     * The policy in force: inside a store the platform default tightened by the store's
     * override (VEN-079), elsewhere the platform default. The store override is applied
     * on read too, so a later platform change can never be loosened by an older override:
     * window = min, fee = max, non-returnable = union, exchanges = both allow.
     * The returned model is a detached copy in store context — never save it.
     */
    public function policy(): ReturnPolicy
    {
        $platform = $this->platformPolicy();
        $override = $this->storeOverride();

        if ($override === null) {
            return $platform;
        }

        $effective = $platform->replicate();
        $effective->id = $platform->id;
        $effective->exists = false;
        $effective->forceFill([
            'window_days' => min($platform->window_days, (int) $override['window_days']),
            'restocking_fee_bps' => max($platform->restocking_fee_bps, (int) $override['restocking_fee_bps']),
            'non_returnable_category_ids' => array_values(array_unique([...array_map('intval', $platform->non_returnable_category_ids ?? []), ...array_map('intval', (array) $override['non_returnable_category_ids'])])),
            'allow_exchanges' => $platform->allow_exchanges && (bool) $override['allow_exchanges'],
        ]);

        return $effective;
    }

    /**
     * The platform default (ADM-090), never the store override.
     */
    public function platformPolicy(): ReturnPolicy
    {
        return $this->policies->platform();
    }

    /**
     * The current store's override, null outside a store or when it has none.
     *
     * @return array{window_days: int, restocking_fee_bps: int, non_returnable_category_ids: list<int>, allow_exchanges: bool, text: array<string, string>}|null
     */
    public function storeOverride(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $value = $this->storeSettings->get(StoreSettingKey::ReturnPolicy);

        if (! is_array($value) || ! isset($value['window_days'])) {
            return null;
        }

        return [
            'window_days' => (int) $value['window_days'],
            'restocking_fee_bps' => (int) ($value['restocking_fee_bps'] ?? 0),
            'non_returnable_category_ids' => array_values(array_map('intval', (array) ($value['non_returnable_category_ids'] ?? []))),
            'allow_exchanges' => (bool) ($value['allow_exchanges'] ?? true),
            'text' => array_filter(array_map('strval', (array) ($value['text'] ?? [])), static fn (string $text): bool => trim($text) !== ''),
        ];
    }

    /**
     * Storefront policy text of the current store in the locale (fallback: any locale).
     */
    public function storeText(?string $locale = null): ?string
    {
        $text = $this->storeOverride()['text'] ?? [];
        $locale ??= app()->getLocale();

        return $text[$locale] ?? (reset($text) ?: null);
    }

    /**
     * Saves the store override, clamped to the platform default (never looser).
     */
    public function saveStoreOverride(StoreReturnPolicyData $data): void
    {
        $platform = $this->platformPolicy();
        $before = $this->storeOverride();
        $value = [
            'window_days' => min($data->windowDays, $platform->window_days),
            'restocking_fee_bps' => max($data->restockingFeeBps, $platform->restocking_fee_bps),
            'non_returnable_category_ids' => array_values(array_unique([...array_map('intval', $platform->non_returnable_category_ids ?? []), ...$data->nonReturnableCategoryIds])),
            'allow_exchanges' => $platform->allow_exchanges && $data->allowExchanges,
            'text' => array_filter($data->text, static fn (string $text): bool => trim($text) !== ''),
        ];

        $this->storeSettings->put(StoreSettingKey::ReturnPolicy, $value);
        $this->auditor->record('store.return_policy_updated', null, ['before' => $before, 'after' => $value]);
    }

    /**
     * @return Collection<int, ReturnReason>
     */
    public function reasons(bool $activeOnly = false): Collection
    {
        return $this->policies->reasons($activeOnly);
    }

    /**
     * @return array<string, string> code => label (active reasons)
     */
    public function reasonOptions(): array
    {
        $options = [];

        foreach ($this->reasons(true) as $reason) {
            $options[$reason->code] = $reason->name;
        }

        return $options;
    }

    public function reasonByCode(string $code): ?ReturnReason
    {
        return $this->policies->findReasonByCode($code);
    }

    public function findReason(int $id): ReturnReason
    {
        return $this->policies->findReason($id) ?? throw AfterSalesException::rule('reason_not_found', 404);
    }

    public function update(ReturnPolicyData $data): ReturnPolicy
    {
        $policy = $this->platformPolicy();
        $this->policies->update($policy, [
            'window_days' => $data->windowDays,
            'restocking_fee_bps' => $data->restockingFeeBps,
            'return_shipping_payer' => $data->returnShippingPayer,
            'non_returnable_category_ids' => array_values(array_unique($data->nonReturnableCategoryIds)),
            'require_video' => $data->requireVideo,
            'allow_exchanges' => $data->allowExchanges,
        ]);

        return $policy;
    }

    public function saveReason(ReturnReasonData $data, ?ReturnReason $reason = null): ReturnReason
    {
        $attributes = [
            'name' => $data->name,
            'requires_video' => $data->requiresVideo,
            'seller_fault' => $data->sellerFault,
            'is_active' => $data->isActive,
            'sort_order' => $data->sortOrder > 0 ? $data->sortOrder : $this->policies->nextReasonSort(),
        ];

        if ($reason === null) {
            $names = $data->name;
            $base = Str::limit(Str::slug($names['en'] ?? (string) reset($names), '_'), 30, '');
            $code = $base !== '' ? $base : 'reason';
            $suffix = 1;

            while ($this->policies->findReasonByCode($code) !== null) {
                $code = $base.'_'.(++$suffix);
            }

            $attributes['code'] = $code;
        }

        return $this->policies->saveReason($attributes, $reason);
    }

    public function setReasonActive(ReturnReason $reason, bool $active): void
    {
        $this->policies->saveReason(['is_active' => $active], $reason);
    }

    public function deleteReason(ReturnReason $reason): void
    {
        if ($this->returns->query()->withoutGlobalScopes()->where('reason_code', $reason->code)->exists()) {
            throw AfterSalesException::rule('reason_in_use');
        }

        $this->policies->deleteReason($reason);
    }
}
