<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central DB; queries are tenant scoped in tenant context (BelongsToCurrentTenant).
 *
 * @extends RepositoryContract<AddonPurchase>
 */
interface AddonPurchaseRepositoryContract extends RepositoryContract
{
    /**
     * The store's unpaid purchase of an item, if any.
     */
    public function openFor(int $tenantId, AddonType $type, string $itemCode): ?AddonPurchase;

    /**
     * Item codes of the store's paid purchases of a type (e.g. bought language codes).
     *
     * @return list<string>
     */
    public function paidItemCodes(int $tenantId, AddonType $type): array;

    /**
     * @return Collection<int, AddonPurchase>
     */
    public function forTenant(int $tenantId): Collection;

    /**
     * Admin list of language / AI translation purchases (all stores) with the store and
     * the latest invoice number / payment gateway. Filters: type, status, gateway, search.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AddonPurchase>
     */
    public function adminListing(array $filters = []): Builder;

    /**
     * Purchase counts for the admin list.
     */
    public function adminCount(): int;

    public function findForAdmin(int $id): AddonPurchase;

    /**
     * Payment attempts of the purchase's invoices, newest first.
     *
     * @return Collection<int, PaymentLog>
     */
    public function paymentLogsFor(AddonPurchase $purchase): Collection;
}
