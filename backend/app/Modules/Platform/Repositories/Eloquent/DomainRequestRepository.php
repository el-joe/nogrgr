<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<DomainRequest>
 */
class DomainRequestRepository extends BaseRepository implements DomainRequestRepositoryContract
{
    protected function model(): string
    {
        return DomainRequest::class;
    }

    public function query(): Builder
    {
        return DomainRequest::query()->withoutGlobalScopes();
    }

    public function listing(array $filters = []): Builder
    {
        $status = DomainRequestStatus::tryFrom((string) ($filters['status'] ?? ''));
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->select('domain_requests.*', 'tenants.name as store_name', 'tenants.slug as store_slug')
            ->join('tenants', 'tenants.id', '=', 'domain_requests.tenant_id')
            ->when($status !== null, fn (Builder $query) => $query->where('domain_requests.status', $status?->value))
            ->when($tenantId !== false && $tenantId !== null, fn (Builder $query) => $query->where('domain_requests.tenant_id', $tenantId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('domain_requests.host', 'like', $term)->orWhere('tenants.name', 'like', $term)->orWhere('tenants.slug', 'like', $term));
            });
    }

    public function forTenant(int $tenantId): Collection
    {
        return $this->query()->where('tenant_id', $tenantId)->orderByDesc('id')->get();
    }

    public function hostInUse(string $host, ?int $exceptId = null): bool
    {
        return $this->query()
            ->where('host', strtolower($host))
            ->whereIn('status', [DomainRequestStatus::Pending->value, DomainRequestStatus::Connected->value])
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all();

        return $counts;
    }
}
