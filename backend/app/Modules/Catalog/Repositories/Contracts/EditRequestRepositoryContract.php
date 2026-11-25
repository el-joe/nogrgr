<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Models\Requests\ProductOverride;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product edit requests + the tenant overrides they produce (central DB).
 *
 * @extends RepositoryContract<EditRequest>
 */
interface EditRequestRepositoryContract extends RepositoryContract
{
    /**
     * Filters: status, tenant_id, search (product name / sku, store, ER number).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<EditRequest>
     */
    public function listing(array $filters = []): Builder;

    public function pendingCount(): int;

    public function pendingFor(int $tenantId, int $productId, ?int $variantId): ?EditRequest;

    /**
     * Inserts or replaces the override of one field / locale.
     *
     * @param  array{value: string|null, central_value: string|null, edit_request_id: int|null, approved_by: int|null}  $values
     */
    public function upsertOverride(int $tenantId, int $productId, ?int $variantId, string $field, string $locale, array $values): ProductOverride;

    /**
     * @return Collection<int, ProductOverride>
     */
    public function overridesFor(int $tenantId, int $productId): Collection;
}
