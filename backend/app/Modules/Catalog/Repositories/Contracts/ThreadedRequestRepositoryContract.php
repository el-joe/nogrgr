<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Requests with a conversation (product / manufacturing / brand), central DB, admin side
 * (no tenant scope). One implementation per request model.
 *
 * @template TModel of Model
 *
 * @extends RepositoryContract<TModel>
 */
interface ThreadedRequestRepositoryContract extends RepositoryContract
{
    /**
     * Admin list: filters status ("open" = every open status), priority (product), tenant_id,
     * archived (product: "1" = archived only, default = not archived), search (subject,
     * store, request number). Adds store_name / store_slug and admin_unread_count.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public function listing(array $filters = []): Builder;

    /**
     * Requests the admin has not opened or that carry unread vendor messages.
     */
    public function adminUnreadCount(): int;

    /**
     * Vendor list of one store: filters status ("open" = every open status) and search
     * (subject, request number). Adds vendor_unread_count (unread platform messages).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public function vendorListing(int $tenantId, array $filters = []): Builder;

    /**
     * @return TModel|null
     */
    public function findForTenant(int $tenantId, int $id): ?Model;

    /**
     * Requests of the store with unread platform messages.
     */
    public function vendorUnreadCount(int $tenantId): int;

    /**
     * @return array{total: int, open: int, completed: int}
     */
    public function vendorStats(int $tenantId): array;
}
