<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Repositories\Contracts\EditRequestRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<EditRequest>
 */
final class EditRequestRepository extends BaseRepository implements EditRequestRepositoryContract
{
    protected function model(): string
    {
        return EditRequest::class;
    }

    public function query(): Builder
    {
        return EditRequest::withoutTenantScope();
    }

    public function listing(array $filters = []): Builder
    {
        $status = EditRequestStatus::tryFrom((string) ($filters['status'] ?? ''));
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->select('catalog_edit_requests.*', 'tenants.name as store_name', 'tenants.slug as store_slug', 'products.sku as product_sku', 'products.name as product_name')
            ->join('tenants', 'tenants.id', '=', 'catalog_edit_requests.tenant_id')
            ->join('products', 'products.id', '=', 'catalog_edit_requests.product_id')
            ->when($status !== null, static fn (Builder $query) => $query->where('catalog_edit_requests.status', $status?->value))
            ->when($tenantId !== false && $tenantId !== null, static fn (Builder $query) => $query->where('catalog_edit_requests.tenant_id', $tenantId))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $digits = (string) preg_replace('/\D+/', '', $search);
                $query->where(static function (Builder $inner) use ($term, $digits): void {
                    $inner->where('products.sku', 'like', $term)
                        ->orWhere('products.name', 'like', $term)
                        ->orWhere('tenants.name', 'like', $term);

                    if ($digits !== '') {
                        $inner->orWhere('catalog_edit_requests.id', (int) $digits);
                    }
                });
            });
    }

    public function pendingCount(): int
    {
        return $this->query()->where('status', EditRequestStatus::Pending->value)->count();
    }

    public function pendingFor(int $tenantId, int $productId, ?int $variantId): ?EditRequest
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('status', EditRequestStatus::Pending->value)
            ->first();
    }
}
