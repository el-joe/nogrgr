<?php

namespace App\Modules\AfterSales\Repositories\Eloquent;

use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Repositories\Contracts\ReturnPolicyRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ReturnPolicy>
 */
class ReturnPolicyRepository extends BaseRepository implements ReturnPolicyRepositoryContract
{
    protected function model(): string
    {
        return ReturnPolicy::class;
    }

    public function platform(): ReturnPolicy
    {
        /** @var ReturnPolicy $policy */
        $policy = $this->query()->orderBy('id')->first() ?? $this->query()->create([]);

        return $policy->refresh();
    }

    public function reasons(bool $activeOnly = false): Collection
    {
        return ReturnReason::query()
            ->when($activeOnly, static fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findReason(int $id): ?ReturnReason
    {
        return ReturnReason::query()->find($id);
    }

    public function findReasonByCode(string $code): ?ReturnReason
    {
        return ReturnReason::query()->where('code', $code)->first();
    }

    public function saveReason(array $attributes, ?ReturnReason $reason = null): ReturnReason
    {
        $reason ??= new ReturnReason;
        $reason->fill($attributes)->save();

        return $reason;
    }

    public function deleteReason(ReturnReason $reason): void
    {
        $reason->delete();
    }

    public function nextReasonSort(): int
    {
        return (int) ReturnReason::query()->max('sort_order') + 1;
    }
}
