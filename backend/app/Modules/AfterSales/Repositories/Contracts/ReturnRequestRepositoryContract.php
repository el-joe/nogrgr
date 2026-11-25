<?php

namespace App\Modules\AfterSales\Repositories\Contracts;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestEvent;
use App\Modules\AfterSales\Models\ReturnRequestMedia;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<ReturnRequest>
 */
interface ReturnRequestRepositoryContract extends RepositoryContract
{
    /**
     * Admin (all stores, tenant filter) / vendor (current store via the tenant scope) list.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ReturnRequest>
     */
    public function listing(array $filters): Builder;

    /**
     * @return array<string, int> status => count
     */
    public function statusCounts(): array;

    public function findForTenant(int $id, ?int $tenantId = null): ?ReturnRequest;

    /**
     * @return Collection<int, ReturnRequest>
     */
    public function forOrder(int $tenantId, string $orderUuid): Collection;

    /**
     * Units of an order line held by returns that are not rejected / cancelled.
     */
    public function heldQuantity(int $tenantId, string $orderUuid, int $orderItemId): int;

    public function nextReference(): string;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addEvent(ReturnRequest $return, array $attributes): ReturnRequestEvent;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addMedia(ReturnRequest $return, array $attributes): ReturnRequestMedia;

    public function findMedia(ReturnRequest $return, int $mediaId): ?ReturnRequestMedia;

    public function openCount(): int;

    public function lock(ReturnRequest $return): ReturnRequest;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function setStatus(ReturnRequest $return, ReturnStatus $status, array $extra = []): void;
}
