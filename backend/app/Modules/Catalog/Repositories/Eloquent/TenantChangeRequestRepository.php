<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Repositories\Contracts\TenantChangeRequestRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<TenantChangeRequest>
 */
final class TenantChangeRequestRepository extends BaseRepository implements TenantChangeRequestRepositoryContract
{
    protected function model(): string
    {
        return TenantChangeRequest::class;
    }

    public function query(): Builder
    {
        return TenantChangeRequest::withoutTenantScope();
    }

    public function listing(array $filters = []): Builder
    {
        $type = TenantChangeType::tryFrom((string) ($filters['type'] ?? ''));
        $status = TenantChangeStatus::tryFrom((string) ($filters['status'] ?? ''));
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->select('catalog_tenant_change_requests.*', 'tenants.name as store_name', 'tenants.slug as store_slug')
            ->join('tenants', 'tenants.id', '=', 'catalog_tenant_change_requests.tenant_id')
            ->when($type !== null, static fn (Builder $query) => $query->where('catalog_tenant_change_requests.type', $type?->value))
            ->when($status !== null, static fn (Builder $query) => $query->where('catalog_tenant_change_requests.status', $status?->value))
            ->when($tenantId !== false && $tenantId !== null, static fn (Builder $query) => $query->where('catalog_tenant_change_requests.tenant_id', $tenantId))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (Builder $inner) => $inner->where('tenants.name', 'like', $term)->orWhere('tenants.slug', 'like', $term));
            });
    }

    public function pendingCount(): int
    {
        return $this->query()->where('status', TenantChangeStatus::Pending->value)->count();
    }

    public function pendingFor(int $tenantId, string $type): ?TenantChangeRequest
    {
        return $this->query()->where('tenant_id', $tenantId)->where('type', $type)->where('status', TenantChangeStatus::Pending->value)->first();
    }
}
