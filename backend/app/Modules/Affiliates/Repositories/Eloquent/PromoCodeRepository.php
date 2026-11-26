<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<AffiliatePromoCode>
 */
class PromoCodeRepository extends BaseRepository implements PromoCodeRepositoryContract
{
    protected function model(): string
    {
        return AffiliatePromoCode::class;
    }

    public function listing(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $state = (string) ($filters['state'] ?? '');

        return AffiliatePromoCode::query()
            ->with('affiliate')
            ->when(filled($filters['affiliate_id'] ?? null), static fn (Builder $q) => $q->where('affiliate_id', (int) $filters['affiliate_id']))
            ->when($state === 'active', static fn (Builder $q) => $q->where('is_active', true)->where(static fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
            ->when($state === 'inactive', static fn (Builder $q) => $q->where('is_active', false))
            ->when($state === 'expired', static fn (Builder $q) => $q->whereNotNull('ends_at')->where('ends_at', '<', now()))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('code', 'like', '%'.$search.'%')
                ->orWhereIn('affiliate_id', Affiliate::withoutTenantScope()->where('name', 'like', '%'.$search.'%')->select('id'))));
    }

    public function findByCode(string $code): ?AffiliatePromoCode
    {
        return AffiliatePromoCode::query()->where('code', strtoupper(trim($code)))->first();
    }

    public function hasConversions(AffiliatePromoCode $code): bool
    {
        return AffiliateConversion::query()->where('promo_code_id', $code->id)->exists();
    }
}
