<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\DTOs\ReturnPolicyData;
use App\Modules\AfterSales\DTOs\ReturnReasonData;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Repositories\Contracts\ReturnPolicyRepositoryContract;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Platform default return policy (ADM-090): window, restocking fee, who pays the return
 * shipping, non-returnable categories, video evidence, exchanges, and the reasons list
 * (seller-fault reasons waive the fee and refund shipping on a whole-order return).
 */
class ReturnPolicyService extends BaseService
{
    public function __construct(
        private readonly ReturnPolicyRepositoryContract $policies,
        private readonly ReturnRequestRepositoryContract $returns,
    ) {}

    public function policy(): ReturnPolicy
    {
        return $this->policies->platform();
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
        $policy = $this->policy();
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
