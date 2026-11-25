<?php

namespace App\Modules\AfterSales\Repositories\Eloquent;

use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\RefundAttempt;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Refund>
 */
class RefundRepository extends BaseRepository implements RefundRepositoryContract
{
    protected function model(): string
    {
        return Refund::class;
    }

    public function listing(array $filters): Builder
    {
        $status = RefundStatus::tryFrom((string) ($filters['status'] ?? ''));
        $source = RefundSource::tryFrom((string) ($filters['source'] ?? ''));
        $tenant = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $from = is_string($filters['from'] ?? null) && strtotime($filters['from']) !== false ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && strtotime($filters['to']) !== false ? $filters['to'] : null;
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->with('returnRequest:id,reference')
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->when($source !== null, static fn (Builder $query) => $query->where('source', $source?->value))
            ->when(is_int($tenant), static fn (Builder $query) => $query->where('tenant_id', $tenant))
            ->when($from !== null, static fn (Builder $query) => $query->whereDate('requested_at', '>=', (string) $from))
            ->when($to !== null, static fn (Builder $query) => $query->whereDate('requested_at', '<=', (string) $to))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (Builder $inner) => $inner->where('reference', 'like', $term)->orWhere('order_number', 'like', $term));
            });
    }

    public function stats(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(static fn (mixed $n): int => (int) $n)->all();

        return [
            'refunded_30d' => (int) $this->query()->where('status', RefundStatus::Completed->value)->where('processed_at', '>=', now()->subDays(30))->sum('amount'),
            'pending' => (int) $this->query()->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Processing->value])->sum('amount'),
            'failed' => (int) $this->query()->where('status', RefundStatus::Failed->value)->sum('amount'),
            'pending_count' => ($counts[RefundStatus::Pending->value] ?? 0) + ($counts[RefundStatus::Processing->value] ?? 0),
            'failed_count' => $counts[RefundStatus::Failed->value] ?? 0,
            'counts' => $counts,
        ];
    }

    public function findForTenant(int $id, ?int $tenantId = null): ?Refund
    {
        return $this->query()->whereKey($id)->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId))->first();
    }

    public function forOrder(int $tenantId, string $orderUuid): Collection
    {
        return Refund::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->orderByDesc('id')->get();
    }

    public function sumForOrder(int $tenantId, string $orderUuid, array $statuses): int
    {
        return (int) Refund::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->whereIn('status', $statuses)->sum('amount');
    }

    public function nextReference(): string
    {
        $last = (int) Refund::withoutTenantScope()->max('id');

        return 'RF-'.(5001 + $last);
    }

    public function lock(Refund $refund): Refund
    {
        /** @var Refund $locked */
        $locked = Refund::withoutTenantScope()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    public function logAttempt(Refund $refund, array $attributes): RefundAttempt
    {
        /** @var RefundAttempt $attempt */
        $attempt = $refund->attemptLog()->create($attributes);

        return $attempt;
    }
}
