<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Affiliate>
 */
class AffiliateRepository extends BaseRepository implements AffiliateRepositoryContract
{
    private const PER_PAGE = 20;

    protected function model(): string
    {
        return Affiliate::class;
    }

    public function listing(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Affiliate::withoutTenantScope()
            ->withCount(['clicks', 'conversions' => static fn (Builder $q) => $q->whereIn('status', ['approved', 'paid'])])
            ->when(filled($filters['type'] ?? null), static fn (Builder $q) => $q->where('type', (string) $filters['type']))
            ->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')));
    }

    public function counts(): array
    {
        $byType = Affiliate::withoutTenantScope()->selectRaw('type, COUNT(*) AS total')->groupBy('type')->pluck('total', 'type');
        $byStatus = Affiliate::withoutTenantScope()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            'all' => (int) $byType->sum(),
            'vendor' => (int) ($byType[AffiliateType::Vendor->value] ?? 0),
            'independent' => (int) ($byType[AffiliateType::Independent->value] ?? 0),
            'pending' => (int) ($byStatus[AccountStatus::Pending->value] ?? 0),
            'suspended' => (int) ($byStatus[AccountStatus::Suspended->value] ?? 0),
        ];
    }

    public function findById(int $id): ?Affiliate
    {
        return Affiliate::withoutTenantScope()->find($id);
    }

    public function findActiveByCode(string $code): ?Affiliate
    {
        return Affiliate::withoutTenantScope()
            ->where('code', strtoupper(trim($code)))
            ->where('status', AccountStatus::Active->value)
            ->first();
    }

    public function findForTenant(int $tenantId): ?Affiliate
    {
        return Affiliate::withoutTenantScope()->where('tenant_id', $tenantId)->first();
    }

    public function lock(int $id): Affiliate
    {
        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::withoutTenantScope()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $affiliate;
    }

    public function codeExists(string $code): bool
    {
        return Affiliate::withoutTenantScope()->where('code', $code)->exists();
    }

    public function emailTaken(string $email): bool
    {
        return Affiliate::withoutTenantScope()->where('email', $email)->exists();
    }

    public function search(string $term, int $page = 1): array
    {
        $term = trim($term);
        $rows = Affiliate::withoutTenantScope()
            ->when($term !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')))
            ->orderBy('name')
            ->forPage(max(1, $page), self::PER_PAGE + 1)
            ->get(['id', 'name', 'email']);

        return [
            'results' => $rows->take(self::PER_PAGE)->map(static fn (Affiliate $affiliate): array => [
                'id' => $affiliate->id,
                'text' => $affiliate->email !== null ? $affiliate->name.' ('.$affiliate->email.')' : $affiliate->name,
            ])->values()->all(),
            'pagination' => ['more' => $rows->count() > self::PER_PAGE],
        ];
    }
}
