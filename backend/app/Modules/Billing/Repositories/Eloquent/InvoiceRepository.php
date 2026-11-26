<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * @extends BaseRepository<Invoice>
 */
class InvoiceRepository extends BaseRepository implements InvoiceRepositoryContract
{
    protected function model(): string
    {
        return Invoice::class;
    }

    public function listing(array $filters = []): Builder
    {
        return $this->applyFilters(Invoice::query()->select('invoices.*')->with('tenant'), $filters);
    }

    public function findWithItems(int $id): Invoice
    {
        return Invoice::query()->with(['items', 'tenant'])->findOrFail($id);
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = Invoice::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;
        $search = $filters['search'] ?? null;
        $ownerId = filter_var($filters['owner_id'] ?? null, FILTER_VALIDATE_INT);
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);

        return $query
            // Owner portal: the owner's stores only (set by the server, never by the request).
            ->when($ownerId !== false && $ownerId !== null, fn (Builder $q) => $q->whereIn('invoices.tenant_id', Tenant::query()->select('id')->where('owner_id', $ownerId)))
            ->when($tenantId !== false && $tenantId !== null, fn (Builder $q) => $q->where('invoices.tenant_id', $tenantId))
            ->when(is_string($status) && InvoiceStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('invoices.status', $status))
            ->when(is_string($type) && InvoiceType::tryFrom($type) !== null, fn (Builder $q) => $q->where('invoices.type', $type))
            ->when(is_string($search) && $search !== '', function (Builder $q) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $q->where(fn (Builder $inner) => $inner->where('invoices.number', 'like', $term)
                    ->orWhereIn('invoices.tenant_id', Tenant::query()->select('id')->where('name', 'like', $term)->orWhere('slug', 'like', $term)));
            });
    }

    public function paidCursor(?array $tenantIds = null): LazyCollection
    {
        return Invoice::withoutTenantScope()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('tenant_id')
            ->when($tenantIds !== null, static fn (Builder $query) => $query->whereIn('tenant_id', $tenantIds))
            ->orderBy('id')
            ->lazyById(200);
    }
}
