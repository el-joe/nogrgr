<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<AffiliateConversion>
 */
class ConversionRepository extends BaseRepository implements ConversionRepositoryContract
{
    protected function model(): string
    {
        return AffiliateConversion::class;
    }

    public function listing(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $id = preg_match('/^CV-?0*(\d+)$/i', $search, $match) === 1 ? (int) $match[1] : null;

        return AffiliateConversion::query()
            ->with(['affiliate', 'tenant', 'promoCode'])
            ->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when(filled($filters['source'] ?? null), static fn (Builder $q) => $q->where('source', (string) $filters['source']))
            ->when(filled($filters['affiliate_id'] ?? null), static fn (Builder $q) => $q->where('affiliate_id', (int) $filters['affiliate_id']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->when($id !== null, static fn (Builder $byId) => $byId->orWhere('id', $id))
                ->orWhereIn('affiliate_id', Affiliate::withoutTenantScope()->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')->select('id'))
                ->orWhereIn('tenant_id', Tenant::query()->where('name', 'like', '%'.$search.'%')->select('id'))));
    }

    public function counts(): array
    {
        $counts = AffiliateConversion::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $result = ['all' => (int) $counts->sum()];

        foreach (ConversionStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    public function existsForTenant(int $tenantId): bool
    {
        return AffiliateConversion::query()->where('tenant_id', $tenantId)->exists();
    }

    public function lockPendingForTenant(int $tenantId): ?AffiliateConversion
    {
        return AffiliateConversion::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ConversionStatus::Pending->value)
            ->lockForUpdate()
            ->first();
    }

    public function lockByPaymentLog(int $paymentLogId): ?AffiliateConversion
    {
        return AffiliateConversion::query()->where('payment_log_id', $paymentLogId)->lockForUpdate()->first();
    }

    public function paymentLogUsed(int $paymentLogId): bool
    {
        return AffiliateConversion::query()->where('payment_log_id', $paymentLogId)->exists();
    }

    public function lock(int $id): AffiliateConversion
    {
        /** @var AffiliateConversion $conversion */
        $conversion = AffiliateConversion::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $conversion;
    }

    public function lockUnpaidApproved(int $affiliateId): Collection
    {
        return AffiliateConversion::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', ConversionStatus::Approved->value)
            ->whereColumn('paid_amount', '<', 'commission_amount')
            ->orderBy('approved_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
