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
}
