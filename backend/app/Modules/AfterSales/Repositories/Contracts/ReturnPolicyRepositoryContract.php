<?php

namespace App\Modules\AfterSales\Repositories\Contracts;

use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<ReturnPolicy>
 */
interface ReturnPolicyRepositoryContract extends RepositoryContract
{
    public function platform(): ReturnPolicy;

    /**
     * @return Collection<int, ReturnReason>
     */
    public function reasons(bool $activeOnly = false): Collection;

    public function findReason(int $id): ?ReturnReason;

    public function findReasonByCode(string $code): ?ReturnReason;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveReason(array $attributes, ?ReturnReason $reason = null): ReturnReason;

    public function deleteReason(ReturnReason $reason): void;

    public function nextReasonSort(): int;
}
